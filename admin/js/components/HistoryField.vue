/** @license MIT, https://opensource.org/license/mit */

<script>
import { assets, decorate, describe, formatting, images, paragraphs, structure, words } from '../historytext'
import { items } from '../history'
import equal from 'fast-deep-equal'
import HistoryLine from './HistoryLine.vue'
import HistoryMedia from './HistoryMedia.vue'
import { stringify } from '../utils'

export default {
  components: { HistoryLine, HistoryMedia },
  props: {
    field: { type: Object, required: true },
    beforeLabel: { type: String, required: true },
    afterLabel: { type: String, required: true },
    side: { type: String, default: null },
    rawDetails: { type: Boolean, default: true },
    schema: { type: Object, default: () => ({}) },
    before: { type: Object, default: () => ({}) },
    after: { type: Object, default: () => ({}) }
  },

  data: () => ({ context: {}, rawOpen: false }),

  computed: {
    comparison() {
      const before = this.format(this.field.before, false)
      const after = this.format(this.field.after, true)
      const aligned = paragraphs(before.text, after.text)
      const formats = formatting(before, after, aligned), structures = structure(before, after, aligned)
      const imageChanges = images(before, after)
      const shown = new Set()
      const overlap = (range, start, text) => text !== null && range && range[0] < start + text.length && range[1] > start
      const list = (value, start, text) => {
        const item = text !== null && value.lists.find(item => item.start < start + text.length && item.end > start)
        if (!item) return null
        const { start: offset, end, ...result } = item
        return item.first && offset >= start ? { ...result, first: true } : { depth: item.depth, first: false }
      }
      let a = 0, b = 0
      const rows = aligned.map((row, key) => {
        const inline = formats.filter(change => change.side === 'before' ? overlap([change.start, change.end], a, row.before) : overlap([change.start, change.end], b, row.after))
        const layout = structures.filter(change => change.spans.some(span => overlap(span.before, a, row.before) || overlap(span.after, b, row.after)))
        const beforeList = list(before, a, row.before), afterList = list(after, b, row.after)
        const listChanged = !equal(beforeList, afterList)
        const same = row.equal && !inline.length && !layout.length && !listChanged
        const fresh = changes => changes.filter(change => !shown.has(change)).map(change => { shown.add(change); return change })
        const parts = words(row.before ?? '', row.after ?? '')
        const beforeWords = decorate(parts.filter(word => !word.added).map(word => ({ value: word.value, highlight: !!word.removed })), before.marks, inline.filter(mark => mark.side === 'before'), a)
        const afterWords = decorate(parts.filter(word => !word.removed).map(word => ({ value: word.value, highlight: !!word.added })), after.marks, inline.filter(mark => mark.side === 'after'), b)
        const starts = { before: a, after: b }
        if (row.before !== null) a += row.before.length + 1
        if (row.after !== null) b += row.after.length + 1
        return { ...row, equal: same, key: 'row:' + key, beforeList, afterList, listChanged, starts, imagesBefore: [], imagesAfter: [],
          formatting: fresh(inline), structure: fresh(layout),
          beforeWords, afterWords,
          context: !row.equal && !inline.length && !layout.length && parts.some(word => !word.added && !word.removed && word.value.length > 180)
        }
      })
      if (!this.side) imageChanges.forEach((image, index) => {
        const side = image.after === undefined ? 'before' : 'after', position = image[side]
        const candidates = rows.filter(row => row[side] !== null)
        const row = candidates.find(row => row.starts[side] <= position && row.starts[side] + row[side].length >= position) || candidates.at(-1) || rows[0]
        row[position <= row.starts[side] ? 'imagesBefore' : 'imagesAfter'].push({ key: 'image:' + index, image })
      })

      return {
        before, after,
        rows,
        source: !equal(this.field.before, this.field.after) && before.placeholder === after.placeholder && !imageChanges.length && rows.every(row => row.equal),
        raw: this.field.path.at(-1) === 'refid' || before.html || after.html || typeof this.field.before === 'object' || typeof this.field.after === 'object'
          || before.text === after.text,
      }
    },

    isCompact() {
      if (this.side || this.media.before.length || this.media.after.length || ['text', 'html', 'markdown', 'plaintext'].includes(this.schema.type)) return false
      return [this.field.before, this.field.after].every(value => value == null ||
        (typeof value !== 'object' && String(value).length <= 90 && !/[\n\r]|<\/?[a-z]/i.test(String(value))))
    },

    media() {
      const reference = value => value == null || typeof value === 'string' || value?.type === 'file' || (Array.isArray(value) && value.every(reference))
      return reference(this.field.before) && reference(this.field.after)
        ? { before: assets(this.field.before, this.before.files), after: assets(this.field.after, this.after.files) }
        : { before: [], after: [] }
    },

    passages() {
      const rows = this.comparison.rows.flatMap(row => [...row.imagesBefore, row, ...row.imagesAfter])
      const result = []
      for (let i = 0; i < rows.length; i++) {
        const start = i
        if (rows[i].image) { result.push(rows[i]); continue }
        if (rows[i].equal) while (i + 1 < rows.length && rows[i + 1].equal && !rows[i + 1].image) i++
        const run = rows.slice(start, i + 1)
        if (run.length > 3 && run.length !== rows.length) {
          result.push({ key: run[0].key, rows: [run[0]] })
          result.push({ key: 'gap:' + start, rows: run.slice(1, -1), hidden: true })
          result.push({ key: run.at(-1).key, rows: [run.at(-1)] })
        } else {
          result.push({ key: run[0].key, rows: run })
        }
      }
      return result
    },

    rawDiff() {
      const raw = value => typeof this.field.before !== typeof this.field.after && typeof value === 'string' ? JSON.stringify(value) : this.raw(value)
      const before = raw(this.field.before), after = raw(this.field.after)
      if (!this.rawOpen) return { before: [{ value: before }], after: [{ value: after }] }
      const parts = words(before, after)
      return { before: parts.filter(part => !part.added), after: parts.filter(part => !part.removed) }
    },

    rows() {
      const a = this.field.before, b = this.field.after
      return (Array.isArray(a) || Array.isArray(b)) && (a == null || Array.isArray(a)) && (b == null || Array.isArray(b))
        ? items(a || [], b || [], this.schema.identity || 'id') : null
    }
  },

  methods: {
    compact(words, row) {
      if (!row.context || this.context[row.key]) return words

      return words.map((word, index) => {
        if (word.highlight || word.value.length <= 180) return word
        const start = index === 0 && words.length > 1 ? '' : word.value.slice(0, 80)
        const end = index === words.length - 1 && words.length > 1 ? '' : word.value.slice(-80)
        return { ...word, value: start + ' … ' + end }
      })
    },

    format(value, after) {
      let text = stringify(value)
      let placeholder = null
      const name = this.field.path.at(-1)
      const parsed = describe(value, this.schema.type)
      const html = parsed.html

      if (value === undefined) placeholder = after ? this.$gettext('Removed') : this.$gettext('Not present')
      else if (value === null || value === '') placeholder = this.$gettext('Empty')
      else if (name === 'refid') {
        const element = Object.values((after ? this.after : this.before).elements || {}).find(element => element.id === value)
        text = element?.name || this.$gettext('Unavailable shared element')
      } else if (typeof value === 'boolean' || (name === 'scheduled' && this.field.path.length === 1)) {
        text = value ? this.$gettext('Enabled') : this.$gettext('Disabled')
      } else if (name === 'status' && this.field.path.length === 1) {
        text = { 0: this.$gettext('Disabled'), 1: this.$gettext('Enabled'), 2: this.$gettext('Hidden in navigation') }[value] ?? text
      } else if (typeof value === 'string' && (this.schema.type === 'date' || (!this.schema.type && /(?:_at|date)$/.test(name)))
        && /^\d{4}-\d\d-\d\d(?:T| |$)/.test(value) && !isNaN(Date.parse(value))) {
        text = value.length === 10 ? new Date(value + 'T00:00:00').toLocaleDateString(this.$vuetify.locale.current)
          : new Date(value).toLocaleString(this.$vuetify.locale.current)
      } else if (html) {
        text = parsed.text
        if (!text) placeholder = this.$gettext('Empty')
      } else if (Array.isArray(value) && value.every(item => item == null || typeof item !== 'object')) {
        text = value.map(item => stringify(item)).join('\n')
        if (!text) placeholder = this.$gettext('Empty')
      }

      const option = this.schema.options?.find(option => option.value === value)
      if (option) { text = this.$pgettext('op', option.label); placeholder = null }
      if (name === 'group') text = this.$pgettext('sg', text)
      return { ...parsed, text: placeholder ? '' : text, html, placeholder, tokens: decorate([{ value: placeholder ? '' : text }], parsed.marks, []) }
    },

    formatLabel(change) {
      const names = { bold: this.$gettext('Bold'), italic: this.$gettext('Italic'), underline: this.$gettext('Underline'), strike: this.$gettext('Strikethrough'), code: this.$gettext('Code') }
      const text = change.text.length > 80 ? change.text.slice(0, 80) + '…' : change.text
      if (change.kind === 'link') return this.$gettext('Link destination for “%{text}”', { text })
      return change.action === 'added' ? this.$gettext('%{format} added to “%{text}”', { format: names[change.kind], text })
        : this.$gettext('%{format} removed from “%{text}”', { format: names[change.kind], text })
    },

    imageAttribute(name) {
      return { src: this.$gettext('Image source'), srcset: this.$gettext('Image sources'), alt: this.$gettext('Alternative text'), title: this.$gettext('Title'),
        width: this.$gettext('Width'), height: this.$gettext('Height'), loading: this.$gettext('Loading'), style: this.$gettext('Style') }[name] || name
    },

    imageLabel(change) {
      const label = change.kind === 'added' ? this.$gettext('Image %{num} added', { num: change.number })
        : change.kind === 'removed' ? this.$gettext('Image %{num} removed', { num: change.number }) : this.$gettext('Image %{num} changed', { num: change.number })
      return label + (change.text ? ': ' + change.text.slice(0, 80) : '')
    },

    itemLabel(row) {
      const item = row.after || row.before
      const text = describe(item.title || item.name || item.value || '').text.replace(/\s+/g, ' ').slice(0, 60)
      return this.$gettext('Item %{num}', { num: (row.to ?? row.from) + 1 }) + (text ? ': ' + text : '')
    },

    label(field) {
      return field.path.map((key, index) => this.$pgettext('fn', (index === 0 && this.schema.item?.[key]?.label) || key)).join(' › ')
    },

    raw(value) {
      return value === undefined ? this.$gettext('Not present') : typeof value === 'string' ? value : JSON.stringify(value, null, 2)
    },

    space(word) {
      if (!(word.highlight || word.added || word.removed) || !/^[ \t\r\n\u00a0]+$/.test(word.value)) return undefined
      return word.value.replace(/[ \t\r\n\u00a0]/g, value => ({ ' ': '·', '\t': '⇥', '\r': '␍', '\n': '↵\n', '\u00a0': '⍽' })[value])
    },

    spaceLabel(word) {
      if (!this.space(word)) return undefined
      const names = { ' ': this.$gettext('Space'), '\t': this.$gettext('Tab'), '\r': this.$gettext('Carriage return'), '\n': this.$gettext('Line break'), '\u00a0': this.$gettext('Non-breaking space') }
      return [...new Set(word.value)].map(value => this.$gettext('%{name} × %{num}', { name: names[value], num: word.value.split(value).length - 1 })).join(', ')
    },

    structureLabel(types) {
      const names = { P: this.$gettext('Paragraph'), UL: this.$gettext('Bulleted list'), OL: this.$gettext('Numbered list'), BLOCKQUOTE: this.$gettext('Quote'), PRE: this.$gettext('Preformatted text') }
      return types.map(type => /^H[1-6]$/.test(type) ? this.$gettext('Heading %{num}', { num: type.slice(1) }) : names[type]).join(' › ')
    }
  },

  watch: {
    field() { this.context = {} }
  }
}
</script>

