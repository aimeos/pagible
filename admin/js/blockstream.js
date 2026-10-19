/** @license MIT, https://opensource.org/license/mit */

/**
 * Splits a streamed Markdown answer into completed blocks, each rendered once, and the open
 * trailing block which is still receiving text.
 *
 * The message object holds the stream state, so it can be a reactive list entry:
 * - content: full raw text received so far
 * - blocks: rendered HTML of the completed blocks (never re-rendered)
 * - pending: raw text of the open trailing block
 * - streaming: true until finishStream() has been called
 */

/**
 * Returns a new message which receives a streamed answer.
 *
 * @param {Object} props Additional message properties, e.g. id and role
 * @returns {Object} Message with empty stream state
 */
export function streamMessage(props = {}) {
  return {
    ...props,
    content: '',
    blocks: [],
    pending: '',
    mark: 0, // start of the open trailing block
    scanned: 0, // end of the text already checked for code fences
    fenceOpen: false,
    streaming: true
  }
}

/**
 * Appends a chunk of text and renders the blocks completed by it.
 *
 * A blank line may close one or more blocks, unless it's inside a fenced code block. Fences are
 * tracked incrementally by counting only the line-start ``` delimiters in the newly scanned span
 * (so inline backticks don't open one), which avoids re-scanning the whole text for each chunk.
 *
 * @param {Object} msg Message created by streamMessage()
 * @param {String} text Received text chunk
 * @param {Function} render Converts Markdown text to HTML
 */
export function appendChunk(msg, text, render) {
  const boundary = (msg.content.slice(-1) + text).includes('\n\n')
  msg.content += text
  msg.pending += text // accumulate the raw tail incrementally (O(chunk), not O(tail))

  if (!boundary) {
    return
  }

  const end = msg.content.lastIndexOf('\n\n')

  if (end < msg.scanned) {
    return
  }

  if ((msg.content.slice(msg.scanned, end).match(/^```/gm) || []).length % 2) {
    msg.fenceOpen = !msg.fenceOpen
  }
  msg.scanned = end

  if (!msg.fenceOpen) {
    msg.blocks.push(render(msg.content.slice(msg.mark, end)))
    msg.mark = end + 2
    msg.scanned = end + 2
    msg.pending = msg.content.slice(msg.mark) // reset tail after a commit
  }
}

/**
 * Renders the open trailing block as the last completed one and ends the stream.
 *
 * @param {Object} msg Message created by streamMessage()
 * @param {Function} render Converts Markdown text to HTML
 */
export function finishStream(msg, render) {
  if (msg.mark < msg.content.length) {
    msg.blocks.push(render(msg.content.slice(msg.mark)))
    msg.mark = msg.content.length
  }
  msg.pending = ''
  msg.streaming = false
}
