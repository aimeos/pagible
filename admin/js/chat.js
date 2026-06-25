/**
 * @license MIT, https://opensource.org/license/mit
 */

// Raw chunked-text streaming transport for the admin AI chat (POST cms.chat). Kept apart from ai.js
// because that module's transcribe/translate/write go through GraphQL/Apollo, not fetch streaming.
import gettext from './i18n'
import { postHeaders } from './utils'
import { urlchat } from './config'

/**
 * Streams the AI assistant's answer from the chat endpoint.
 *
 * POSTs the prompt and prior turns to the cookie-guarded chat endpoint and passes each streamed chunk
 * to onDelta as ({ text }). HTTP failures throw; 409 (a stream is already running for this user) and
 * 429 (rate limit) carry a translated `.message` flagged `.shown` so the caller can render it verbatim
 * instead of a generic error. Aborting via the signal is not an error: the returned promise resolves
 * with the text decoded so far.
 *
 * @param {string} prompt Current user message (the caller checks permission and non-empty)
 * @param {Array<{role: string, content: string}>} history Prior conversation turns
 * @param {function|null} onDelta Streamed-chunk consumer: ({ text }) => void
 * @param {AbortSignal|null} signal Signal to abort the stream (the Stop button)
 * @returns {Promise<string>} The assistant text streamed so far (full text on normal completion)
 */
export async function chat(prompt, history = [], onDelta = null, signal = null) {
  const { $gettext } = gettext

  if (!urlchat) {
    // The cms.chat route is absent (feature off): config.js keeps urlchat empty. Don't fall through to
    // fetch(''), which would POST the prompt to the current admin URL; surface it as a shown error.
    const error = new Error($gettext('The chat assistant is not available.'))
    error.shown = true
    throw error
  }

  let text = ''

  try {
    // postHeaders() forwards Laravel's XSRF-TOKEN cookie so the guarded POST passes CSRF
    const response = await fetch(urlchat, {
      method: 'POST',
      headers: postHeaders('text/plain'),
      credentials: 'include',
      signal: signal,
      body: JSON.stringify({ prompt: prompt, messages: history })
    })

    if (!response.ok) {
      // Two distinct "busy" cases the caller shows verbatim in the bubble: 409 = the per-user
      // concurrency lock is held (a stream is already running), 429 = the cms-ai rate limit.
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

    if (!response.body) {
      // A 2xx response with no readable stream (a proxy buffered/stripped the body): nothing to
      // stream, so deliver the whole text in one chunk instead of failing.
      const full = await response.text()

      if (full) {
        text = full
        onDelta?.({ text: full })
      }

      return text
    }

    // The body is the raw markdown answer; append each chunk as it streams in
    const reader = response.body.getReader()
    const decoder = new TextDecoder()

    for (;;) {
      const { value, done } = await reader.read()

      if (done) {
        break
      }

      const chunk = decoder.decode(value, { stream: true })

      if (chunk) {
        text += chunk
        onDelta?.({ text: chunk })
      }
    }

    // Flush any bytes the decoder buffered for a multibyte char split across the final chunk
    const last = decoder.decode()

    if (last) {
      text += last
      onDelta?.({ text: last })
    }

    return text
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
