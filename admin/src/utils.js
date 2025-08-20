/**
 * Generates a unique content ID based on the current date and time.
 *
 * @returns String Unique content ID
 */
const uid = (function () {
  const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'
  const EPOCH = new Date('2025-01-01T00:00:00Z').getTime();

  let counter = 0

  return function() {
    // IDs will repeat after ~70 years
    const value = (((Date.now() - EPOCH) / 4096) << 7) | counter
    counter = (counter + 1) & 0b01111111

    return Array.from({ length: 6 }, (_, i) => {
      const index = (value >> (6 * (5 - i))) & 63
      return BASE64[i === 0 ? index % 52 : index]
    }).join('');
  }
})()

export { uid }



/**
 * Extracts the audio from an URL and converts it to a MP3 file (mono).
 *
 * @param {string} url - URL of the media file.
 * @returns {Promise} - Promise with MP3 blob
 */
export async function url2audio(url) {
  const context = new AudioContext()

  const response = await fetch(url)
  const arrayBuffer = await response.arrayBuffer()
  const audioBuffer = await context.decodeAudioData(arrayBuffer)
  const channels = []

  for (let i = 0; i < audioBuffer.numberOfChannels; i++) {
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
