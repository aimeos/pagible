/**
 * @license MIT, https://opensource.org/license/mit
 */

import { markRaw, reactive } from 'vue'
import gettext from './i18n'
import { useMessageStore } from './stores'

/**
 * Starts a recording if none is running, otherwise stops it and passes the transcribed text to "done"
 *
 * @param {Promise|null} audio Running recording returned by the previous call
 * @param {Function} busy Called with true while transcribing and with false afterwards
 * @param {Function} done Called with the transcribed text
 * @returns {Promise|null} New recording state the caller must store, resolves to null if recording failed
 */
export function dictate(audio, busy, done) {
  if (!audio) {
    return markRaw(
      import('./audio')
        .then((mod) => mod.recording().start())
        .catch((error) => {
          useMessageStore().error(gettext.$gettext('Error recording audio'), error)
          return null
        })
    )
  }

  busy(true)

  audio
    .then((rec) => rec?.stop())
    .then((buffer) => {
      // nothing recorded, e.g. stopped immediately
      if (!buffer) return

      return import('./ai')
        .then((mod) => mod.transcribe(buffer))
        .then((transcription) => done(transcription.asText()))
    })
    // transcribe() reports its own errors, these are from stopping or encoding the recording
    .catch((error) => useMessageStore().error(gettext.$gettext('Error recording audio'), error))
    .finally(() => busy(false))

  return null
}

/**
 * Returns the reactive dictation state of a component, keyed for components with several dictation buttons
 *
 * Store it in data() and call stop() in beforeUnmount() to end running recordings.
 */
export function useDictation() {
  return reactive({
    audio: {},
    loading: {},

    /**
     * Returns if a recording is running for the key
     */
    active(key = '') {
      return !!this.audio[key]
    },

    /**
     * Returns if the recording of the key is transcribed
     */
    busy(key = '') {
      return !!this.loading[key]
    },

    /**
     * Starts the recording for the key or stops it and passes the transcribed text to "done"
     */
    toggle(done, key = '') {
      const audio = dictate(this.audio[key] || null, (busy) => (this.loading[key] = busy), done)
      this.audio[key] = audio

      // a failed recording (e.g. microphone access denied) resolves to null and ends the recording state
      audio?.then((rec) => {
        if (!rec && this.audio[key] === audio) {
          delete this.audio[key]
        }
      })
    },

    /**
     * Ends all running recordings without transcribing them
     */
    stop() {
      for (const audio of Object.values(this.audio)) {
        audio?.then((rec) => rec?.stop?.()).catch(() => {})
      }
      this.audio = {}
    }
  })
}