<template>
  <div class="field-comparison" :class="{ 'is-compact': isCompact, 'is-snapshot': side }">
    <HistoryMedia v-if="media.before.length || media.after.length" :before="media.before" :after="media.after" :before-label="beforeLabel" :after-label="afterLabel" :side="side" />
    <template v-else-if="rows">
      <div v-for="row in rows" :key="row.key" class="array-item">
        <div class="item-heading">
          <span>{{ itemLabel(row) }}</span>
          <v-chip v-if="!side && row.kind === 'added'" size="x-small" color="success" label>{{ $gettext('Item added') }}</v-chip>
          <v-chip v-else-if="!side && row.kind === 'removed'" size="x-small" color="error" label>{{ $gettext('Item removed') }}</v-chip>
          <span v-if="!side && row.moved">{{ $gettext('Moved from position %{from} to %{to}', { from: row.from + 1, to: row.to + 1 }) }}</span>
        </div>
        <div v-for="entry in row.fields" :key="entry.key" class="item-field">
          <span v-if="entry.path[0] !== 'value'" class="item-label">{{ label(entry) }}</span>
          <HistoryField :field="entry" :schema="schema.item?.[entry.path[0]]"
            :before-label="beforeLabel" :after-label="afterLabel" :before="before" :after="after"
            :side="side" :raw-details="rawDetails"
          />
        </div>
      </div>
    </template>
    <div v-else-if="side && !comparison[side].lists.length" class="snapshot-value">
      <span v-if="comparison[side].placeholder" class="value-placeholder">{{ comparison[side].placeholder }}</span>
      <template v-else><span v-for="(word, index) in comparison[side].tokens" :key="index" :class="word.kinds.map(kind => 'format-' + kind)">{{ word.value }}</span></template>
    </div>
    <template v-else>
      <div v-if="!side && comparison.rows.length > 1 && comparison.rows.some(row => !row.equal)" class="diff-columns paragraph-labels" aria-hidden="true">
        <div class="side-label">{{ beforeLabel }}</div>
        <div class="side-label">{{ afterLabel }}</div>
      </div>
      <template v-for="passage in passages" :key="passage.key">
        <div v-if="passage.image" class="image-change">
          <p>{{ imageLabel(passage.image) }}</p>
          <dl><div v-for="attribute in passage.image.attributes" :key="attribute.name">
            <dt>{{ imageAttribute(attribute.name) }}</dt>
            <dd><span :aria-label="beforeLabel">{{ attribute.before ?? $gettext('Not present') }}</span> → <span :aria-label="afterLabel">{{ attribute.after ?? $gettext('Removed') }}</span></dd>
          </div></dl>
        </div>
        <template v-else>
          <v-btn v-if="passage.hidden" class="context-gap" variant="text" size="small" :aria-expanded="!!context[passage.key]" @click="context[passage.key] = !context[passage.key]">
            {{ context[passage.key] ? $gettext('Hide unchanged paragraphs') : $ngettext('%{num} unchanged paragraph', '%{num} unchanged paragraphs', passage.rows.length, { num: passage.rows.length }) }}
          </v-btn>
          <template v-if="!passage.hidden || context[passage.key]">
            <div v-for="row in passage.rows" :key="row.key" class="change-passage">
              <HistoryLine v-if="side" class="snapshot-value" :list="row[side + 'List']"><span v-for="(word, index) in row[side + 'Words']" :key="index" :class="word.kinds.map(kind => 'format-' + kind)">{{ word.value }}</span></HistoryLine>
              <div v-else-if="row.equal && comparison.before.placeholder === comparison.after.placeholder" class="shared-context">
                <div v-if="row === passage.rows[0]" class="side-label">{{ $gettext('Unchanged') }}</div>
                <span v-if="comparison.after.placeholder" class="value-placeholder">{{ comparison.after.placeholder }}</span>
                <HistoryLine v-else class="diff-text" :list="row.afterList"><span v-for="(word, index) in row.afterWords" :key="index" :class="word.kinds.map(kind => 'format-' + kind)">{{ word.value }}</span></HistoryLine>
              </div>
              <div v-else :class="[isCompact ? 'scalar-values' : 'diff-columns paragraph-row', { unchanged: row.equal }]">
                <template v-for="(position, index) in ['before', 'after']" :key="position">
                  <span v-if="isCompact && index" class="scalar-arrow" aria-hidden="true">→</span>
                  <div class="diff-side" :class="[index ? 'change-new' : 'change-old', { 'empty-paragraph': row[position] === null, 'is-placeholder': comparison[position].placeholder }]" :aria-label="index ? afterLabel : beforeLabel">
                    <div class="side-label" :class="{ repeated: comparison.rows.length > 1 }"><span aria-hidden="true">{{ index ? '+' : '−' }}</span> {{ index ? afterLabel : beforeLabel }}</div>
                    <HistoryLine class="diff-text" :list="row[position + 'List']" :changed="row.listChanged">
                      <span v-if="row[position] !== null && comparison[position].placeholder" class="value-placeholder">{{ comparison[position].placeholder }}</span>
                      <span v-else-if="row[position] === '' && row[index ? 'before' : 'after'] === null" class="highlight whitespace" data-space="↵" :aria-label="index ? $gettext('Line break added') : $gettext('Line break removed')" :title="index ? $gettext('Line break added') : $gettext('Line break removed')"></span>
                      <template v-else><span v-for="(word, index) in compact(row[position + 'Words'], row)" :key="index" :class="{ highlight: word.highlight, 'format-change': word.formatting, whitespace: space(word) }" :data-space="space(word)" :title="spaceLabel(word)" :aria-label="spaceLabel(word)"><span :class="word.kinds.map(kind => 'format-' + kind)">{{ word.value }}</span></span></template>
                    </HistoryLine>
                  </div>
                </template>
              </div>
              <v-btn v-if="!side && row.context" class="context-text" variant="text" size="small" :aria-expanded="!!context[row.key]" @click="context[row.key] = !context[row.key]">
                {{ context[row.key] ? $gettext('Hide unchanged text') : $gettext('Show unchanged text') }}
              </v-btn>
              <ul v-if="!side && row.formatting.length" class="formatting-changes">
                <li v-for="(change, index) in row.formatting" :key="index">
                  {{ formatLabel(change) }}
                  <span v-if="change.kind === 'link'" class="link-change">
                    <span class="change-old">{{ change.before ?? $gettext('Not present') }}</span>
                    <span aria-hidden="true"> → </span>
                    <span class="change-new">{{ change.after ?? $gettext('Removed') }}</span>
                  </span>
                </li>
              </ul>
              <ul v-if="!side && row.structure.length" class="formatting-changes structure-changes">
                <li v-for="(change, index) in row.structure" :key="index">
                  {{ structureLabel(change.before) }} → {{ structureLabel(change.after) }}
                </li>
              </ul>
            </div>
          </template>
        </template>
      </template>
      <div v-if="!side && comparison.source" class="source-note">{{ comparison.before.html || comparison.after.html ? $gettext('Source changed; displayed text is unchanged') : $gettext('Stored value changed; displayed value is unchanged') }}</div>
    </template>
    <details v-if="rawDetails && (rows || comparison.raw)" class="raw-details" @toggle="rawOpen = $event.target.open">
      <summary>{{ $gettext('Show raw details') }}</summary>
      <div class="diff-columns">
        <div v-for="(position, sideIndex) in ['before', 'after']" :key="position" :class="sideIndex ? 'change-new' : 'change-old'"><div class="side-label">{{ sideIndex ? afterLabel : beforeLabel }}</div><pre><span v-for="(part, index) in rawDiff[position]" :key="index" :class="{ highlight: part[sideIndex ? 'added' : 'removed'], whitespace: space(part) }" :data-space="space(part)" :title="spaceLabel(part)" :aria-label="spaceLabel(part)"><span>{{ part.value }}</span></span></pre></div>
      </div>
    </details>
  </div>
