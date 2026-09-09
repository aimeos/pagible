/** @license MIT, https://opensource.org/license/mit */

import DOMPurify from 'dompurify'
import { diffArrays, diffWordsWithSpace } from 'diff'
import { Marked } from 'marked'

const formats = { B: 'bold', STRONG: 'bold', I: 'italic', EM: 'italic', U: 'underline', S: 'strike', DEL: 'strike', STRIKE: 'strike', CODE: 'code' }
const blocks = new Set(['P', 'DIV', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'TR'])
const structures = new Set(['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE'])
const markdown = new Marked({ async: false, gfm: true })
const graphemes = new Intl.Segmenter(undefined, { granularity: 'grapheme' })
const segments = new Intl.Segmenter(undefined, { granularity: 'word' })

export function describe(value, type) {
  const rich = ['text', 'html', 'markdown'].includes(type)
  const html = typeof value === 'string' && (rich || (!type && /<\/?[a-z][^>]*>/i.test(value)))
  if (!html) return { text: value == null ? '' : String(value), html: false, marks: [], structure: [], images: [], lists: [] }
  if (type === 'markdown') value = markdown.parse(value)

  // Read an inert sanitized fragment. Neither historical HTML nor its links are rendered.
  const fragment = DOMPurify.sanitize(value, { RETURN_DOM_FRAGMENT: true })
  const marks = [], structure = [], images = [], lists = []
  let text = ''
  const listing = (value, list) => {
    if (!list || !value.trim()) return
    const { started, ...item } = list
    lists.push({ ...item, first: !started, start: text.length + value.length - value.trimStart().length, end: text.length + value.length })
    list.started = true
  }
  const walk = (node, path, parents = [], list = null) => {
    if (node.nodeType === 3) {
      if (!node.textContent.trim() && /\n/.test(node.textContent) &&
        [node.previousSibling, node.nextSibling].some(sibling => blocks.has(sibling?.nodeName) || ['TABLE', 'UL', 'OL', 'PRE'].includes(sibling?.nodeName))) return
      if (node.textContent.trim()) structure.push({ start: text.length, end: text.length + node.textContent.length, types: parents.length ? parents : ['P'] })
      listing(node.textContent, list)
      text += node.textContent
      return
    }
    if (node.nodeType !== 1) return
    if (node.tagName === 'BR') { text += '\n'; return }
    if (node.tagName === 'IMG') {
      images.push({ start: text.length, attributes: Object.fromEntries(Array.from(node.attributes).map(attr => [attr.name, attr.value]).sort(([a], [b]) => a.localeCompare(b))) })
      listing(node.getAttribute('alt') || '', list)
      text += node.getAttribute('alt') || ''
      return
    }
    if (node.tagName === 'TABLE') {
      if (text && !text.endsWith('\n')) text += '\n'
      if (node.caption?.textContent.trim()) text += node.caption.textContent.trim() + '\n'
      for (const row of node.rows) {
        text += '| ' + Array.from(row.cells).map(cell => cell.textContent.replace(/\s+/g, ' ').trim()).join(' | ') + ' |\n'
      }
      return
    }

    if (blocks.has(node.tagName) && text && !text.endsWith('\n')) text += '\n'
    const start = text.length
    const context = structures.has(node.tagName) ? [...parents, node.tagName] : parents
    const isList = node.tagName === 'OL' || node.tagName === 'UL'
    const reversed = node.tagName === 'OL' && node.reversed
    let number = node.hasAttribute('start') ? node.start : reversed ? [...node.children].filter(child => child.tagName === 'LI').length : 1
    node.childNodes.forEach((child, index) => {
      let item = list
      if (isList && child.tagName === 'LI') {
        if (child.hasAttribute('value')) number = child.value
        item = { kind: node.tagName.toLowerCase(), number: node.tagName === 'OL' ? number : undefined, depth: context.filter(type => type === 'OL' || type === 'UL').length,
          type: node.tagName === 'OL' && ['1', 'a', 'A', 'i', 'I'].includes(child.type || node.type) ? child.type || node.type : '1' }
        number += reversed ? -1 : 1
      }
      walk(child, path + ':' + index, context, item)
    })
    const content = text.slice(start)
    const value = content.trim()
    const range = { path, start: start + content.length - content.trimStart().length, end: start + content.trimEnd().length }
    const kinds = new Set([formats[node.tagName]].filter(Boolean))
    if (node.style.fontWeight === 'bold' || Number(node.style.fontWeight) >= 600) kinds.add('bold')
    if (node.style.fontStyle === 'italic') kinds.add('italic')
    if (node.style.textDecoration.includes('underline')) kinds.add('underline')
    if (node.style.textDecoration.includes('line-through')) kinds.add('strike')
    for (const kind of kinds) if (value) marks.push({ kind, text: value, ...range })
    if (node.tagName === 'A' && value) marks.push({ kind: 'link', text: value, ...range, value: node.getAttribute('href') || '' })
    if (blocks.has(node.tagName) && !text.endsWith('\n')) text += '\n'
  }
  fragment.childNodes.forEach((node, index) => walk(node, String(index)))
  const offset = text.length - text.trimStart().length
  marks.forEach(mark => { mark.start -= offset; mark.end -= offset })
  structure.forEach(mark => { mark.start -= offset; mark.end -= offset })
  lists.forEach(item => { item.start -= offset; item.end -= offset })
  images.forEach(image => { image.start = Math.max(0, Math.min(text.trim().length, image.start - offset)) })
  return { text: text.trim(), html, marks, structure, images, lists }
}

