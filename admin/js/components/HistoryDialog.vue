/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose } from '@mdi/js'
import { useSchemaStore } from '../stores'
import { filechanges, plaintext, restore, sections } from '../history'
import HistoryField from './HistoryField.vue'

export default {
  components: { HistoryField },

  props: {
    modelValue: { type: Boolean, required: true },
    readonly: { type: Boolean, default: false },
    current: { type: Object, default: null },
    load: { type: Function, required: true }
  },

  emits: ['update:modelValue', 'apply', 'use'],

  setup() {
    return { schemas: useSchemaStore(), mdiClose }
  },

  data: () => ({
    cards: [],
    loading: false,
    failed: false,
    opened: null
  }),

  computed: {
    active() {
      return this.cards.find(card => this.opened === card.key)
    },

    labels() {
      return {
        data: this.$gettext('Fields'), meta: this.$gettext('Meta data'),
        config: this.$gettext('Configuration'), content: this.$gettext('Content')
      }
    }
  },

  mounted() {
    this.fetch()
  },

  methods: {
    apply(card) {
      if (this.readonly || !this.selected(card)) return
      const source = this.current || this.cards[0].after
      const changes = restore(source.data || {}, card.before.data || {}, card.diffs, key => card.selection[key])
      this.$emit('apply', changes, card.before)
    },

    blockLabel(card, block) {
      const item = block.after || block.before
      const element = Object.values((block.after ? card.after : card.before).elements || {}).find(element => element.id === item?.refid)
      const title = plaintext(element?.name || item?.data?.title || item?.data?.text || '').replace(/\s+/g, ' ').slice(0, 60)
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
      const fields = block.fields.filter(field => !field.position)
      if (block.moved) parts.push(this.$gettext('Moved from position %{from} to %{to}', { from: block.from + 1, to: block.to + 1 }))
      if (fields.length) parts.push(this.$ngettext('%{num} field changed', '%{num} fields changed', fields.length, { num: fields.length }))
      if (!this.readonly) parts.push(this.$gettext('%{selected} of %{total} selected', {
        selected: this.selected(card, block.keys), total: block.keys.length
      }))
      return parts.join(' · ')
    },

    date(value) {
      return value ? new Date(value).toLocaleString(this.$vuetify.locale.current) : this.$gettext('Unknown date')
    },

    async fetch() {
      this.cards = []
      this.opened = null
      this.loading = true
      this.failed = false

      try {
        const versions = await this.load()
        if (!Array.isArray(versions)) throw new Error('Invalid version response')

        const snapshots = this.current && versions.length ? [this.current, ...versions] : versions
        this.cards = snapshots.slice(0, -1).map((after, index) => {
          const before = snapshots[index + 1]
          const unsaved = index === 0 && !!this.current
          const diffs = sections(before.data || {}, after.data || {})
          const files = filechanges(before, after, diffs)
          const keys = Object.entries(diffs).flatMap(([section, entries]) => section === 'content' ? entries.flatMap(block => block.keys) : entries.map(entry => entry.key))
          const blocks = diffs.content || []
          return {
            key: unsaved ? 'current' : 'version:' + (after.id || index), before, after, unsaved, diffs, fileCount: files.count, keys,
            selection: Object.fromEntries(keys.map(key => [key, true])),
            remaining: files.remaining,
            expanded: (blocks.length > 5 ? blocks.slice(0, 1) : blocks).map(block => block.key)
          }
        }).filter(card => !card.unsaved || card.keys.length || card.fileCount)
        const first = this.cards.find(card => card.keys.length || card.fileCount) || this.cards[0]
        this.opened = first?.key ?? null
      } catch (error) {
        this.failed = true
        this.$log('HistoryDialog::fetch()', error)
      } finally {
        this.loading = false
      }
    },

    fieldLabel(card, field, section, block) {
      if (field.position) return this.$gettext('Block position')
      const path = field.path.filter((key, index) => !(key === 'data' || (index === 0 && key === section)))
      const definition = this.schema(card, field, block)
      if (definition.label) path[path.length - 1] = definition.label
      else if (path.at(-1) === 'refid') path[path.length - 1] = this.$gettext('Shared element')
      return path.map(key => this.$pgettext('fn', key).replace(/-|_/g, ' ')).join(' › ') || this.$gettext('Value')
    },

    inactive(card, key) {
      return !this.readonly && !card.selection[key]
    },

    schema(card, field, block) {
      if (!field.path) return {}
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

    selected(card, keys = card.keys) {
      return keys.filter(key => card.selection[key]).length
    },

    summary(card) {
      const parts = []
      const count = Object.entries(card.diffs).filter(([section]) => section !== 'content').reduce((sum, [, entries]) => sum + entries.length, 0)
      if (count) parts.push(this.$ngettext('%{num} field changed', '%{num} fields changed', count, { num: count }))

      const blocks = card.diffs.content || []
      const added = blocks.filter(block => block.kind === 'added').length
      const removed = blocks.filter(block => block.kind === 'removed').length
      const changed = blocks.filter(block => block.kind === 'changed' && block.fields.some(field => !field.position)).length
      const moved = blocks.filter(block => block.moved).length
      if (added) parts.push(this.$ngettext('%{num} block added', '%{num} blocks added', added, { num: added }))
      if (removed) parts.push(this.$ngettext('%{num} block removed', '%{num} blocks removed', removed, { num: removed }))
      if (changed) parts.push(this.$ngettext('%{num} block changed', '%{num} blocks changed', changed, { num: changed }))
      if (moved) parts.push(this.$ngettext('%{num} block moved', '%{num} blocks moved', moved, { num: moved }))
      if (card.fileCount) parts.push(this.$ngettext('%{num} file changed', '%{num} files changed', card.fileCount, { num: card.fileCount }))
      return parts.join(' · ') || this.$gettext('No changes')
    },

    toggleAll(card, keys = card.keys) {
      const selected = keys.every(key => card.selection[key])
      for (const key of keys) card.selection[key] = !selected
    }
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
      <v-card-text class="history-body">
        <v-expansion-panels v-model="opened" class="version-panels" elevation="2">
          <v-timeline side="end" align="start">
            <v-timeline-item v-if="loading" dot-color="grey-lighten-1" size="small" width="100%">
              <div class="loading" role="status">{{ $gettext('Loading') }}<v-progress-circular indeterminate size="24" /></div>
            </v-timeline-item>
            <v-timeline-item v-else-if="failed" dot-color="error" size="small" width="100%">
              <div role="alert">{{ $gettext('Error fetching versions') }}</div>
              <v-btn variant="outlined" @click="fetch">{{ $gettext('Retry') }}</v-btn>
            </v-timeline-item>
            <v-timeline-item v-else-if="!cards.length" dot-color="grey-lighten-1" size="small" width="100%">
              <span role="status">{{ $gettext('No changes') }}</span>
            </v-timeline-item>

            <v-timeline-item
              v-for="card in cards" :key="card.key"
              :dot-color="card.after.published ? 'success' : 'grey-lighten-1'"
              width="100%" size="small"
            >
              <v-expansion-panel :value="card.key" class="version-panel">
                <v-expansion-panel-title class="version-heading version-panel-title">
                  <span class="version-title" role="heading" aria-level="3">
                    <span class="version-date">{{ card.unsaved ? $gettext('Current changes') : date(card.after.created_at) }}</span>
                    <span class="version-summary">{{ summary(card) }}</span>
                  </span>
                  <span v-if="!card.unsaved" class="version-editor">
                    {{ card.after.editor }}
                    <template v-if="!card.after.published && card.after.publish_at">
                      {{ card.after.editor ? ' · ' : '' }}{{ $gettext('Scheduled for %{date}', { date: date(card.after.publish_at) }) }}
                    </template>
                  </span>
                  <v-checkbox
                    v-if="!readonly && card.keys.length" class="select-all"
                    :model-value="selected(card) === card.keys.length"
                    :indeterminate="selected(card) > 0 && selected(card) < card.keys.length"
                    :disabled="opened !== card.key"
                    :aria-label="$gettext('Select all')" @click.stop @update:model-value="toggleAll(card)" hide-details density="compact"
                  />
                </v-expansion-panel-title>

                <v-expansion-panel-text class="version-diffs">
                <section v-for="(entries, name) in card.diffs" :key="name" class="diff-section">
                  <h3 class="section-header">{{ labels[name] }}</h3>
                  <template v-if="name === 'content'">
                    <v-expansion-panels v-model="card.expanded" class="block-panels" multiple elevation="0">
                      <v-expansion-panel v-for="block in entries" :key="block.key" :value="block.key"
                        class="diff-block" role="group" :aria-label="blockLabel(card, block)" :data-block-key="block.key"
                        :class="{ 'is-unselected': block.kind !== 'changed' && inactive(card, block.key) }"
                      >
                        <v-expansion-panel-title class="diff-heading">
                          <v-chip v-if="block.kind === 'added'" size="small" color="success" label>{{ $gettext('Block added') }}</v-chip>
                          <v-chip v-else-if="block.kind === 'removed'" size="small" color="error" label>{{ $gettext('Block removed') }}</v-chip>
                          <span class="block-title" role="heading" aria-level="4">
                            <span>{{ blockLabel(card, block) }}</span>
                            <span v-if="block.kind === 'changed'" class="block-summary">{{ blockSummary(card, block) }}</span>
                          </span>
                          <v-checkbox
                            v-if="!readonly" class="diff-check block-check" :model-value="selected(card, block.keys) === block.keys.length"
                            :indeterminate="selected(card, block.keys) > 0 && selected(card, block.keys) < block.keys.length"
                            :aria-label="blockLabel(card, block)" @click.stop @update:model-value="toggleAll(card, block.keys)" hide-details density="compact"
                          />
                        </v-expansion-panel-title>
                        <v-expansion-panel-text>
                          <div class="block-details">
                            <p class="block-location">{{ blockLocation(block) }}</p>
                            <div v-for="field in block.fields" :key="field.key" class="diff-group" role="group" :aria-label="fieldLabel(card, field, name, block)"
                              :class="{ 'is-unselected': block.kind === 'changed' && inactive(card, field.key) }"
                            >
                              <div class="diff-heading">
                                <h5 class="diff-label">{{ fieldLabel(card, field, name, block) }}</h5>
                                <span v-if="block.kind === 'changed' && inactive(card, field.key)" class="keep-current">
                                  {{ field.position ? $gettext('Keep current position') : $gettext('Keep current value') }}
                                </span>
                                <v-checkbox v-if="!readonly && block.kind === 'changed'" v-model="card.selection[field.key]" class="diff-check"
                                  :aria-label="fieldLabel(card, field, name, block)" hide-details density="compact"
                                />
                              </div>
                              <HistoryField :field="field" :type="schema(card, field, block).type" />
                            </div>
                            <HistoryField v-if="block.kind !== 'changed'" class="block-raw" :field="{ before: block.before, after: block.after }" />
                          </div>
                        </v-expansion-panel-text>
                      </v-expansion-panel>
                    </v-expansion-panels>
                  </template>
                  <template v-else>
                    <div v-for="field in entries" :key="field.key" class="diff-group" :class="{ 'is-unselected': inactive(card, field.key) }"
                      role="group" :aria-label="fieldLabel(card, field, name)"
                    >
                      <div class="diff-heading">
                        <h4 class="diff-label">{{ fieldLabel(card, field, name) }}</h4>
                        <span v-if="inactive(card, field.key)" class="keep-current">{{ $gettext('Keep current value') }}</span>
                        <v-checkbox
                          v-if="!readonly" v-model="card.selection[field.key]" class="diff-check"
                          :aria-label="fieldLabel(card, field, name)" hide-details density="compact"
                        />
                      </div>
                      <HistoryField :field="field" :type="schema(card, field).type" />
                    </div>
                  </template>
                </section>

                <section v-if="card.remaining.before.length || card.remaining.after.length" class="diff-section" :aria-label="$gettext('Media')"
                >
                  <h3 class="section-header">{{ $gettext('Media') }}</h3>
                  <HistoryField :field="{ media: card.remaining }" />
                </section>
                <div v-if="!card.keys.length && !card.fileCount" role="status">{{ $gettext('No changes') }}</div>
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-timeline-item>
          </v-timeline>
        </v-expansion-panels>
      </v-card-text>
      <div v-if="active && !readonly" class="history-actions">
        <div class="restore-source">
          <span>{{ active.unsaved ? $gettext('Previous version: Latest saved version') : $gettext('Previous version: %{date}', { date: date(active.before.created_at) }) }}</span>
          <span role="status">{{ $gettext('%{selected} of %{total} selected for reverting', { selected: selected(active), total: active.keys.length }) }}</span>
        </div>
        <v-btn class="restore-selected" variant="tonal" color="info" :disabled="!selected(active)" @click="apply(active)">
          {{ $gettext('Revert selected changes') }}
        </v-btn>
        <v-btn class="restore-whole" variant="outlined" @click="$emit('use', active.before, active.unsaved)">
          {{ $gettext('Restore previous version') }}
        </v-btn>
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

.version-panels, .version-panel {
  min-width: 0;
}

.version-panels, .block-panels {
  display: block;
}

.version-panel::after, .diff-block::after {
  display: none;
}

.version-panel :deep(.v-expansion-panel-title.version-heading) {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto 48px;
  grid-template-rows: auto auto;
  align-items: center;
  row-gap: 6px;
  min-height: 0;
  width: 100%;
  padding: 16px;
  text-align: start;
}

.version-title {
  grid-column: 1;
  grid-row: 1;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 16px;
  min-width: 0;
  font-size: 1.1rem;
  font-weight: 600;
  width: 100%;
}

.version-date {
  white-space: nowrap;
}

.version-panel :deep(.v-expansion-panel-title__icon) {
  grid-column: 3;
  grid-row: 1;
  justify-self: end;
  justify-content: center;
  width: 48px;
  margin-inline-start: 0;
}

.select-all {
  grid-column: 2;
  grid-row: 1;
  align-self: center;
  justify-self: end;
  width: 48px;
}

.select-all :deep(.v-selection-control__wrapper) {
  width: 48px;
}

.version-editor {
  grid-column: 1;
  grid-row: 2;
  min-width: 0;
  overflow-wrap: anywhere;
  font-size: 0.875rem;
  opacity: 0.75;
}

.version-summary {
  min-width: 0;
  overflow-wrap: normal;
  text-align: end;
  font-size: 0.875rem;
  font-weight: 400;
}

.version-diffs {
  min-width: 0;
}

.version-diffs :deep(.v-expansion-panel-text__wrapper) {
  padding: 0 16px 16px;
}

.keep-current {
  font-size: 0.8rem;
  font-weight: 500;
}

.diff-block.is-unselected, .diff-group.is-unselected {
  border-style: dashed;
}

.is-unselected > .field-comparison, .is-unselected .block-details {
  filter: grayscale(1);
}

.is-unselected > .field-comparison :deep(.highlight),
.is-unselected .block-details :deep(.highlight) {
  text-decoration: none;
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
  margin-bottom: 10px;
}

.diff-group {
  padding: 10px;
}

.diff-block {
  border-radius: 6px !important;
  margin-top: 0 !important;
}

.diff-block :deep(.v-expansion-panel-title) {
  min-height: 48px !important;
  padding: 10px;
}

.diff-block :deep(.v-expansion-panel-text__wrapper) {
  padding: 0 10px 10px;
}

.block-details > .diff-group {
  margin: 12px 0 0;
  border: 0;
}

.block-title {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.block-summary {
  min-width: 0;
  font-size: 0.8rem;
  font-weight: 400;
  text-align: end;
  overflow-wrap: anywhere;
}

.diff-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.diff-block :deep(.v-expansion-panel-title.diff-heading) {
  margin-bottom: 0;
}

.diff-label {
  font-size: 0.875rem;
  font-weight: 600;
  overflow-wrap: anywhere;
  margin: 0;
}

.diff-check {
  flex: 0 0 auto;
  margin-inline-start: auto;
}

.block-location {
  font-size: 0.8rem;
  opacity: 0.7;
  margin-bottom: 8px;
}


.history-actions {
  flex: 0 0 auto;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
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
  .v-timeline {
    column-gap: 4px;
  }

  .v-timeline :deep(.v-timeline-item__body) {
    padding-inline-start: 0;
  }

  .history-body {
    padding: 8px !important;
  }

  .version-panel :deep(.v-expansion-panel-title.version-heading) {
    padding: 10px;
    row-gap: 4px;
  }

  .version-title {
    font-size: 0.95rem;
  }

  .version-editor {
    font-size: 0.75rem;
    line-height: 1.35;
  }

  .version-diffs :deep(.v-expansion-panel-text__wrapper) {
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

}
</style>
