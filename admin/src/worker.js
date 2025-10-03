/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import * as lame from '@breezystack/lamejs';


/**
 * Converts a Float32Array (-1 to 1) to Int16Array (-32768 to 32767).
 *
 * @param {Float32Array} float32 - PCM float samples
 * @returns {Int16Array} - PCM 16-bit samples
 */
function convert(float32) {
  const int16 = new Int16Array(float32.length);

  for(let i = 0; i < float32.length; i++) {
    const s = Math.max(-1, Math.min(1, float32[i]));
    int16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
  }

  return int16;
}


/**
 * Mixes any PCM data (mono or stereo) into a mono Float32Array.
 *
 * @param {Array} channels - Audio channels (Float32Arrays)
 * @param {number} length - Length of the audio data
 * @returns {Float32Array} - Mono PCM samples
 */
function toMono(channels, length) {
  if (channels.length === 1) return channels[0];

  const mono = new Float32Array(length);
  const left = channels[0];
  const right = channels[1];

  for (let i = 0; i < length; i++) {
    mono[i] = (left[i] + right[i]) / 2;
  }

  return mono;
}


/**
 * Encodes a mono Float32Array to MP3 using lamejs.
 *
 * @param {Float32Array} pcm - Mono PCM float samples
 * @param {number} sampleRate - Audio sample rate
 * @returns {Blob} - MP3 blob
 */
function encode(pcm, sampleRate) {
  const encoder = new lame.Mp3Encoder(1, sampleRate, 64);
  const samples = convert(pcm);
  const blockSize = 1152;
  const mp3 = [];

  for (let i = 0; i < samples.length; i += blockSize) {
    const chunk = samples.subarray(i, i + blockSize);
    const buffer = encoder.encodeBuffer(chunk);

    if(buffer.length > 0) mp3.push(new Uint8Array(buffer));
  }

  const flush = encoder.flush();
  if(flush.length > 0) mp3.push(new Uint8Array(flush));

  return new Blob(mp3, { type: 'audio/mpeg' });
}


self.onmessage = async (e) => {
  // Expect to receive AudioBuffer channel arrays, length and sampleRate
  const { channels, length, sampleRate } = e.data;
  const mp3 = encode(toMono(channels, length), sampleRate);

  self.postMessage(mp3);
}