export function decorate(words, marks, changes, offset = 0) {
  return words.flatMap(word => {
    const start = offset, end = offset += word.value.length
    const active = marks.filter(mark => mark.start < end && mark.end > start)
    const edited = changes.filter(mark => mark.start < end && mark.end > start)
    const bounds = [...new Set([start, end, ...[...active, ...edited].flatMap(mark => [Math.max(start, mark.start), Math.min(end, mark.end)])])].sort((a, b) => a - b)
    return bounds.slice(0, -1).map((from, i) => ({
      ...word, value: word.value.slice(from - start, bounds[i + 1] - start),
      kinds: [...new Set(active.filter(mark => mark.start <= from && mark.end > from).map(mark => mark.kind))],
      formatting: edited.some(mark => mark.start <= from && mark.end > from)
    }))
  })
}

export function structure(before, after, rows = paragraphs(before.text, after.text)) {
  const changes = []
  const shape = value => {
    const entries = value.structure || []
    let index = 0
    return (offset, text) => {
      while (entries[index]?.end <= offset) index++
      return entries[index]?.start < offset + text.length ? entries[index].types : ['P']
    }
  }
  const oldShape = shape(before), newShape = shape(after)
  let a = 0, b = 0, previous
  for (const row of rows) {
    let key
    if (row.before !== null && row.after !== null && row.before.trim() && row.after.trim()) {
      const old = oldShape(a, row.before), next = newShape(b, row.after)
      if (JSON.stringify(old) !== JSON.stringify(next)) {
        key = JSON.stringify([old, next])
        if (key !== previous) changes.push({ before: old, after: next, text: row.after, spans: [] })
        changes.at(-1).spans.push({ before: [a, a + row.before.length], after: [b, b + row.after.length] })
      }
    }
    previous = key
    if (row.before !== null) a += row.before.length + 1
    if (row.after !== null) b += row.after.length + 1
  }
  return changes
}

