/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiChevronDown, mdiChevronUp, mdiClose, mdiDotsVertical } from '@mdi/js'
import { useSchemaStore } from '../stores'
import { assets, describe } from '../historytext'
import { choices, media, restore, sections } from '../history'
import HistoryField from './HistoryField.vue'
import HistoryMedia from './HistoryMedia.vue'

export default {
  components: { HistoryField, HistoryMedia },

  props: {
    modelValue: { type: Boolean, required: true },
    readonly: { type: Boolean, default: false },
    current: { type: Object, default: null },
    load: { type: Function, required: true }
  },

  emits: ['update:modelValue', 'apply', 'use', 'revert'],

  setup() {
    return { schemas: useSchemaStore(), mdiChevronDown, mdiChevronUp, mdiClose, mdiDotsVertical, choices }
  },

  data: () => ({
    versions: [],
    loading: false,
    failed: false,
    mode: 'restore',
    onlySelected: false,
    opened: null,
    collapsedBlocks: {},
    cursor: {},
    unchecked: {},
    request: 0
  }),

  computed: {
    active() {
      return this.cards.find(card => this.opened === card.key)
    },

    beforeLabel() {
      return this.mode === 'save' ? this.$gettext('Previous saved value') : this.$gettext('Current value')
    },

    cards() {
      const latest = this.versions[0]
      if (!latest) return []

      const current = this.current || latest
      return this.versions.map((version, index) => {
        const before = this.mode === 'save' ? this.versions[index + 1] : current
        const diffs = before ? sections(before.data || {}, version.data || {}) : {}
        const files = before ? media(before.files || {}, version.files || {}) : []
        const keys = Object.entries(diffs).flatMap(([section, entries]) => section === 'content' ? entries.flatMap(choices) : entries.map(entry => entry.key))
        return { key: `version:${version.id || index}`, version, before: before || {}, after: version, diffs, files, keys,
          unavailable: !before, unsaved: this.mode === 'restore' && index === 0 && !!(keys.length || files.length) }
      })
    },

    labels() {
      return {
        data: this.$gettext('Fields'), meta: this.$gettext('Meta data'),
        config: this.$gettext('Configuration'), content: this.$gettext('Content')
      }
    },

    navigation() {
      const card = this.active
      if (!card) return []
      const entries = Object.entries(this.visible(card)).flatMap(([section, entries]) => section === 'content'
        ? entries.flatMap(block => (block.kind === 'changed'
          ? [...(block.moved ? [block.moveKey] : []), ...block.fields.map(field => field.key)] : [block.key])
          .filter(key => !this.onlySelected || !this.selectable || this.checked(card, key)).map(key => ({ key, block,
            label: (key === block.moveKey ? this.$gettext('Block position') : block.kind === 'changed'
              ? this.fieldLabel(card, block.fields.find(field => field.key === key), section, block) : this.$gettext('Content')) + ' · ' + this.blockLabel(card, block) })))
        : entries.map(field => ({ key: field.key, label: this.fieldLabel(card, field, section) + ' · ' + this.labels[section] })))
      if (this.remaining.length) entries.push({ key: 'media', label: this.$gettext('Media') })
      return entries.map((entry, index) => ({ ...entry, number: index + 1, total: entries.length }))
    },

    position() {
      return Math.max(0, this.navigation.findIndex(entry => entry.key === this.cursor[this.active?.key]))
    },

    remaining() {
      const card = this.active
      if (!card || (this.onlySelected && this.selectable)) return []
      const used = new Set()
      for (const [section, entries] of Object.entries(card.diffs)) {
        for (const field of section === 'content' ? entries.flatMap(block => block.fields) : entries) {
          for (const side of ['before', 'after']) {
            assets(field[side], card[side].files).forEach(file => used.add(`${side}:${file.id}`))
          }
        }
      }
      return card.files.filter(entry => !used.has(`${entry.side}:${entry.file.id}`))
    },

    selectable() {
      return !this.readonly && this.mode === 'restore'
    }
  },

  beforeUnmount() {
    this.request++
  },

  methods: {
    afterLabel(card, key) {
      if (this.mode === 'save') return this.$gettext('Value in this save')
      return this.selectable && key && this.checked(card, key) ? this.$gettext('Value after restoration') : this.$gettext('Saved value')
    },

    apply(card) {
      if (!this.selectable || !this.selected(card)) return
      const changes = restore(card.before.data || {}, card.after.data || {}, card.diffs, key => this.checked(card, key))
      this.$emit('apply', changes, card.version)
    },

    blockFields(block) {
      return block.kind === 'changed' ? block.fields : block.fields.filter(field => !['type', 'refid', 'group'].includes(field.path[0]))
    },

    blockLabel(card, block) {
      const item = block.after || block.before
      const element = Object.values((block.after ? card.after : card.before).elements || {}).find(element => element.id === item?.refid)
      const title = describe(element?.name || item?.data?.title || item?.data?.text || '').text.replace(/\s+/g, ' ').slice(0, 60)
      const type = item?.type === 'reference' ? this.$gettext('Shared element')
        : this.$pgettext('st', this.schemas.content[item?.type]?.label || item?.type || this.$gettext('Content block'))
      return type + (title ? ': ' + title : '')
    },

    blockLocation(block) {
      return this.$gettext('Group: %{group} · Position %{num}', {
        group: this.$pgettext('sg', (block.after || block.before)?.group || 'main'), num: (block.to ?? block.from) + 1
      })
    },

    blockSummary(card, block) {
      const parts = []
      if (block.moved) parts.push(this.$gettext('Moved from position %{from} to %{to}', { from: block.from + 1, to: block.to + 1 }))
      if (block.fields.length) parts.push(this.$ngettext('%{num} field changed', '%{num} fields changed', block.fields.length, { num: block.fields.length }))
      if (this.selectable) parts.push(this.$gettext('%{selected} of %{total} selected', {
        selected: choices(block).filter(key => this.checked(card, key)).length, total: choices(block).length
      }))
      return parts.join(' · ')
    },

    checked(card, key) {
      return !this.unchecked[JSON.stringify([card.key, key])]
    },

    collapsed(card, block) {
      return this.collapsedBlocks[JSON.stringify([card.key, block.key])]
        ?? (card.diffs.content.length > 5 && block !== card.diffs.content[0])
    },

    date(value) {
      return value ? new Date(value).toLocaleString(this.$vuetify.locale.current) : this.$gettext('Unknown date')
    },

    async fetch() {
      const request = ++this.request
      this.versions = []
      this.opened = null
      this.collapsedBlocks = {}
      this.cursor = {}
      this.unchecked = {}
      this.loading = true
      this.failed = false
      this.onlySelected = false
      this.mode = 'restore'

      try {
        const versions = await this.load()
        if (request !== this.request) return
        if (!Array.isArray(versions)) throw new Error('Invalid version response')

        this.versions = Object.freeze(versions)
        const first = this.cards.find(card => card.keys.length || card.files.length) || this.cards[0]
        this.opened = first?.key ?? null
      } catch {
        if (request === this.request) this.failed = true
      } finally {
        if (request === this.request) this.loading = false
      }
    },

    fieldLabel(card, field, section, block) {
      const path = field.path.filter((key, index) => !(key === 'data' || (index === 0 && key === section)))
      const definition = this.schema(card, field, block)
      if (definition.label) path[path.length - 1] = definition.label
      else if (path.at(-1) === 'refid') path[path.length - 1] = this.$gettext('Shared element')
      return path.map(key => this.$pgettext('fn', key).replace(/-|_/g, ' ')).join(' › ') || this.$gettext('Value')
    },

    focus(event) {
      const key = event.target.closest('[data-change-key]')?.dataset.changeKey
      if (this.active && this.navigation.some(entry => entry.key === key)) this.cursor[this.active.key] = key
    },

    inactive(card, key) {
      return this.selectable && !this.checked(card, key)
    },

    mark() {
      const body = this.$refs.body?.$el, entry = this.navigation[this.position]
      if (!body) return
      const target = entry && (this.targets().get(entry.key) || [...body.querySelectorAll('[data-block-key]')].find(node => node.dataset.blockKey === entry.block?.key))
      for (const node of body.querySelectorAll('[data-current-change]')) {
        if (node === target) continue
        node.removeAttribute('data-current-change')
        node.removeAttribute('aria-current')
      }
      if (target) {
        target.setAttribute('data-current-change', '')
        target.setAttribute('aria-current', 'true')
      }
    },

    async navigate(step) {
      const card = this.active, entry = this.navigation[this.position + step]
      if (!card || !entry) return
      this.cursor[card.key] = entry.key
      if (entry.block) this.collapsedBlocks[JSON.stringify([card.key, entry.block.key])] = false
      await this.$nextTick()
      const body = this.$refs.body?.$el, target = this.targets().get(entry.key)
      if (target) {
        target.focus({ preventScroll: true })
        body.scrollTo({ top: body.scrollTop + target.getBoundingClientRect().top - body.getBoundingClientRect().top - 12 })
      }
    },

    open(card) {
      this.opened = this.opened === card.key ? null : card.key
      this.onlySelected = false
    },

    schema(card, field, block) {
      let path = [...field.path], definitions
      if (block && path[0] === 'data') {
        definitions = this.schemas.content[(block.after || block.before).type]?.fields
        path.shift()
      } else if (['meta', 'config'].includes(path[0])) {
        const [section, key] = path.splice(0, 2)
        const item = card.after.data?.[section]?.[key] || card.before.data?.[section]?.[key]
        definitions = this.schemas[section][item?.type || key]?.fields
        if (path[0] === 'data') path.shift()
      } else if (path[0] === 'data') {
        definitions = this.schemas.content[card.after.data?.type || card.before.data?.type]?.fields
        path.shift()
      }
      let definition
      for (const key of path) {
        definition = definitions?.[key]
        definitions = definition?.item || definition?.fields
      }
      return definition || {}
    },

    selected(card) {
      return card.keys.filter(key => this.checked(card, key)).length
    },

    summary(card) {
      if (card.unavailable) return this.$gettext('No earlier version available')
      const parts = []
      const count = Object.entries(card.diffs).filter(([section]) => section !== 'content').reduce((sum, [, entries]) => sum + entries.length, 0)
      if (count) parts.push(this.$ngettext('%{num} field changed', '%{num} fields changed', count, { num: count }))

      const blocks = card.diffs.content || []
      const added = blocks.filter(block => block.kind === 'added').length
      const removed = blocks.filter(block => block.kind === 'removed').length
      const changed = blocks.filter(block => block.kind === 'changed' && block.fields.length).length
      const moved = blocks.filter(block => block.moved).length
      if (added) parts.push(this.$ngettext('%{num} block added', '%{num} blocks added', added, { num: added }))
      if (removed) parts.push(this.$ngettext('%{num} block removed', '%{num} blocks removed', removed, { num: removed }))
      if (changed) parts.push(this.$ngettext('%{num} block changed', '%{num} blocks changed', changed, { num: changed }))
      if (moved) parts.push(this.$ngettext('%{num} block moved', '%{num} blocks moved', moved, { num: moved }))
      const files = new Set(card.files.map(entry => entry.file.id)).size
      if (files) parts.push(this.$ngettext('%{num} file changed', '%{num} files changed', files, { num: files }))
      return parts.join(' · ') || this.$gettext('No changes')
    },

    targets() {
      const nodes = new Map()
      for (const field of this.$refs.body?.$el.querySelectorAll('[data-change-key]') || []) {
        nodes.set(field.dataset.changeKey, field)
      }
      return nodes
    },

    toggle(card, key) {
      const id = JSON.stringify([card.key, key])
      if (this.unchecked[id]) delete this.unchecked[id]
      else this.unchecked[id] = true
    },

    toggleAll(card, keys = card.keys) {
      const checked = keys.every(key => this.checked(card, key))
      for (const key of keys) {
        const id = JSON.stringify([card.key, key])
        if (checked) this.unchecked[id] = true
        else delete this.unchecked[id]
      }
    },

    toggleBlock(card, block) {
      this.collapsedBlocks[JSON.stringify([card.key, block.key])] = !this.collapsed(card, block)
    },

    visible(card) {
      if (!this.onlySelected || !this.selectable) return card.diffs
      return Object.fromEntries(Object.entries(card.diffs).map(([section, entries]) => [section,
        entries.filter(entry => section === 'content' ? choices(entry).some(key => this.checked(card, key)) : this.checked(card, entry.key))
      ]).filter(([, entries]) => entries.length))
    }
  },

  watch: {
    collapsedBlocks: {
      deep: true,
      handler() { this.$nextTick(this.mark) }
    },

    async mode() {
      this.onlySelected = false
      this.cursor = {}
      await this.$nextTick()
      const body = this.$refs.body?.$el
      const heading = body?.querySelector('.version-heading[aria-expanded="true"]')
      if (heading) body.scrollTo({ top: body.scrollTop + heading.getBoundingClientRect().top - body.getBoundingClientRect().top - 12 })
    },

    modelValue: {
      immediate: true,
      handler(value) {
        if (value) this.fetch()
        else {
          this.request++
          this.versions = []
          this.opened = null
          this.collapsedBlocks = {}
          this.cursor = {}
          this.unchecked = {}
          this.loading = false
        }
      }
    },

    navigation() { this.$nextTick(this.mark) },

    position() { this.$nextTick(this.mark) }
  }
}
</script>

