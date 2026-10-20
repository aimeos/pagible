// Maximum recording length in seconds, further audio data is dropped
const MAX_SECONDS = 180

/**
 * Provides audio recording functionality using the MediaRecorder API.
 *
 * @returns Recording object with start() and stop() methods
 */
export function recording() {
  let audioContext
  let source
  let node
  let active = false
  let chunks = []
  let samples = 0
  let blobUrl

  // releases the audio context, the worklet and the microphone
  async function release() {
    source?.mediaStream.getTracks().forEach((t) => t.stop())
    source?.disconnect()
    node?.disconnect()
    await audioContext?.close().catch(() => {})

    if (blobUrl) {
      URL.revokeObjectURL(blobUrl)
    }

    audioContext = source = node = blobUrl = null
  }

  // sets up the audio worklet and connects the microphone
  async function init() {
    const blob = new Blob(
      [
        `
      class RecorderProcessor extends AudioWorkletProcessor {
        process(inputs) {
          const input = inputs[0]
          if (input && input[0]) this.port.postMessage(input[0])
          return true
        }
      }
      registerProcessor('recorder-processor', RecorderProcessor)
    `
      ],
      { type: 'application/javascript' }
    )

    audioContext = new AudioContext()
    blobUrl = URL.createObjectURL(blob)
    await audioContext.audioWorklet.addModule(blobUrl)

    node = new AudioWorkletNode(audioContext, 'recorder-processor')

    // the worklet posts render quanta of 128 frames, so the limit is counted in samples
    const maxSamples = audioContext.sampleRate * MAX_SECONDS

    node.port.onmessage = (e) => {
      if (samples < maxSamples) {
        chunks.push(new Float32Array(e.data))
        samples += e.data.length
      }
    }

    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })

    source = audioContext.createMediaStreamSource(stream)
    source.connect(node)
  }

  return {
    async start() {
      if (active) return this
      active = true

      try {
        await init()
      } catch (error) {
        active = false
        chunks = []
        samples = 0
        await release()
        throw error
      }

      return this
    },

    /**
     * Stops the recording
     *
     * @returns {Promise<AudioBuffer|null>} Recorded audio or null if nothing was recorded
     */
    async stop() {
      if (!active || !node || !source) return null
      active = false

      const sampleRate = audioContext.sampleRate
      await release()

      const buffer = new Float32Array(samples)
      let offset = 0

      for (const chunk of chunks) {
        buffer.set(chunk, offset)
        offset += chunk.length
      }

      chunks = []
      samples = 0

      if (!buffer.length) return null

      const audioBuffer = new AudioBuffer({ length: buffer.length, numberOfChannels: 1, sampleRate })
      audioBuffer.getChannelData(0).set(buffer)

      return audioBuffer
    }
  }
}

/**
 * Converts the audio data to a MP3 file (mono).
 *
 * @param {*} input - URL, Blob, ArrayBuffer, or AudioBuffer
 * @returns {Promise} - Promise with MP3 blob
 */
export async function toMp3(input) {
  const channels = []
  let audioBuffer

  if (!input) {
    throw new Error('toMp3(): No audio input')
  }

  if (input instanceof AudioBuffer) {
    audioBuffer = input
  } else {
    const context = new AudioContext()
    try {
      if (input instanceof ArrayBuffer) {
        audioBuffer = await context.decodeAudioData(input)
      } else if (typeof input === 'string' || input instanceof URL) {
        const response = await fetch(input)

        if (!response.ok) {
          throw new Error(`toMp3(): Fetching audio failed with status ${response.status}`)
        }

        audioBuffer = await context.decodeAudioData(await response.arrayBuffer())
      } else if (input instanceof Blob) {
        audioBuffer = await context.decodeAudioData(await input.arrayBuffer())
      } else {
        throw new Error(
          'toMp3(): Unsupported input type. Expected URL, Blob, ArrayBuffer, or AudioBuffer.'
        )
      }
    } finally {
      await context.close()
    }
  }

  for (let i = 0; i < audioBuffer.numberOfChannels; i++) {
    channels.push(audioBuffer.getChannelData(i))
  }

  const worker = new Worker(new URL('./worker.js', import.meta.url), { type: 'module' })

  return new Promise((resolve, reject) => {
    worker.onmessage = (e) => {
      worker.terminate()
      e.data?.error ? reject(new Error(e.data.error)) : resolve(e.data?.mp3)
    }
    worker.onerror = (e) => {
      worker.terminate()
      reject(e)
    }
    worker.postMessage(
      {
        channels, // audioBuffer itself isn't transferable
        length: audioBuffer.length,
        sampleRate: audioBuffer.sampleRate
      },
      channels.map((c) => c.buffer) // Transfer with zero-copy
    )
  })
}

/**
 * Transcribes a list of audio segments into text.
 *
 * @param {*} list List of sements with { start, end, text } structure
 * @returns Transcription object with asText() and asVTT() methods
 */
export function transcription(list = []) {
  return {
    asText(sep = '\n') {
      return list.map((e) => e.text ?? '').join(sep)
    },

    /**
     * Returns the list as a valid WebVTT string
     * Expects list items to have { start, end, text } structure
     */
    asVTT() {
      return (
        'WEBVTT\n\n' +
        list
          .map((item, i) => {
            const { start = 0, end = 0 } = item
            // blank lines end a cue and "-->" starts its timing line, so both are not allowed in the text
            const text = String(item.text ?? '').replace(/-->/g, '->').replace(/\n\s*\n/g, '\n').trim()

            const formatTime = (s) => {
              const ms = Math.floor((s % 1) * 1000)
              const totalSeconds = Math.floor(s)

              const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0')
              const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0')
              const seconds = String(totalSeconds % 60).padStart(2, '0')

              return `${hours}:${minutes}:${seconds}.${String(ms).padStart(3, '0')}`
            }

            return `${i + 1}\n${formatTime(start)} --> ${formatTime(end)}\n${text}\n`
          })
          .join('\n')
      )
    }
  }
}