export function formatting(before, after, rows = paragraphs(before.text, after.text)) {
  if (!before.marks.length && !after.marks.length) return []
  // Project formatting boundaries through text edits. Replacement text can retain
  // its formatting even when none of its words match the previous text.
  const ranges = []
  let a = 0, b = 0
  for (const row of rows) {
    let previous
    for (const part of diffWordsWithSpace(row.before ?? '', row.after ?? '')) {
      const changed = !!(part.added || part.removed)
      const range = changed && previous?.changed ? previous : { a, b, endA: a, endB: b, changed }
      if (range !== previous) ranges.push(range)
      if (!part.added) a += part.value.length
      if (!part.removed) b += part.value.length
      range.endA = a
      range.endB = b
      previous = range
    }
    if (row.before !== null) a++
    if (row.after !== null) b++
  }
  const project = position => {
    const points = []
    for (const range of ranges) {
      if (position === range.a) points.push(range.b)
      if (position === range.endA) points.push(range.endB)
      if (!range.changed && position > range.a && position < range.endA) points.push(range.b + position - range.a)
    }
    return points.length ? [Math.min(...points), Math.max(...points)] : null
  }
  const remaining = new Set(after.marks)
  const changes = []
  for (const old of before.marks) {
    const start = project(old.start), end = project(old.end)
    const next = [...remaining].find(mark => mark.kind === old.kind && (start && end
      ? mark.start >= start[0] && mark.start <= start[1] && mark.end >= end[0] && mark.end <= end[1]
      : mark.path === old.path))
    if (next) remaining.delete(next)
    if (!next || old.value !== next.value) changes.push({ ...(next || old), side: next ? 'after' : 'before', action: next ? 'changed' : 'removed', before: old.value, after: next?.value })
  }
  remaining.forEach(mark => changes.push({ ...mark, side: 'after', action: 'added', after: mark.value }))
  return changes
}

export function images(before, after) {
  const parts = diffArrays(before.images, after.images, { comparator: (a, b) => JSON.stringify(a.attributes) === JSON.stringify(b.attributes) })
  const changes = []
  let old = 0, next = 0
  for (let i = 0; i < parts.length; i++) {
    if (!parts[i].added && !parts[i].removed) {
      old += parts[i].value.length
      next += parts[i].value.length
      continue
    }
    const a = [], b = []
    while (i < parts.length && (parts[i].added || parts[i].removed)) {
      const part = parts[i++]
      if (part.removed) a.push(...part.value)
      else b.push(...part.value)
    }
    i--
    for (let j = 0; j < Math.max(a.length, b.length); j++) {
      const prior = a[j]?.attributes, value = b[j]?.attributes
      const attributes = [...new Set([...Object.keys(prior || {}), ...Object.keys(value || {})])]
        .filter(name => prior?.[name] !== value?.[name])
        .map(name => ({ name, before: prior?.[name], after: value?.[name] }))
      changes.push({ number: (b[j] ? next : old) + j + 1, kind: !a[j] ? 'added' : !b[j] ? 'removed' : 'changed', attributes,
        before: a[j]?.start, after: b[j]?.start, text: value?.alt || prior?.alt || '' })
    }
    old += a.length
    next += b.length
  }
  return changes
}

export function paragraphs(before, after) {
  const parts = diffArrays(before.split(/\r?\n/), after.split(/\r?\n/))
  const rows = []
  for (let index = 0; index < parts.length; index++) {
    const part = parts[index]
    if (!part.added && !part.removed) {
      rows.push(...part.value.map(text => ({ before: text, after: text, equal: true })))
      continue
    }
    const a = [], b = []
    while (index < parts.length && (parts[index].added || parts[index].removed)) {
      const change = parts[index++]
      if (change.removed) a.push(...change.value)
      else b.push(...change.value)
    }
    index--
    rows.push(...align(a, b))
  }
  return rows
}

