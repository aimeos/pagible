/** @license MIT, https://opensource.org/license/mit */

import equal from 'fast-deep-equal'
import { diffArrays } from 'diff'

const SKIP = ['__proto__', 'constructor', 'prototype']
const SECTIONS = ['meta', 'config', 'content']
const METADATA = ['previews', 'editor']
const graphemes = new Intl.Segmenter(undefined, { granularity: 'grapheme' })
const segments = new Intl.Segmenter(undefined, { granularity: 'word' })
const vacant = value => value == null || (typeof value === 'object' && !Object.keys(value).length)
const same = (a, b) => equal(a, b) || (vacant(a) && vacant(b))
const object = value => value != null && typeof value === 'object' && !Array.isArray(value)
const filedata = file => file && { path: file.path, name: file.name, mime: file.mime, previews: file.previews || {} }
const reference = value => value == null || typeof value === 'string' || value?.type === 'file' || (Array.isArray(value) && value.every(reference))

function fileids(value, files, ids = new Set()) {
  if (typeof value === 'string') {
    if (files?.[value]) ids.add(value)
  } else if (Array.isArray(value)) {
    value.forEach(value => fileids(value, files, ids))
  } else if (value && typeof value === 'object') {
    if (value.type === 'file' && typeof value.id === 'string' && value.id && (!files || files[value.id])) ids.add(value.id)
    else Object.values(value).forEach(value => fileids(value, files, ids))
  }
  return ids
}

// Occurrences keep repeated references distinct; legacy blocks without IDs use their position.
function indexed(items = []) {
  const counts = new Map()

  return items.map((item, index) => {
    const id = item?.id ? ['id', item.id] : item?.refid ? ['ref', item.refid] : ['index', index]
    const base = JSON.stringify(id)
    const count = counts.get(base) || 0
    counts.set(base, count + 1)
    return { key: JSON.stringify([...id, count]), item, index }
  })
}

// The longest increasing subsequence identifies unchanged relative order in O(n log n).
function stationary(before, after) {
  const positions = new Map(before.map((entry, index) => [entry.key, index]))
  const shared = after.filter(entry => positions.has(entry.key))
  const tails = [], previous = []

  shared.forEach((entry, index) => {
    let lo = 0, hi = tails.length
    const position = positions.get(entry.key)

    while (lo < hi) {
      const mid = (lo + hi) >> 1
      if (positions.get(shared[tails[mid]].key) < position) lo = mid + 1
      else hi = mid
    }

    previous[index] = lo ? tails[lo - 1] : -1
    tails[lo] = index
  })

  const keys = new Set()
  for (let index = tails.at(-1); index != null && index >= 0; index = previous[index]) {
    keys.add(shared[index].key)
  }
  return keys
}

export function fields(before = {}, after = {}, path = []) {
  const result = []

  for (const name of new Set([...Object.keys(before || {}), ...Object.keys(after || {})])) {
    if (SKIP.includes(name)) continue
    if (name === 'files' && (before?.type || after?.type) && (before?.data || after?.data)) continue

    const a = before?.[name], b = after?.[name]
    if (same(a, b)) continue

    const next = [...path, name]
    if ((object(a) || a == null) && (object(b) || b == null) && a?.type !== 'file' && b?.type !== 'file') {
      result.push(...fields(a, b, next))
    } else {
      result.push({ key: JSON.stringify(next), path: next, before: a, after: b })
    }
  }
  return result
}

export function blocks(before = [], after = []) {
  const a = indexed(before), b = indexed(after)
  const aMap = new Map(a.map(entry => [entry.key, entry]))
  const bMap = new Map(b.map(entry => [entry.key, entry]))
  const fixed = stationary(a, b)
  const result = []

  for (const key of new Set([...aMap.keys(), ...bMap.keys()])) {
    const old = aMap.get(key), next = bMap.get(key)
    const moved = !!old && !!next && !fixed.has(key)
    const changes = fields(old?.item, next?.item).filter(field => field.path[0] !== 'id')
      .map(field => ({ ...field, key: JSON.stringify(['content', key, ...field.path]) }))
    if (old && next && !moved && !changes.length) continue

    const entry = {
      key: `content:${key}`, id: key,
      before: old?.item, after: next?.item,
      from: old?.index, to: next?.index, moved,
      kind: !old ? 'added' : !next ? 'removed' : 'changed',
      fields: moved ? [{ key: `content:${key}:move`, before: old.index + 1, after: next.index + 1, position: true }, ...changes] : changes
    }
    entry.keys = entry.kind === 'changed' ? entry.fields.map(field => field.key) : [entry.key]
    result.push(entry)
  }
  return result
}

