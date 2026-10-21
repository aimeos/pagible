/**
 * @license MIT, https://opensource.org/license/mit
 */

// Raw chunked-text streaming transport for the admin AI chat (POST cms.chat). Kept apart from ai.js
// because that module's transcribe/translate/write go through GraphQL/Apollo, not fetch streaming.
import gettext from './i18n'
import { postHeaders } from './http'
import { useSessionStore, useUserStore } from './stores'
import { urlchat } from './config'
import { AUTH_STATUS } from './graphql'

// Ends the streamed answer, followed by an error message if it failed; a missing marker means cut off
const END = '\x1E'

/**
 * Streams the AI assistant's answer from the chat endpoint.
 *
 * POSTs the prompt and prior turns to the cookie-guarded chat endpoint and passes each streamed chunk
 * to onDelta as ({ text }). HTTP failures throw; 409 (all chat streams of this user are in use) and
 * 429 (rate limit) carry a translated `.message` flagged `.shown` so the caller can render it verbatim
 * instead of a generic error. The answer ends with the END marker: a server message after it throws
 * flagged `.shown`, a stream without the marker (cut off) throws without. Aborting via the signal is
 * not an error: the returned promise resolves with the text decoded so far.
 *
 * @param {string} prompt Current user message (the caller checks permission and non-empty)
 * @param {Array<{role: string, content: string}>} history Prior conversation turns
 * @param {function|null} onDelta Streamed-chunk consumer: ({ text }) => void
 * @param {AbortSignal|null} signal Signal to abort the stream (the Stop button)
 * @param {string} context Additional system context for the conversation
 * @returns {Promise<string>} The assistant text streamed so far (full text on normal completion)
 */
export async function chat(prompt, history = [], onDelta = null, signal = null, context = '') {
  const { $gettext } = gettext

  if (!urlchat) {
    // The cms.chat route is absent (feature off): config.js keeps urlchat empty. Don't fall through to
    // fetch(''), which would POST the prompt to the current admin URL; surface it as a shown error.
    const error = new Error($gettext('The chat assistant is not available.'))
    error.shown = true
    throw error
  }

  let text = ''
  let tail = null // text after the END marker, null until the marker arrived

  try {
    // postHeaders() forwards Laravel's XSRF-TOKEN cookie so the guarded POST passes CSRF
    const send = () =>
      fetch(urlchat, {
        method: 'POST',
        headers: postHeaders('text/plain'),
        credentials: 'include',
        signal: signal,
        body: JSON.stringify({ prompt: prompt, messages: history, context: context })
      })

    let response = await send()
    const user = useUserStore()

    // Session expired: the request was rejected before streaming, so it's safe to send it again
    // (with the renewed XSRF token) after the user signed in again in the re-login dialog
    if (AUTH_STATUS.includes(response.status) && user.me) {
      await useSessionStore().reauth()
      response = await send()
    }

    if (response.ok) {
      // the chat request extends the session like GraphQL requests do
      useSessionStore().touch()
    } else {
      // Two distinct "busy" cases the caller shows verbatim in the bubble: 409 = all per-user
      // stream slots are in use (too many chats running), 429 = the cms-ai rate limit.
      let message = 'HTTP ' + response.status

      if (response.status === 409) {
        message = $gettext('A response is already being generated. Please wait for it to finish.')
      } else if (response.status === 429) {
        message = $gettext('Too many requests. Please wait a moment and try again.')
      }

      const error = new Error(message)
      error.status = response.status
      error.shown = response.status === 409 || response.status === 429
      throw error
    }

    const emit = (chunk) => {
      if (tail !== null) {
        tail += chunk
        return
      }

      const pos = chunk.indexOf(END)

      if (pos !== -1) {
        tail = chunk.slice(pos + 1)
        chunk = chunk.slice(0, pos)
      }

      if (chunk) {
        text += chunk
        onDelta?.({ text: chunk })
      }
    }

    // Throws if the answer failed or was cut off, otherwise returns the complete answer
    const result = () => {
      if (tail === null) {
        throw new Error('Chat stream ended without end marker')
      }

      if (tail.trim()) {
        const error = new Error(tail.trim())
        error.shown = true
        throw error
      }

      return text
    }

    if (!response.body) {
      // A 2xx response with no readable stream (a proxy buffered/stripped the body): nothing to
      // stream, so deliver the whole text in one chunk instead of failing.
      emit(await response.text())
      return result()
    }

    // The body is the raw markdown answer; append each chunk as it streams in
    const reader = response.body.getReader()
    const decoder = new TextDecoder()

    for (;;) {
      const { value, done } = await reader.read()

      if (done) {
        break
      }

      emit(decoder.decode(value, { stream: true }))
    }

    // Flush any bytes the decoder buffered for a multibyte char split across the final chunk
    emit(decoder.decode())

    return result()
  } catch (error) {
    if (error?.name === 'AbortError') {
      // Stop button: keep whatever already streamed. The decoder is deliberately not flushed here -
      // on abort any buffered bytes are an incomplete multibyte char whose remaining bytes were cut
      // off, so decode() would emit a U+FFFD replacement char rather than recover the character.
      return text
    }

    console.error('chat(): chat stream failed', error)
    throw error
  }
}