<template>
  <v-dialog
    :aria-label="$gettext('History')"
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200"
    scrollable
  >
    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('History') }}</v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="$emit('update:modelValue', false)" />
      </v-toolbar>
      <div v-if="cards.length" class="history-mode">
        <v-btn-toggle v-model="mode" mandatory density="compact" divided :aria-label="$gettext('Comparison')">
          <v-btn value="restore">{{ $gettext('Restore changes') }}</v-btn>
          <v-btn value="save">{{ $gettext('Changes in this save') }}</v-btn>
        </v-btn-toggle>
      </div>
      <v-card-text ref="body" class="history-body" @focusin="focus">
        <v-timeline side="end" align="start">
          <v-timeline-item v-if="loading" dot-color="grey-lighten-1" size="small" width="100%">
            <div class="loading" role="status">{{ $gettext('Loading') }}<v-progress-circular indeterminate size="24" /></div>
          </v-timeline-item>
          <v-timeline-item v-else-if="failed" dot-color="error" size="small" width="100%">
            <div role="alert">{{ $gettext('Error fetching versions') }}</div>
            <v-btn variant="text" @click="fetch">{{ $gettext('Retry') }}</v-btn>
          </v-timeline-item>
          <v-timeline-item v-else-if="!cards.length" dot-color="grey-lighten-1" size="small" width="100%">
            <span role="status">{{ $gettext('No changes') }}</span>
          </v-timeline-item>

          <v-timeline-item
            v-for="card in cards" :key="card.key"
            :dot-color="card.version.published ? 'success' : 'grey-lighten-1'"
            :class="{ publish: !card.version.published && card.version.publish_at }"
            width="100%" size="small"
          >
            <v-card :elevation="2" class="version-card">
              <button class="version-heading" :aria-expanded="opened === card.key" @click="open(card)">
                <span class="version-title">
                  {{ date(card.version.created_at) }}
                  <v-icon :icon="opened === card.key ? mdiChevronUp : mdiChevronDown" aria-hidden="true" />
                </span>
                <span class="version-badges">
                  <v-chip v-if="card.unsaved" size="small" color="info" label>{{ $gettext('Unsaved changes') }}</v-chip>
                  <v-chip v-if="card.version === versions[0]" size="small" label>{{ $gettext('Latest saved') }}</v-chip>
                  <v-chip v-if="card.version.published" size="small" color="success" label>{{ $gettext('Published') }}</v-chip>
                  <v-chip v-else-if="card.version.publish_at" size="small" color="warning" label>{{ $gettext('Scheduled') }}</v-chip>
                  <v-chip v-else size="small" label>{{ $gettext('Draft') }}</v-chip>
                </span>
                <span class="version-editor">{{ card.version.editor }}</span>
                <span v-if="!card.version.published && card.version.publish_at" class="version-editor">
                  {{ $gettext('Scheduled for %{date}', { date: date(card.version.publish_at) }) }}
                </span>
                <span class="version-summary">{{ summary(card) }}</span>
              </button>

              <v-card-text v-if="opened === card.key" class="version-diffs">
                <p class="comparison-label">
                  {{ mode === 'save' ? $gettext('Previous saved value → Value in this save') : $gettext('Current value → Saved value') }}
                </p>
                <p v-if="mode === 'save' && !card.unavailable" class="comparison-source">
                  {{ $gettext('Compared with the version saved on %{date}', { date: date(card.before.created_at) }) }}
                </p>
                <div v-if="selectable && card.keys.length" class="selection-bar">
                  <v-checkbox
                    :model-value="selected(card) === card.keys.length"
                    :indeterminate="selected(card) > 0 && selected(card) < card.keys.length"
                    :label="$gettext('Select all')" @update:model-value="toggleAll(card)" hide-details density="compact"
                  />
                  <v-checkbox v-model="onlySelected" :label="$gettext('Selected only')" hide-details density="compact" class="selected-filter" />
                </div>

                <section v-for="(entries, name) in visible(card)" :key="name" class="diff-section">
                  <h3 class="section-header">{{ labels[name] }}</h3>
                  <template v-if="name === 'content'">
                    <div v-for="block in entries" :key="block.key" class="diff-block" role="group" :aria-label="blockLabel(card, block)"
                      :data-block-key="block.key"
                      :class="{ 'is-unselected': block.kind !== 'changed' && inactive(card, block.key) }"
                      :data-change-key="block.kind !== 'changed' ? block.key : undefined" tabindex="-1"
                    >
                      <div class="diff-heading">
                        <v-checkbox
                          v-if="selectable" class="diff-check block-check" :model-value="choices(block).every(key => checked(card, key))"
                          :indeterminate="choices(block).some(key => checked(card, key)) && !choices(block).every(key => checked(card, key))"
                          :aria-label="blockLabel(card, block)" @update:model-value="toggleAll(card, choices(block))" hide-details density="compact"
                        />
                        <h4 class="block-title">
                          <button class="block-toggle" :aria-expanded="!collapsed(card, block)" @click="toggleBlock(card, block)">
                            <span>{{ blockLabel(card, block) }}</span>
                            <v-icon :icon="collapsed(card, block) ? mdiChevronDown : mdiChevronUp" aria-hidden="true" />
                          </button>
                        </h4>
                        <v-chip v-if="block.kind === 'added'" size="small" color="success" label>{{ $gettext('Block added') }}</v-chip>
                        <v-chip v-else-if="block.kind === 'removed'" size="small" color="error" label>{{ $gettext('Block removed') }}</v-chip>
                      </div>
                      <p v-if="block.kind === 'changed'" class="block-summary">{{ blockSummary(card, block) }}</p>
                      <p v-if="selectable && !choices(block).some(key => checked(card, key))" class="keep-current">{{ $gettext('Keep current value') }}</p>
                      <div v-if="!collapsed(card, block)" class="block-details">
                        <p class="block-location">{{ blockLocation(block) }}</p>
                        <p v-if="block.kind !== 'changed' && blockFields(block).length" class="snapshot-label">{{ block.kind === 'removed' ? beforeLabel : afterLabel(card, block.key) }}</p>
                        <div v-if="block.moved && (!onlySelected || !selectable || checked(card, block.moveKey))" class="block-move" :class="{ 'is-unselected': inactive(card, block.moveKey) }" :data-change-key="block.moveKey" tabindex="-1">
                          <v-checkbox v-if="selectable" :model-value="checked(card, block.moveKey)" :aria-label="$gettext('Block position')"
                            @update:model-value="toggle(card, block.moveKey)" hide-details density="compact"
                          />
                          {{ $gettext('Moved from position %{from} to %{to}', { from: block.from + 1, to: block.to + 1 }) }}
                          <span v-if="inactive(card, block.moveKey)" class="keep-current">{{ $gettext('Keep current position') }}</span>
                        </div>
                        <div v-for="field in blockFields(block).filter(field => !onlySelected || !selectable || block.kind !== 'changed' || checked(card, field.key))" :key="field.key" class="diff-group" role="group" :aria-label="fieldLabel(card, field, name, block)"
                          :class="{ 'is-unselected': block.kind === 'changed' && inactive(card, field.key) }"
                          :data-change-key="block.kind === 'changed' ? field.key : undefined" tabindex="-1"
                        >
                          <div class="diff-heading">
                            <v-checkbox v-if="selectable && block.kind === 'changed'" class="diff-check" :model-value="checked(card, field.key)"
                              :aria-label="fieldLabel(card, field, name, block)" @update:model-value="toggle(card, field.key)" hide-details density="compact"
                            />
                            <h5 class="diff-label">{{ fieldLabel(card, field, name, block) }}</h5>
                            <span v-if="block.kind === 'changed' && inactive(card, field.key)" class="keep-current">{{ $gettext('Keep current value') }}</span>
                          </div>
                          <HistoryField :field="field" :schema="schema(card, field, block)" :before="card.before" :after="card.after"
                            :side="block.kind === 'changed' ? null : block.kind === 'removed' ? 'before' : 'after'" :raw-details="block.kind === 'changed'"
                            :before-label="beforeLabel"
                            :after-label="afterLabel(card, block.kind === 'changed' ? field.key : block.key)"
                          />
                        </div>
                        <details v-if="block.kind !== 'changed'" class="block-raw raw-details">
                          <summary>{{ $gettext('Show raw details') }}</summary>
                          <pre>{{ JSON.stringify(block.after || block.before, null, 2) }}</pre>
                        </details>
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    <div v-for="field in entries" :key="field.key" class="diff-group" :class="{ 'is-unselected': inactive(card, field.key) }" role="group" :aria-label="fieldLabel(card, field, name)" :data-change-key="field.key" tabindex="-1">
                      <div class="diff-heading">
                        <v-checkbox
                          v-if="selectable" class="diff-check" :model-value="checked(card, field.key)"
                          :aria-label="fieldLabel(card, field, name)" @update:model-value="toggle(card, field.key)" hide-details density="compact"
                        />
                        <h4 class="diff-label">{{ fieldLabel(card, field, name) }}</h4>
                        <span v-if="inactive(card, field.key)" class="keep-current">{{ $gettext('Keep current value') }}</span>
                      </div>
                      <HistoryField :field="field" :schema="schema(card, field)" :before="card.before" :after="card.after"
                        :before-label="beforeLabel"
                        :after-label="afterLabel(card, field.key)"
                      />
                    </div>
                  </template>
                </section>

                <section v-if="remaining.length" class="diff-section" data-change-key="media" tabindex="-1" :aria-label="$gettext('Media')">
                  <h3 class="section-header">{{ $gettext('Media') }}</h3>
                  <HistoryMedia :before="remaining.filter(entry => entry.side === 'before').map(entry => entry.file)"
                    :after="remaining.filter(entry => entry.side === 'after').map(entry => entry.file)"
                    :before-label="beforeLabel"
                    :after-label="afterLabel(card)"
                  />
                </section>
                <div v-if="card.unavailable" role="status">{{ $gettext('No earlier version available') }}</div>
                <div v-else-if="!card.keys.length && !card.files.length" role="status">{{ $gettext('No changes') }}</div>
                <div v-else-if="onlySelected && selectable && !selected(card)" role="status">{{ $gettext('No changes selected') }}</div>
              </v-card-text>
            </v-card>
          </v-timeline-item>
        </v-timeline>
      </v-card-text>
      <div v-if="navigation.length > 1" class="history-navigation">
        <v-btn variant="text" size="small" :prepend-icon="mdiChevronUp" :disabled="position === 0" @click="navigate(-1)">{{ $gettext('Previous change') }}</v-btn>
        <span class="navigation-current" role="status">
          <span class="navigation-count">{{ $gettext('Change %{num} of %{total}', { num: navigation[position]?.number, total: navigation[position]?.total }) }}</span>
          <span class="navigation-label" :title="navigation[position]?.label">{{ navigation[position]?.label }}</span>
        </span>
        <v-btn variant="text" size="small" :append-icon="mdiChevronDown" :disabled="position === navigation.length - 1" @click="navigate(1)">{{ $gettext('Next change') }}</v-btn>
      </div>
      <div v-if="active && selectable" class="history-actions diff-actions">
        <div class="restore-source">
          <span>{{ active.unsaved ? $gettext('Restore from: Latest saved version') : $gettext('Restore from: %{date}', { date: date(active.version.created_at) }) }}</span>
          <span role="status">{{ $gettext('%{selected} of %{total} selected for restoration', { selected: selected(active), total: active.keys.length }) }}</span>
        </div>
        <v-btn class="restore-selected" variant="tonal" color="info" :disabled="!selected(active)" @click="apply(active)">
          {{ $gettext('Restore selected changes') }}
        </v-btn>
        <v-menu v-if="$vuetify.display.width <= 700">
          <template #activator="{ props }">
            <v-btn v-bind="props" class="restore-more" variant="text" :icon="mdiDotsVertical" :aria-label="$gettext('More restoration options')" size="small" />
          </template>
          <v-list density="compact">
            <v-list-item :title="active.unsaved ? $gettext('Discard all changes') : $gettext('Restore version')"
              @click="$emit(active.unsaved ? 'revert' : 'use', active.version)" />
          </v-list>
        </v-menu>
        <v-btn v-else class="restore-whole" variant="text" @click="$emit(active.unsaved ? 'revert' : 'use', active.version)">
          {{ active.unsaved ? $gettext('Discard all changes') : $gettext('Restore version') }}
        </v-btn>
      </div>
      <div v-else-if="active && !readonly" class="history-actions">
        <v-btn variant="tonal" color="info" @click="mode = 'restore'">{{ $gettext('Select changes to restore') }}</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.v-timeline--vertical {
  grid-template-columns: 0 min-content minmax(0, 1fr);
}

