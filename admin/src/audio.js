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
