/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { diffLines, diffWords } from 'diff'
import { mdiClose, mdiUndoVariant } from '@mdi/js'
import { empty, stringify } from '../utils'

export default {
  props: {
    modelValue: { type: Boolean, default: false },
    changed: { type: Object, default: null },
    targets: { type: Object, default: () => ({}) }
  },

  emits: ['update:modelValue', 'resolve'],

  setup() {
    return { mdiClose, mdiUndoVariant, stringify }
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
          const id = `${name}.${key}`
          const isObj = typeof info.overwritten === 'object' && typeof (info.current ?? info.overwritten) === 'object'

          const unwrap = (o) => o?.data ?? o

          let theirsDiff = null
          let mineDiff = null
          if (info.previous != null) {
            if (isObj) {
              theirsDiff = this.buildFieldDiffs(this.getChangedFields(unwrap(info.previous), unwrap(info.overwritten)))
              mineDiff = this.buildFieldDiffs(this.getChangedFields(unwrap(info.previous), unwrap(info.current)))
            } else {
              const fmtTheirs = this.formatDiffPair(info.previous, info.overwritten)
              theirsDiff = this.buildDiff(fmtTheirs.old, fmtTheirs.new)
              const fmtMine = this.formatDiffPair(info.previous, info.current)
              mineDiff = this.buildDiff(fmtMine.old, fmtMine.new)
            }
          } else {
            if (isObj) {
              theirsDiff = this.buildFieldDiffs(this.getChangedFields(unwrap(info.overwritten), unwrap(info.current)))
            } else {
              const fmt = this.formatDiffPair(info.overwritten, info.current)
              theirsDiff = this.buildDiff(fmt.old, fmt.new)
            }
          }

          const merge = info.merged ?? null

          let mergeFields = null
          if (merge != null) {
            if (typeof info.current === 'object' && typeof merge === 'object') {
              const hasData = info.current.data && merge.data
              const a = hasData ? (info.previous?.data || {}) : info.previous
              const b = hasData ? merge.data : merge
              mergeFields = this.getChangedFields(a, b).map(f => ({ label: f.label, value: f.new }))
            }
          }

          map[id] = { theirsDiff, mineDiff, merge, mergeFields, isObj }
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

    resolved(val) {
      if (val.size > 0 && val.size >= this.totalConflicts) {
        this.show = false
      }
    }
  },

  methods: {
    buildFieldDiffs(fields) {
      return fields.map(({ label, old: oldVal, new: newVal }) => {
        const words = diffWords(oldVal || '', newVal || '')
        return {
          label,
          words: words.map(w => ({ value: w.value, removed: !!w.removed, added: !!w.added })),
        }
      })
    },

    buildDiff(oldStr, newStr) {
      const lines = diffLines(oldStr, newStr)
      const diff = []
      let i = 0
      while (i < lines.length) {
        const part = lines[i]
        if (part.removed) {
          const next = lines[i + 1]
          if (next?.added) {
            const words = diffWords(part.value, next.value)
            diff.push({
              removed: words.filter(w => !w.added).map(w => ({ value: w.value, highlight: !!w.removed })),
              added: words.filter(w => !w.removed).map(w => ({ value: w.value, highlight: !!w.added })),
              words: words.map(w => ({ value: w.value, removed: !!w.removed, added: !!w.added })),
            })
            i += 2
          } else {
            diff.push({ removed: [{ value: part.value, highlight: true }], added: null })
            i++
          }
        } else if (part.added) {
          diff.push({ removed: null, added: [{ value: part.value, highlight: true }] })
          i++
        } else {
          i++
        }
      }
      return diff
    },

    formatDiffPair(a, b) {
      return { old: stringify(a), new: stringify(b) }
    },

    getChangedFields(a, b) {
      const fields = []

      for (const k of new Set([...Object.keys(a || {}), ...Object.keys(b || {})])) {
        const aVal = a?.[k]
        const bVal = b?.[k]
        if (JSON.stringify(aVal) === JSON.stringify(bVal)) continue
        if (empty(aVal) && empty(bVal)) continue

        const label = this.$pgettext('fn', k)

        if (aVal && bVal && typeof aVal === 'object' && !Array.isArray(aVal)
            && typeof bVal === 'object' && !Array.isArray(bVal)) {
          const sub = this.getChangedFields(aVal, bVal)
          fields.push(...sub.map(f => ({ ...f, label: `${label} › ${f.label}` })))
        } else {
          fields.push({ label, old: stringify(aVal), new: stringify(bVal) })
        }
      }

      return fields
    },

    merge(section, key) {
      const merged = this.changes[`${section}.${key}`]?.merge
      if (merged == null) return

      this.resolve(section, key, merged)
    },

    resolve(section, key, value) {
      const info = this.changed[section][key]
      const target = this.targets[section]

      if (target) {
        if (Array.isArray(target)) {
          const idx = target.findIndex((b) => (b.id || b.refid) === key)
          if (idx >= 0) {
            info.snapshot = target[idx]
            target[idx] = value
          }
        } else {
          info.snapshot = target[key]
          target[key] = value
        }
      }

      info.resolved = value
      this.resolved = new Set([...this.resolved, `${section}.${key}`])
      this.$emit('resolve')
    },

    unresolve(section, key) {
      const info = this.changed[section][key]
      const target = this.targets[section]

      if (target && 'snapshot' in info) {
        if (Array.isArray(target)) {
          const idx = target.findIndex((b) => (b.id || b.refid) === key)
          if (idx >= 0) target[idx] = info.snapshot
        } else {
          target[key] = info.snapshot
        }
        delete info.snapshot
      }

      delete info.resolved
      const next = new Set(this.resolved)
      next.delete(`${section}.${key}`)
      this.resolved = next
    },

    label(name, key, info) {
      const block = info.current || info.overwritten
      return block?.type
        ? this.$pgettext('st', block.type) + ': ' + this.title(block)
        : this.$pgettext('fn', key)
    },

    title(block) {
      if (!block || typeof block !== 'object' || !block.type) return null
      return (
        (
          block.data?.title ||
          block.data?.text ||
          Object.values(block.data || {})
            .map((v) => (v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null))
            .filter((v) => !!v)
            .join(' - ')
        ).substring(0, 100) ||
        this.$pgettext('st', block.type) ||
        ''
      )
    },

  }
}
</script>

