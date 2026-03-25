/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gql from 'graphql-tag'
import gettext from './i18n'
import { apolloClient } from './graphql'
import { toMp3, transcription } from './audio'
import { useUserStore, useMessageStore } from './stores'
import { url } from './utils'


/**
 * Transcribes audio/video from a media URL to text via GraphQL
 *
 * Requires `audio:transcribe` permission. Converts the media to MP3 before uploading.
 *
 * @param {string} input Media file path or URL
 * @returns {Promise<string>|undefined} Transcribed text or undefined if permission denied
 */
export function transcribe(input) {
  const user = useUserStore()
  const messages = useMessageStore()
  const { $gettext } = gettext

  if (!user.can('audio:transcribe')) {
    messages.add($gettext('Permission denied'), 'error')
    return
  }

  return toMp3(url(input, true))
    .then((blob) => {
      return apolloClient.mutate({
        mutation: gql`
          mutation ($file: Upload!) {
            transcribe(file: $file)
          }
        `,
        variables: {
          file: new File([blob], 'audio.mp3', { type: 'audio/mpeg' })
        },
        context: {
          hasUpload: true
        }
      })
    })
    .then((result) => {
      if (result.errors) {
        throw result
      }

      return transcription(JSON.parse(result.data?.transcribe || '[]'))
    })
    .catch((error) => {
      messages.add($gettext('Error transcribing file') + ':\n' + error, 'error')
      console.error(`useAi::transcribe(): Error transcribing from media URL`, error)
    })
}


/**
 * Translates one or more texts to a target language via GraphQL
 *
 * Requires `text:translate` permission.
 *
 * @param {string|string[]} texts Text(s) to translate
 * @param {string} to Target language code (e.g. 'DE', 'FR')
 * @param {string|null} from Source language code or null for auto-detection
 * @param {string|null} context Additional context to improve translation quality
 * @returns {Promise<string[]>|undefined} Translated texts or undefined if permission denied
 */
export function translate(texts, to, from = null, context = null) {
  const user = useUserStore()
  const messages = useMessageStore()
  const { $gettext } = gettext

  if (!user.can('text:translate')) {
    messages.add($gettext('Permission denied'), 'error')
    return
  }

  if (!Array.isArray(texts)) {
    texts = [texts].filter((v) => !!v)
  }

  if (!texts.length) {
    return Promise.resolve([])
  }

  if (!to) {
    return Promise.reject(new Error('Target language is required'))
  }

  return apolloClient
    .mutate({
      mutation: gql`
        mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
          translate(texts: $texts, to: $to, from: $from, context: $context)
        }
      `,
      variables: {
        texts: texts,
        to: to.toUpperCase(),
        from: from?.toUpperCase(),
        context: context
      }
    })
    .then((result) => {
      if (result.errors) {
        throw result
      }

      return result.data?.translate || []
    })
    .catch((error) => {
      messages.add($gettext('Error translating texts') + ':\n' + error, 'error')
      console.error(`useAi::translate(): Error translating texts`, error)
    })
}


/**
 * Generates text from a prompt using AI via GraphQL
 *
 * Requires `text:write` permission.
 *
 * @param {string} prompt Instructions for text generation
 * @param {string|string[]} context Additional context strings joined with newlines
 * @param {string[]} files File URLs to include as context for the AI
 * @returns {Promise<string>|undefined} Generated text or undefined if permission denied
 */
export function write(prompt, context = [], files = []) {
  const user = useUserStore()
  const messages = useMessageStore()
  const { $gettext } = gettext

  if (!user.can('text:write')) {
    return messages.add($gettext('Permission denied'), 'error')
  }

  prompt = String(prompt).trim()

  if (!prompt) {
    messages.add($gettext('Prompt is required for generating text'), 'error')
    return Promise.resolve('')
  }

  if (!Array.isArray(context)) {
    context = [context]
  }

  context.push('Only return the requested data without any additional information')

  return apolloClient
    .mutate({
      mutation: gql`
        mutation ($prompt: String!, $context: String, $files: [String!]) {
          write(prompt: $prompt, context: $context, files: $files)
        }
      `,
      variables: {
        prompt: prompt,
        context: context.filter((v) => !!v).join('\n'),
        files: files.filter((v) => !!v)
      }
    })
    .then((result) => {
      if (result.errors) {
        throw result
      }

      return result.data?.write?.replace(/^"(.*)"$/, '$1') || ''
    })
    .catch((error) => {
      messages.add($gettext('Error generating text') + ':\n' + error, 'error')
      console.error(`useAi::write(): Error generating text`, error)
    })
}
