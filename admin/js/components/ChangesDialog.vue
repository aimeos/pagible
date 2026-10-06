/** @license MIT, https://opensource.org/license/mit */

<script>
import { diffLines } from 'diff'
import { mdiUndoVariant } from '@mdi/js'
import CmsDialog from './Dialog.vue'
import { fields, words } from '../history'
import { itemTitle, stringify } from '../utils'

export default {
  components: {
    CmsDialog
  },

  props: {
    modelValue: { type: Boolean, default: false },
    changed: { type: Object, default: null },
    targets: { type: Object, default: () => ({}) }
  },

  emits: ['update:modelValue', 'resolve'],

  setup() {
    return { mdiUndoVariant, stringify }
  },

  data: () => ({
    resolved: new Set()
  }),

  computed: {
    conflicts() {
      if (!this.changed) return {}

      const { editor, latest, ...sections } = this.changed
      const result = {}

      for (const [name, section] of Object.entries(sections)) {
        const filtered = {}

        for (const [key, info] of Object.entries(section)) {
          if (info.overwritten) {
            filtered[key] = info
          }
        }

        if (Object.keys(filtered).length) {
          result[name] = filtered
        }
      }

      return result
    },

    labels() {
      return {
        aux: this.$gettext('Fields'),
        data: this.$gettext('Fields'),
        meta: this.$gettext('Meta tags'),
        config: this.$gettext('Configuration'),
        content: this.$gettext('Content')
      }
    },

    changes() {
      const map = {}

      for (const [name, section] of Object.entries(this.conflicts)) {
        for (const [key, info] of Object.entries(section)) {
          const isObj = typeof info.overwritten === 'object' && typeof (info.current ?? info.overwritten) === 'object'
          const prev = info.previous != null
          const theirsDiff = prev ? this.diff(info.previous, info.overwritten, isObj) : this.diff(info.overwritten, info.current, isObj)
          const mineDiff = prev ? this.diff(info.previous, info.current, isObj) : null
          const merge = info.merged ?? null
          let merged = []

          if (isObj && merge && typeof merge === 'object' && typeof info.current === 'object') {
            const data = info.current?.data && merge.data
            merged = this.getChangedFields(data ? info.previous?.data : info.previous, data ? merge.data : merge)
              .map(f => ({ label: f.label, value: f.new }))
          } else if (!isObj && merge != null) {
            merged = [{ value: stringify(merge) }]
          }

          const sides = [
            { symbol: '−', css: 'change-theirs', diff: theirsDiff },
            { symbol: '+', css: 'change-mine', diff: mineDiff }
          ].filter((side) => side.diff)

          map[`${name}.${key}`] = { theirsDiff, mineDiff, merge, merged, isObj, sides }
        }
      }
      return map
    },

    totalConflicts() {
      return Object.values(this.conflicts).reduce((sum, s) => sum + Object.keys(s).length, 0)
    },

    show: {
      get() {
        return this.modelValue
      },
      set(v) {
        this.$emit('update:modelValue', v)
      }
    }
  },

  watch: {
    changed() {
      this.resolved = new Set()
    },

    modelValue(val) {
      if (!val) {
        this.resolved = new Set()
      }
    },

    resolved: {
      deep: true,
      handler(val) {
        if (val.size > 0 && val.size >= this.totalConflicts) {
          this.show = false
        }
      }
    }
  },

  methods: {
    // field word diffs for objects, line diffs with word highlights for other values
    diff(a, b, isObj) {
      if (isObj) {
        return this.getChangedFields(a?.data ?? a, b?.data ?? b).map(({ label, old, new: next }) => ({
          label,
          words: words(old || '', next || '').map(this.wordDiff)
        }))
      }

      const lines = diffLines(stringify(a), stringify(b))
      const diff = []

      for (let i = 0; i < lines.length; i++) {
        const part = lines[i]
        const next = lines[i + 1]

        if (part.removed && next?.added) {
          const parts = words(part.value, next.value)

          diff.push({
            removed: parts.filter(w => !w.added).map(w => ({ value: w.value, highlight: !!w.removed })),
            added: parts.filter(w => !w.removed).map(w => ({ value: w.value, highlight: !!w.added })),
            words: parts.map(this.wordDiff),
          })
          i++
        } else if (part.removed) {
          diff.push({ removed: [{ value: part.value, highlight: true }], added: null })
        } else if (part.added) {
          diff.push({ removed: null, added: [{ value: part.value, highlight: true }] })
        }
      }

      return diff
    },

    // changed (nested) fields of both objects with their translated labels
    getChangedFields(a, b) {
      return fields(a || {}, b || {}).map((field) => ({
        label: field.path.map((key) => this.$pgettext('fn', key)).join(' › '),
        old: stringify(field.before),
        new: stringify(field.after)
      }))
    },

    label(name, key, info) {
      const block = info.current || info.overwritten

      return block?.type
        ? this.$pgettext('st', block.type).replace('::', ' ') + ': ' + this.title(block)
        : this.$pgettext('fn', key)
    },

    merge(section, key) {
      const merged = this.changes[`${section}.${key}`]?.merge

      if (merged == null) return

      this.resolve(section, key, merged)
    },

    resolve(section, key, value) {
      const info = this.changed[section][key]
      const [target, idx] = this.target(section, key)

      if (target) {
        info.snapshot = target[idx]
        target[idx] = value
      }

      info.resolved = value
      this.resolved.add(`${section}.${key}`)
      this.$emit('resolve')
    },

    // target list or object of the section and the index or key of the entry, no target if not found
    target(section, key) {
      const target = this.targets[section]

      if (Array.isArray(target)) {
        const idx = target.findIndex((b) => (b.id || b.refid) === key)
        return idx >= 0 ? [target, idx] : [null, -1]
      }

      return [target, key]
    },

    title(block) {
      if (!block || typeof block !== 'object' || !block.type) return null
      return itemTitle(block.data) || this.$pgettext('st', block.type).replace('::', ' ') || ''
    },

    unresolve(section, key) {
      const info = this.changed[section][key]
      const [target, idx] = this.target(section, key)

      if (target && 'snapshot' in info) {
        target[idx] = info.snapshot
      }

      delete info.snapshot

      delete info.resolved
      this.resolved.delete(`${section}.${key}`)
    },

    wordDiff(w) {
      return { value: w.value, removed: !!w.removed, added: !!w.added }
    }
  }
}
</script>