<template>
  <v-dialog v-model="show" max-width="800" scrollable :aria-label="$gettext('Conflict resolution')">
    <v-card>
      <v-toolbar density="compact" color="error">
        <v-toolbar-title>
          {{ $gettext('Conflicts from %{editor}', { editor: changed?.editor }) }}
        </v-toolbar-title>
        <span class="toolbar-counter" aria-live="polite">
          {{ $gettext('%{count} / %{total}', { count: resolved.size, total: totalConflicts }) }}
        </span>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="show = false" />
      </v-toolbar>
      <v-card-text class="pa-4">
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
              <div v-if="changes[`${name}.${key}`]?.isObj" class="conflict-diff field-diff" role="group" :aria-label="$gettext('Changes')">
                <template v-for="(entry, idx) in changes[`${name}.${key}`]?.theirsDiff" :key="'t' + idx">
                  <span class="diff-symbol">−</span>
                  <span class="diff-label">{{ entry.label }}</span>
                  <div class="change-theirs"><span
                    v-for="(word, wi) in entry.words" :key="wi"
                    :class="{ 'highlight-removed': word.removed, 'highlight-added': word.added }"
                  >{{ word.value }}</span></div>
                </template>
                <template v-if="changes[`${name}.${key}`]?.mineDiff">
                  <template v-for="(entry, idx) in changes[`${name}.${key}`].mineDiff" :key="'m' + idx">
                    <span class="diff-symbol">+</span>
                    <span class="diff-label">{{ entry.label }}</span>
                    <div class="change-mine"><span
                      v-for="(word, wi) in entry.words" :key="wi"
                      :class="{ 'highlight-removed': word.removed, 'highlight-added': word.added }"
                    >{{ word.value }}</span></div>
                  </template>
                </template>
                <template v-if="changes[`${name}.${key}`]?.mergeFields">
                  <template v-for="(field, idx) in changes[`${name}.${key}`].mergeFields" :key="'g' + idx">
                    <span class="diff-symbol">⇒</span>
                    <span class="diff-label">{{ field.label }}</span>
                    <div class="merged">{{ field.value }}</div>
                  </template>
                </template>
              </div>
              <div v-else class="conflict-diff" role="group" :aria-label="$gettext('Changes')">
                <template v-for="(entry, idx) in changes[`${name}.${key}`]?.theirsDiff" :key="'t' + idx">
                  <template v-if="changes[`${name}.${key}`]?.mineDiff">
                    <template v-if="entry.words">
                      <span class="diff-symbol">−</span>
                      <div class="change-theirs"><span
                        v-for="(word, wi) in entry.words" :key="wi"
                        :class="{ 'highlight-removed': word.removed, 'highlight-added': word.added }"
                      >{{ word.value }}</span></div>
                    </template>
                    <template v-else-if="entry.removed">
                      <span class="diff-symbol">−</span>
                      <div class="change-theirs highlight-removed">{{ entry.removed[0].value }}</div>
                    </template>
                    <template v-else-if="entry.added">
                      <span class="diff-symbol">−</span>
                      <div class="change-theirs highlight-added">{{ entry.added[0].value }}</div>
                    </template>
                  </template>
                  <template v-else>
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
                <template v-if="changes[`${name}.${key}`]?.mineDiff">
                  <template v-for="(entry, idx) in changes[`${name}.${key}`].mineDiff" :key="'m' + idx">
                    <template v-if="entry.words">
                      <span class="diff-symbol">+</span>
                      <div class="change-mine"><span
                        v-for="(word, wi) in entry.words" :key="wi"
                        :class="{ 'highlight-removed': word.removed, 'highlight-added': word.added }"
                      >{{ word.value }}</span></div>
                    </template>
                    <template v-else-if="entry.removed">
                      <span class="diff-symbol">+</span>
                      <div class="change-mine highlight-removed">{{ entry.removed[0].value }}</div>
                    </template>
                    <template v-else-if="entry.added">
                      <span class="diff-symbol">+</span>
                      <div class="change-mine highlight-added">{{ entry.added[0].value }}</div>
                    </template>
                  </template>
                </template>
                <template v-if="changes[`${name}.${key}`]?.merge != null && !changes[`${name}.${key}`]?.isObj">
                  <span class="diff-symbol">⇒</span>
                  <div class="merged">{{ stringify(changes[`${name}.${key}`].merge) }}</div>
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
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.toolbar-counter {
  opacity: 0.85;
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
  border-color: rgba(var(--v-theme-on-surface), 0.08);
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.conflict-card.solved .conflict-key {
  color: rgba(var(--v-theme-on-surface), 0.6);
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
  opacity: 0.7;
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
  background-color: rgba(var(--v-theme-error), 0.08);
}

.conflict-diff .change-mine {
  background-color: rgba(var(--v-theme-info), 0.08);
}

.conflict-diff .merged {
  background-color: rgba(var(--v-theme-primary), 0.2);
}

.removed .highlight {
  background-color: rgba(var(--v-theme-error), 0.4);
}

.added .highlight {
  background-color: rgba(var(--v-theme-success), 0.4);
}

.change-theirs .highlight-removed,
.change-mine .highlight-removed {
  background-color: rgba(var(--v-theme-error), 0.4);
}

.change-theirs .highlight-added,
.change-mine .highlight-added {
  background-color: rgba(var(--v-theme-success), 0.4);
}

</style>