.v-timeline :deep(.v-timeline-item__body) {
  min-width: 0;
  width: 100%;
}

.version-card {
  min-width: 0;
}

.version-heading {
  display: block;
  width: 100%;
  text-align: start;
  padding: 16px;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.version-heading:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.version-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  font-size: 1.1rem;
  font-weight: 600;
}

.version-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.version-editor, .version-summary {
  display: block;
  font-size: 0.875rem;
  margin-top: 6px;
  overflow-wrap: anywhere;
}

.version-editor {
  opacity: 0.75;
}

.version-diffs {
  padding-top: 0;
}

.comparison-label {
  margin: 0 0 8px;
  font-weight: 500;
}

.comparison-source {
  font-size: 0.8rem;
  margin: 0 0 8px;
}

.history-mode {
  flex: 0 0 auto;
  padding: 8px 24px;
}

.history-mode .v-btn {
  font-size: 0.8rem;
  letter-spacing: normal;
}

.keep-current {
  font-size: 0.8rem;
  font-weight: 500;
}

.diff-block.is-unselected, .diff-group.is-unselected {
  border-style: dashed;
}

.is-unselected > .field-comparison, .is-unselected > .block-details {
  filter: grayscale(1);
}

.is-unselected > .field-comparison :deep(.diff-side .highlight),
.is-unselected > .block-details :deep(.diff-side .highlight) {
  text-decoration: none;
}

