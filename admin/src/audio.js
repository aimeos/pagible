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
  const chunks = []


  return {
    async start() {
      if (active) return
      active = true

      const blob = new Blob([`
        class RecorderProcessor extends AudioWorkletProcessor {
          process(inputs) {
            const input = inputs[0]
            if (input && input[0]) this.port.postMessage(input[0])
            return true
          }
        }
        registerProcessor('recorder-processor', RecorderProcessor)
      `], {type: 'application/javascript'})

      audioContext = new AudioContext()
      await audioContext.audioWorklet.addModule(URL.createObjectURL(blob))

      node = new AudioWorkletNode(audioContext, 'recorder-processor')

      node.port.onmessage = (e) => {
        chunks.push(new Float32Array(e.data))
      }

      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })

      source = audioContext.createMediaStreamSource(stream)
      source.connect(node)

      return this
    },


    async stop() {
      if (!active || !node || !source) return
      active = false

      source.disconnect()
      node.disconnect()
      await audioContext.close()

      const length = chunks.reduce((a, c) => a + c.length, 0)
      const buffer = new Float32Array(length)
      let offset = 0

      for (const chunk of chunks) {
        buffer.set(chunk, offset)
        offset += chunk.length
      }

      const context = new AudioContext()
      const audioBuffer = context.createBuffer(
        1, // number of channels
        buffer.length,
        44100
      )

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
  const context = new AudioContext()
  const channels = []
  let audioBuffer

  if(input instanceof AudioBuffer) {
    audioBuffer = input
  } else if(input instanceof ArrayBuffer) {
    audioBuffer = await context.decodeAudioData(input)
  } else if(typeof input === 'string' || input instanceof URL) {
    audioBuffer = await context.decodeAudioData(await (await fetch(input)).arrayBuffer())
  } else if(input instanceof Blob) {
    audioBuffer = await context.decodeAudioData(await input.arrayBuffer())
  } else {
    throw new Error('toMp3(): Unsupported input type. Expected URL, Blob, ArrayBuffer, or AudioBuffer.')
  }

  for(let i = 0; i < audioBuffer.numberOfChannels; i++) {
    channels.push(audioBuffer.getChannelData(i))
  }

  const worker = new Worker(new URL('./worker.js', import.meta.url), { type: 'module' })

  return new Promise((resolve) => {
    worker.onmessage = (e) => resolve(e.data)
    worker.postMessage({
        channels, // audioBuffer itself isn't transferable
        length: audioBuffer.length,
        sampleRate: audioBuffer.sampleRate
      }, channels.map(c => c.buffer) // Transfer with zero-copy
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
    asText(sep = "\n") {
      return list.map(e => e.text ?? '').join(sep);
    },

    /**
     * Returns the list as a valid WebVTT string
     * Expects list items to have { start, end, text } structure
     */
    asVTT() {
      return 'WEBVTT\n\n' + list.map((item, i) => {
        const { start = 0, end = 0, text = '' } = item;

        const formatTime = (s) => {
            const ms = Math.floor((s % 1) * 1000);
            const totalSeconds = Math.floor(s);

            const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
            const seconds = String(totalSeconds % 60).padStart(2, '0');

            return `${hours}:${minutes}:${seconds}.${String(ms).padStart(3, '0')}`;
        };

        return `${i + 1}\n${formatTime(start)} --> ${formatTime(end)}\n${text}\n`;
      }).join('\n');
    },
  };
}