<template>
  <CmsDialog
    v-model="show"
    :title="$gettext('Conflicts from %{editor}', { editor: changed?.editor || '' })"
    toolbar-color="error"
    max-width="800"
  >
    <template #toolbar-actions>
      <span class="toolbar-counter" aria-live="polite">
        {{ $gettext('%{count} / %{total}', { count: resolved.size, total: totalConflicts }) }}
      </span>
    </template>
    <template v-for="(section, name) in conflicts" :key="name">
      <h3 class="section-header">
        <v-chip size="small" label>{{ labels[name] || name }}</v-chip>
      </h3>
      <v-card
        v-for="(info, key) in section"
        :key="key"
        variant="outlined"
        class="conflict-card mb-3"
        :class="{ solved: resolved.has(`${name}.${key}`) }"
      >
        <v-card-title class="conflict-title text-body-1">
          <span class="conflict-key">{{ label(name, key, info) }}</span>
          <v-btn
            v-if="resolved.has(`${name}.${key}`)"
            size="small"
            variant="text"
            :prepend-icon="mdiUndoVariant"
            @click="unresolve(name, key)"
          >{{ $gettext('Revert') }}</v-btn>
        </v-card-title>
        <v-card-text class="pt-0">
          <div class="conflict-diff" :class="{ 'field-diff': changes[`${name}.${key}`]?.isObj }" role="group" :aria-label="$gettext('Changes')">
            <template v-if="changes[`${name}.${key}`]?.mineDiff || changes[`${name}.${key}`]?.isObj">
              <template v-for="side in changes[`${name}.${key}`].sides" :key="side.css">
                <template v-for="(entry, idx) in side.diff" :key="idx">
                  <span class="diff-symbol">{{ side.symbol }}</span>
                  <span v-if="changes[`${name}.${key}`].isObj" class="diff-label">{{ entry.label }}</span>
                  <div v-if="entry.words" :class="side.css"><span
                    v-for="(word, wi) in entry.words" :key="wi"
                    :class="{ 'highlight-removed': word.removed, 'highlight-added': word.added }"
                  >{{ word.value }}</span></div>
                  <div v-else :class="[side.css, entry.removed ? 'highlight-removed' : 'highlight-added']"
                  >{{ (entry.removed || entry.added)[0].value }}</div>
                </template>
              </template>
            </template>
            <template v-else>
              <template v-for="(entry, idx) in changes[`${name}.${key}`]?.theirsDiff" :key="idx">
                <template v-if="entry.removed">
                  <span class="diff-symbol">−</span>
                  <div class="removed" :aria-label="$gettext('Removed')"><span
                    v-for="(word, wi) in entry.removed" :key="wi"
                    :class="{ highlight: word.highlight }"
                  >{{ word.value }}</span></div>
                </template>
                <template v-if="entry.added">
                  <span class="diff-symbol">+</span>
                  <div class="added" :aria-label="$gettext('Added')"><span
                    v-for="(word, wi) in entry.added" :key="wi"
                    :class="{ highlight: word.highlight }"
                  >{{ word.value }}</span></div>
                </template>
              </template>
            </template>
            <template v-for="(field, idx) in changes[`${name}.${key}`]?.merged" :key="'g' + idx">
              <span class="diff-symbol">⇒</span>
              <span v-if="changes[`${name}.${key}`].isObj" class="diff-label">{{ field.label }}</span>
              <div class="merged">{{ field.value }}</div>
            </template>
          </div>
        </v-card-text>
        <v-card-actions v-if="!resolved.has(`${name}.${key}`)" class="justify-center">
          <v-btn
            color="error"
            class="option"
            variant="tonal"
            @click="resolve(name, key, info.overwritten)"
          >{{ $gettext('Use theirs') }}</v-btn>
          <v-btn
            color="success"
            class="option"
            variant="tonal"
            @click="resolve(name, key, info.current)"
          >{{ $gettext('Keep mine') }}</v-btn>
          <v-btn
            v-if="changes[`${name}.${key}`]?.merge != null"
            color="primary"
            class="option"
            variant="tonal"
            @click="merge(name, key)"
          >{{ $gettext('Merge both') }}</v-btn>
        </v-card-actions>
      </v-card>
    </template>
  </CmsDialog>
