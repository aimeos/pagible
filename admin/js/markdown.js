/** @license MIT, https://opensource.org/license/mit */

// content element types which can be converted to Markdown and back
export const MARKDOWN_TYPES = ['text', 'code', 'heading']

/**
 * Converts a text, code or heading content element to Markdown.
 *
 * @param {Object} el Content element
 * @returns {String} Markdown text, empty for other element types
 */
export function toMarkdown(el) {
  if (el.type === 'text') {
    return el.data.text || ''
  } else if (el.type === 'code') {
    return `\`\`\`${el.data.lang || ''}\n${el.data.text || ''}\n\`\`\``
  } else if (el.type === 'heading') {
    return `${'#'.repeat(Number(el.data.level) || 1)} ${el.data.title || ''}`
  }
  return ''
}

/**
 * Splits Markdown text into the types and data of code, heading, table and text elements.
 *
 * @param {String} text Markdown text
 * @returns {Promise<Array<Object>>} List of { type, data } objects
 */
export async function fromMarkdown(text) {
  const [{ fromMarkdown: parse }, { toString }, { toMarkdown: serialize }] = await Promise.all([
    import('mdast-util-from-markdown'),
    import('mdast-util-to-string'),
    import('mdast-util-to-markdown')
  ])

  return parse(text || '').children.map((node) => {
    switch (node.type) {
      case 'code':
        return { type: 'code', data: { lang: node.lang || null, text: node.value.trim() } }
      case 'heading':
        return { type: 'heading', data: { title: toString(node).trim(), level: String(node.depth) } }
      case 'table': {
        const rows = node.children
          .map((row) =>
            row.children
              .map((cell) => cell.children.map((c) => c.value || '').join(''))
              .join(';')
          )
          .join('\n')
        return { type: 'table', data: { text: rows.trim() } }
      }
      default:
        return { type: 'text', data: { text: serialize(node).trim() } }
    }
  })
}