.selection-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.875rem;
}

.selection-bar .v-checkbox {
  flex: 0 1 auto;
}

.diff-section {
  margin-bottom: 20px;
}

.section-header {
  font-size: 0.9rem;
  font-weight: 600;
  margin: 16px 0 8px;
}

.diff-block, .diff-group {
  min-width: 0;
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  padding: 10px;
  margin-bottom: 10px;
}

.block-details > .diff-group {
  border: 0;
  padding: 0;
  margin: 12px 0 0;
}

.block-title {
  flex: 1;
  min-width: 0;
}

.block-toggle {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  width: 100%;
  text-align: start;
  color: inherit;
  font: inherit;
  background: transparent;
  border: 0;
  cursor: pointer;
}

.block-toggle:focus-visible, [data-change-key]:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.history-body :deep([data-current-change]) {
  outline: 2px solid rgba(var(--v-theme-primary), 0.65);
  outline-offset: -2px;
}

.block-summary {
  font-size: 0.8rem;
  margin: 0 0 6px;
}

.snapshot-label {
  font-size: 0.8rem;
  font-weight: 500;
  margin: 0 0 6px;
}

.block-raw {
  margin-top: 8px;
  font-size: 0.8rem;
}

.block-raw summary {
  cursor: pointer;
}

.block-raw pre {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  margin-top: 6px;
}