</template>

<style scoped>
.toolbar-counter {
  opacity: var(--v-medium-emphasis-opacity);
  margin-inline-end: 8px;
}

h3.section-header {
  font-size: inherit;
  font-weight: normal;
  margin-bottom: 12px;
  margin-top: 16px;
}

h3.section-header:first-child {
  margin-top: 0;
}

.conflict-card.solved {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.conflict-card.solved .conflict-key {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.conflict-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.conflict-key {
  font-weight: 600;
}

.conflict-diff {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 2px 8px;
  align-items: baseline;
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 6px;
  padding: 6px 12px;
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.5;
}

.conflict-diff.field-diff {
  grid-template-columns: auto auto 1fr;
}

.diff-label {
  font-weight: 500;
  opacity: var(--v-medium-emphasis-opacity);
  white-space: nowrap;
}

.diff-symbol {
  font-weight: 600;
  user-select: none;
}

.conflict-diff .added,
.conflict-diff .removed,
.conflict-diff .change-theirs,
.conflict-diff .change-mine,
.conflict-diff .merged {
  border-radius: 4px;
  padding: 4px 6px;
  min-width: 0;
}

.conflict-diff .added {
  background-color: rgba(var(--v-theme-success), 0.2);
}

.conflict-diff .removed {
  background-color: rgba(var(--v-theme-error), 0.2);
}

.conflict-diff .change-theirs {
  background-color: rgba(var(--v-theme-warning), 0.08);
}

.conflict-diff .change-mine {
  background-color: rgba(var(--v-theme-info), 0.08);
}

.conflict-diff .merged {
  background-color: rgba(var(--v-theme-primary), 0.2);
}

.removed .highlight,
:is(.change-theirs, .change-mine) .highlight-removed {
  background-color: rgba(var(--v-theme-error), 0.4);
}

.added .highlight,
:is(.change-theirs, .change-mine) .highlight-added {
  background-color: rgba(var(--v-theme-success), 0.4);
}

</style>