export function words(before, after) {
  const split = value => [...segments.segment(value)].map(item => item.segment)
  const tokens = diffArrays(split(before), split(after), { timeout: 50 })
  const parts = tokens?.map(part => ({ ...part, value: part.value.join('') })) || [{ value: before, removed: true }, { value: after, added: true }]
  const result = []
  for (let i = 0; i < parts.length; i++) {
    if (!parts[i].added && !parts[i].removed) { result.push(parts[i]); continue }
    const run = []
    while (i < parts.length && (parts[i].added || parts[i].removed)) run.push(parts[i++])
    i--
    const a = run.filter(part => part.removed).map(part => part.value).join('')
    const b = run.filter(part => part.added).map(part => part.value).join('')
    if (a && b && a.length <= 120 && b.length <= 120) {
      const split = value => [...graphemes.segment(value)].map(item => item.segment)
      const old = split(a), next = split(b)
      let start = 0, end = 0
      while (start < Math.min(old.length, next.length) && old[start] === next[start]) start++
      while (end < Math.min(old.length, next.length) - start && old[old.length - end - 1] === next[next.length - end - 1]) end++
      const refined = [
        ...(start ? [{ value: old.slice(0, start) }] : []),
        ...diffArrays(old.slice(start, old.length - end), next.slice(start, next.length - end)),
        ...(end ? [{ value: old.slice(old.length - end) }] : [])
      ]
      const shared = refined.filter(part => !part.added && !part.removed).reduce((sum, part) => sum + part.value.length, 0)
      if (shared >= Math.min(old.length, next.length) / 2) {
        result.push(...refined.map(part => ({ ...part, value: part.value.join('') })))
        continue
      }
    }
    result.push(...run)
  }
  return result
}

function align(before, after) {
  const rows = [], anchors = []
  // Bound similarity matching for large pastes. Unmatched passages retain positional pairing.
  if (before.length * after.length <= 10000 && [...before, ...after].reduce((sum, text) => sum + text.length, 0) <= 100000) {
    const tokens = text => {
      const counts = new Map()
      for (const word of text.toLowerCase().match(/[\p{L}\p{N}]+/gu) || []) counts.set(word, (counts.get(word) || 0) + 1)
      return counts
    }
    const a = before.map(tokens), b = after.map(tokens)
    const size = words => [...words.values()].reduce((sum, count) => sum + count, 0)
    const lengthsA = a.map(size), lengthsB = b.map(size)
    const scores = a.map(words => b.map(other => [...words].reduce((sum, [word, count]) => sum + Math.min(count, other.get(word) || 0), 0)))
    const best = Array.from({ length: a.length + 1 }, () => new Float64Array(b.length + 1))
    for (let i = a.length - 1; i >= 0; i--) {
      for (let j = b.length - 1; j >= 0; j--) {
        const similarity = 2 * scores[i][j] / (lengthsA[i] + lengthsB[j] || 1)
        scores[i][j] = similarity >= 0.3 ? similarity : 0
        best[i][j] = Math.max(best[i + 1][j], best[i][j + 1], scores[i][j] + best[i + 1][j + 1])
      }
    }
    for (let i = 0, j = 0; i < a.length && j < b.length;) {
      if (scores[i][j] && best[i][j] === scores[i][j] + best[i + 1][j + 1]) anchors.push([i++, j++])
      else if (best[i + 1][j] > best[i][j + 1]) i++
      else j++
    }
  }
  let i = 0, j = 0
  for (const [endA, endB] of [...anchors, [before.length, after.length]]) {
    while (i < endA || j < endB) rows.push({ before: i < endA ? before[i++] : null, after: j < endB ? after[j++] : null, equal: false })
    if (i < before.length && j < after.length) rows.push({ before: before[i++], after: after[j++], equal: false })
  }
  return rows
}

export function assets(value, files = {}) {
  const found = new Map()
  const visit = value => {
    if (typeof value === 'string' && files[value]) found.set(files[value].id, files[value])
    else if (Array.isArray(value)) value.forEach(visit)
    else if (value && typeof value === 'object') {
      if (value.type === 'file' && files[value.id]) found.set(files[value.id].id, files[value.id])
      else Object.values(value).forEach(visit)
    }
  }
  visit(value)
  return [...found.values()]
}
