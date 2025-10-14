/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


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