export function sections(before = {}, after = {}) {
  const result = {}
  const select = data => Object.fromEntries(Object.entries(data).filter(([key]) => !SECTIONS.includes(key) && !METADATA.includes(key)))
  const data = fields(select(before), select(after))
  if (data.length) result.data = data

  for (const section of ['meta', 'config']) {
    const changes = fields(before[section], after[section], [section])
    if (changes.length) result[section] = changes
  }

  const content = blocks(before.content, after.content)
  if (content.length) result.content = content
  return result
}

export function tableRows(before = [], after = [], limit = 9) {
  const parts = diffArrays(before, after, { comparator: equal, timeout: 50 })
    || [{ value: before, removed: true }, { value: after, added: true }]
  const rows = []

  for (let i = 0; i < parts.length; i++) {
    if (!parts[i].added && !parts[i].removed) {
      parts[i].value.forEach(row => rows.push({ before: row, after: row }))
      continue
    }

    const changed = []
    while (i < parts.length && (parts[i].added || parts[i].removed)) changed.push(parts[i++])
    i--
    const removed = changed.filter(part => part.removed).flatMap(part => part.value)
    const added = changed.filter(part => part.added).flatMap(part => part.value)
    for (let index = 0; index < Math.max(removed.length, added.length); index++) {
      rows.push({ before: removed[index], after: added[index] })
    }
  }

  const context = new Set()
  if (rows.length) context.add(0)
  rows.forEach((row, index) => {
    if (equal(row.before, row.after)) return
    for (let pos = Math.max(0, index - 1); pos <= Math.min(rows.length - 1, index + 1); pos++) context.add(pos)
  })
  let indices = [...context]
  if (indices.length > limit) indices = [...indices.slice(0, Math.ceil(limit / 2)), ...indices.slice(-Math.floor(limit / 2))]

  const result = []
  let previous = -1
  for (const index of indices) {
    if (index > previous + 1) result.push({ skip: index - previous - 1 })
    result.push(rows[index])
    previous = index
  }
  if (previous < rows.length - 1) result.push({ skip: rows.length - previous - 1 })
  return result
}

export function filepairs(before = [], after = []) {
  // A single media field can replace one file with another ID.
  if (before.length === 1 && after.length === 1 && before[0].id !== after[0].id) {
    return [{ key: 'replacement', before: before[0], after: after[0], kind: 'changed', moved: false }]
  }
  const a = indexed(before), b = indexed(after)
  const old = new Map(a.map(entry => [entry.key, entry]))
  const next = new Map(b.map(entry => [entry.key, entry]))
  const fixed = stationary(a, b)
  const order = b.map(entry => entry.key)
  // Keep removals next to their surviving neighbours in the comparison.
  for (let i = a.length - 1; i >= 0; i--) {
    if (next.has(a[i].key)) continue
    const neighbour = a.slice(i + 1).find(entry => order.includes(entry.key))
    order.splice(neighbour ? order.indexOf(neighbour.key) : order.length, 0, a[i].key)
  }
  return order.map(key => {
    const from = old.get(key), to = next.get(key)
    return {
      key, before: from?.item, after: to?.item, from: from?.index, to: to?.index,
      moved: !!from && !!to && !fixed.has(key),
      kind: !from ? 'added' : !to ? 'removed' : equal(filedata(from.item), filedata(to.item)) ? 'unchanged' : 'changed'
    }
  })
}

export function references(data) {
  return [...fileids(data)]
}

function set(target, path, value) {
  const [key, ...rest] = path
  if (!key || SKIP.includes(key)) return

  if (rest.length) {
    target[key] = { ...target[key] }
    set(target[key], rest, value)
  } else if (value === undefined) {
    delete target[key]
  } else {
    target[key] = value
  }
}