</template>

<style scoped>
.diff-side.is-placeholder {
  background: transparent;
}

.value-placeholder {
  display: inline-block;
  padding: 1px 6px;
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  font-size: 0.8rem;
}

.snapshot-value {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.scalar-values {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
}

.scalar-values .diff-side {
  padding: 2px 5px;
  max-width: 100%;
}

.scalar-values .side-label {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
}

.scalar-arrow {
  opacity: 0.7;
}

.formatting-changes {
  padding-left: 20px;
  margin-top: 8px;
  font-size: 0.875rem;
}

.link-change {
  display: block;
  overflow-wrap: anywhere;
}

.array-item {
  margin-bottom: 12px;
  padding: 10px;
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.item-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  margin-bottom: 8px;
}

.item-field {
  margin-top: 8px;
}

.item-label {
  display: block;
  font-size: 0.8rem;
  margin-bottom: 4px;
}

.field-comparison {
  min-width: 0;
}

.diff-columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
}

.diff-side {
  min-width: 0;
  border-radius: 4px;
  padding: 8px 10px;
}

.side-label {
  font-size: 0.75rem;
  font-weight: 600;
  margin-bottom: 4px;
}

.change-passage + .change-passage {
  margin-top: 6px;
}

.paragraph-labels .side-label {
  padding: 0 10px;
}