.history-navigation {
  flex: 0 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 4px 16px;
  border-top: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  font-size: 0.8rem;
}

.navigation-current {
  flex: 1;
  min-width: 0;
  text-align: center;
}

.navigation-label {
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.75rem;
}

.diff-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.diff-heading h4, .diff-label {
  font-size: 0.875rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.diff-label {
  margin-bottom: 6px;
}

.diff-heading .diff-label {
  margin: 0;
}

.diff-check {
  flex: 0 0 auto;
}

.block-location {
  font-size: 0.8rem;
  opacity: 0.7;
  margin-bottom: 8px;
}


.block-move {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
  margin: 4px 0;
}

.diff-group:has(> .field-comparison.is-compact) {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 6px 12px;
}

.diff-group:has(> .field-comparison.is-compact) > .diff-heading {
  margin: 0;
}

.history-actions {
  flex: 0 0 auto;
  border-top: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  padding: 12px 24px;
}

.restore-source {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 4px 16px;
  width: 100%;
  font-size: 0.8rem;
}

.block-move .v-checkbox {
  flex: 0 0 auto;
}

.diff-actions {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding-top: 12px;
}

.restore-selected {
  order: 2;
  margin-inline-start: auto;
}

.restore-whole {
  order: 1;
}

.loading {
  display: flex;
  align-items: center;
  gap: 12px;
}

@media (max-width: 700px) {
  .history-navigation {
    padding: 4px;
    gap: 0;
  }

  .history-mode {
    padding: 6px 8px;
  }

  .history-mode .v-btn {
    padding: 0 8px;
  }

  .history-navigation .v-btn {
    padding: 0 4px;
    letter-spacing: normal;
  }

  .v-timeline {
    column-gap: 4px;
  }

  .v-timeline :deep(.v-timeline-item__body) {
    padding-inline-start: 0;
  }

  .history-body {
    padding: 8px !important;
  }

  .version-heading {
    padding: 10px;
  }

  .version-title {
    font-size: 0.95rem;
  }

  .version-badges {
    gap: 4px;
    margin-top: 4px;
  }

  .version-badges .v-chip {
    height: 20px;
    padding: 0 6px;
    font-size: 0.7rem;
  }

  .version-editor, .version-summary {
    margin-top: 4px;
    font-size: 0.75rem;
    line-height: 1.35;
  }

  .version-diffs {
    padding: 0 10px 10px;
  }

  .history-actions {
    padding: 8px 12px;
    gap: 6px;
  }

  .restore-source {
    font-size: 0.75rem;
  }

  .restore-selected {
    order: 1;
    flex: 1;
    min-width: 0;
    margin: 0;
    font-size: 0.8rem;
    letter-spacing: normal;
  }

  .restore-more {
    order: 2;
  }

}
</style>