function content(current, target, selected, checked) {
  const desired = indexed(target)
  const targetMap = new Map(desired.map(entry => [entry.key, entry]))
  const selectedMap = new Map(selected.map(entry => [entry.id, entry]))
  const position = new Set(selected.filter(entry => entry.kind !== 'changed' || entry.fields.some(field => field.position && checked(field.key))).map(entry => entry.id))
  const replacements = new Map()
  const result = indexed(current).flatMap(entry => {
    const change = selectedMap.get(entry.key)
    if (!change) return [entry]

    if (change.kind === 'changed') {
      const item = { ...entry.item }
      for (const field of change.fields) {
        if (!field.position && checked(field.key)) {
          const value = field.path.reduce((value, key) => value?.[key], targetMap.get(entry.key)?.item)
          set(item, field.path, value)
        }
      }
      if (item.data !== entry.item.data && ('files' in item || references(item.data).length)) item.files = references(item.data)
      replacements.set(entry.key, { ...entry, item })
    }
    if (position.has(entry.key) || !targetMap.has(entry.key)) return []
    return [replacements.get(entry.key) || targetMap.get(entry.key)]
  })

  // Insert relative to surviving target neighbours, preserving all unselected blocks.
  for (let index = desired.length - 1; index >= 0; index--) {
    const entry = desired[index]
    if (!position.has(entry.key)) continue

    const next = desired.slice(index + 1).find(item => result.some(current => current.key === item.key))
    const prev = desired.slice(0, index).reverse().find(item => result.some(current => current.key === item.key))
    const at = next ? result.findIndex(item => item.key === next.key)
      : prev ? result.findIndex(item => item.key === prev.key) + 1 : Math.min(index, result.length)
    result.splice(at, 0, replacements.get(entry.key) || entry)
  }
  return result.map(entry => entry.item)
}

export function restore(current, target, diffs, checked) {
  const merged = { ...current }
  const roots = new Set()

  for (const [section, entries] of Object.entries(diffs)) {
    const selected = entries.filter(entry => section === 'content' ? entry.keys.some(checked) : checked(entry.key))
    if (section === 'content') {
      if (selected.length) {
        merged.content = content(current.content || [], target.content || [], selected, checked)
        roots.add('content')
      }
      continue
    }

    for (const entry of selected) {
      const value = entry.path.reduce((value, key) => value?.[key], target)
      set(merged, entry.path, value)
      roots.add(entry.path[0])
    }
  }

  if (roots.has('path')) {
    merged.previews = target.previews
    roots.add('previews')
  }

  for (const section of ['meta', 'config']) {
    if (!roots.has(section)) continue
    for (const [key, entry] of Object.entries(merged[section] || {})) {
      if (entry?.data && entry.type) merged[section][key] = { ...entry, files: references(entry.data) }
    }
  }

  // Root nulls survive JSON serialization; nested removed properties are deleted by set().
  return Object.fromEntries([...roots].map(key => [key, merged[key] ?? null]))
}

export function plaintext(value) {
  const text = value == null ? '' : String(value)
  if (!/<\/?[a-z][^>]*>/i.test(text)) return text

  const template = document.createElement('template')
  template.innerHTML = text
  template.content.querySelectorAll('script, style').forEach(node => node.remove())
  return Array.from(template.content.childNodes, node => node.textContent).join(' ')
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

export function filechanges(before = {}, after = {}, diffs = {}) {
  const files = { before: before.files || {}, after: after.files || {} }, changes = []
  for (const id of new Set([...Object.keys(files.before), ...Object.keys(files.after)])) {
    if (equal(filedata(files.before[id]), filedata(files.after[id]))) continue
    for (const side of ['before', 'after']) if (files[side][id]) changes.push({ key: `${side}:${id}`, side, file: files[side][id] })
  }

  const used = new Set()
  for (const [section, entries] of Object.entries(diffs)) {
    const fields = section === 'content' ? entries.flatMap(block => block.fields) : entries
    for (const field of fields) {
      field.media = Object.fromEntries(['before', 'after'].map(side => [side, reference(field.before) && reference(field.after)
        ? [...fileids(field[side], files[side])].map(id => files[side][id]) : []]))
      for (const side of ['before', 'after']) field.media[side].forEach(file => used.add(`${side}:${file.id}`))
    }
  }

  const remaining = changes.filter(entry => !used.has(entry.key))
  return {
    count: new Set(changes.map(entry => entry.file.id)).size,
    remaining: Object.fromEntries(['before', 'after'].map(side => [side, remaining.filter(entry => entry.side === side).map(entry => entry.file)]))
  }
}