.paragraph-row .side-label.repeated {
  display: none;
}

.paragraph-row .diff-side {
  padding: 4px 10px;
}

.shared-context {
  padding: 4px 10px;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.diff-side.empty-paragraph {
  background: transparent;
}

.empty-paragraph .side-label {
  visibility: hidden;
}

.context-gap {
  display: flex;
  margin: 8px auto;
}

.diff-text, pre {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  line-height: 1.6;
}

.change-old {
  background: rgba(var(--v-theme-error), 0.06);
}

.change-new {
  background: rgba(var(--v-theme-success), 0.06);
}

.change-old .highlight {
  background: rgba(var(--v-theme-error), 0.25);
  text-decoration: line-through;
}

.change-new .highlight {
  background: rgba(var(--v-theme-success), 0.25);
  text-decoration: underline;
}

.format-change {
  background: rgba(var(--v-theme-info), 0.15);
  box-shadow: 0 1px rgb(var(--v-theme-info));
}

.format-bold { font-weight: 700; }
.format-italic { font-style: italic; }
.format-underline, .format-link { text-decoration: underline; }
.format-strike { text-decoration: line-through; }
.format-underline.format-strike { text-decoration: underline line-through; }
.format-code { font-family: monospace; }

.whitespace::before {
  content: attr(data-space);
  font-family: monospace;
  white-space: pre-wrap;
}

.whitespace > span {
  font-size: 0;
  line-height: 0;
  white-space: normal;
}

.source-note, .image-change {
  font-size: 0.85rem;
  margin-top: 8px;
}

.image-change dt {
  font-weight: 600;
}

.image-change dd {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.raw-details .change-old, .raw-details .change-new {
  padding: 8px;
  border-radius: 4px;
}

.raw-details {
  margin-top: 8px;
  font-size: 0.85rem;
}

.raw-details summary {
  cursor: pointer;
  padding: 6px 0;
}

pre {
  margin: 0;
}

@media (max-width: 700px) {
  .paragraph-labels {
    display: none;
  }

  .paragraph-row .side-label.repeated {
    display: block;
  }

  .diff-columns {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
  }

}
</style>
