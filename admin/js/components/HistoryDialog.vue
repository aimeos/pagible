/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { diffWords } from 'diff'
import { mdiHistory, mdiClose } from '@mdi/js'
import { empty, stringify, url, srcset } from '../utils'

export default {
  props: {
    modelValue: { type: Boolean, required: true },
    readonly: { type: Boolean, required: false },
    current: { type: Object, required: true },
    load: { type: Function, required: true }
  },

  emits: ['update:modelValue', 'apply', 'use', 'revert'],

  setup() {
    return { mdiHistory, mdiClose, url, srcset }
  },

  data: () => ({
    list: [],
    latest: null,
    loading: false,
    unchecked: {}
  }),

  computed: {
    labels() {
      return {
        data: this.$gettext('Fields'),
        meta: this.$gettext('Meta tags'),
        config: this.$gettext('Configuration'),
        content: this.$gettext('Content')
      }
    },

    currentDiffs() {
      if (!this.latest || !this.hasCurrentChanges) return {}
      return this.sectionDiffs(this.latest.data || {}, this.current.data || {})
    },

    hasCurrentChanges() {
      return this.latest && this.isModified(this.latest, this.current)
    },

    versions() {
      return this.list.filter((v) => {
        return this.isModified(v, this.current) || v.published || v.publish_at
      })
    }
  },

  methods: {
    apply(idx) {
      const isCurrent = idx === 'current'
      const version = isCurrent ? this.latest : this.versions[idx]
      const later = isCurrent ? this.current : this.laterVersion(idx)
      const changes = {}
      const sectionNames = ['meta', 'config', 'content']

      for (const key of new Set([
        ...Object.keys(version.data || {}).filter(k => !sectionNames.includes(k)),
        ...Object.keys(later?.data || {}).filter(k => !sectionNames.includes(k))
      ])) {
        if (JSON.stringify(version.data?.[key]) !== JSON.stringify(later?.data?.[key])
            && this.isChecked(idx, `data:${key}`)) {
          changes[key] = version.data[key]
        }
      }

      for (const section of ['meta', 'config']) {
        const vSec = version.data?.[section] || {}
        const lSec = later?.data?.[section] || {}
        if (JSON.stringify(vSec) === JSON.stringify(lSec)) continue

        const merged = { ...lSec }
        let hasChanges = false
        for (const key of new Set([...Object.keys(vSec), ...Object.keys(lSec)])) {
          if (JSON.stringify(vSec[key]) !== JSON.stringify(lSec[key])
              && this.isChecked(idx, `${section}:${key}`)) {
            merged[key] = vSec[key]
            hasChanges = true
          }
        }
        if (hasChanges) changes[section] = merged
      }

      if (JSON.stringify(version.data?.content) !== JSON.stringify(later?.data?.content)) {
        const diffs = isCurrent ? this.currentDiffs : this.versionDiffs(idx)
        if ((diffs.content || []).some(block => this.isChecked(idx, block.diffKey))) {
          changes.content = version.data.content
        }
      }

      this.$emit('apply', changes)
    },

    buildFieldDiffs(fields, prefix = '') {
      return fields.map(({ label, old: oldVal, new: newVal, rootKey }) => {
        const words = diffWords(oldVal || '', newVal || '')
        const removed = words.filter(w => !w.added).map(w => ({ value: w.value, highlight: !!w.removed }))
        const added = words.filter(w => !w.removed).map(w => ({ value: w.value, highlight: !!w.added }))
        return {
          label,
          removed: removed.some(w => w.value) ? removed : [{ value: '\u00a0', highlight: false }],
          added: added.some(w => w.value) ? added : [{ value: '\u00a0', highlight: false }],
          diffKey: prefix ? `${prefix}:${rootKey}` : rootKey,
        }
      })
    },

    contentDiffs(aArr, bArr) {
      const blockKey = (item) => item?.id || item?.refid
      const hasKeys = aArr.some(blockKey) || bArr.some(blockKey)
      const blocks = []

      const diffBlock = (aItem, bItem) => {
        if (JSON.stringify(aItem) === JSON.stringify(bItem)) return
        const fields = this.buildFieldDiffs(this.getChangedFields(aItem?.data || aItem || {}, bItem?.data || bItem || {}))
        if (!fields.length) {
          const top = this.buildFieldDiffs(this.getChangedFields(
            { type: aItem?.type, group: aItem?.group },
            { type: bItem?.type, group: bItem?.group }
          ))
          if (top.length) blocks.push({ title: this.blockLabel(bItem || aItem), fields: top })
        } else {
          blocks.push({ title: this.blockLabel(bItem || aItem), fields })
        }
      }

      if (hasKeys) {
        const aMap = new Map(aArr.map(i => [blockKey(i), i]).filter(([k]) => k))
        const bMap = new Map(bArr.map(i => [blockKey(i), i]).filter(([k]) => k))

        for (const [key, aItem] of aMap) {
          const bItem = bMap.get(key)
          if (!bItem) {
            blocks.push({ title: this.blockLabel(aItem), fields: [{ label: '', removed: [{ value: this.$gettext('Removed'), highlight: true }], added: [{ value: '\u00a0', highlight: false }] }] })
          } else {
            diffBlock(aItem, bItem)
          }
        }
        for (const [key, bItem] of bMap) {
          if (!aMap.has(key)) {
            blocks.push({ title: this.blockLabel(bItem), fields: [{ label: '', removed: [{ value: '\u00a0', highlight: false }], added: [{ value: this.$gettext('Added'), highlight: true }] }] })
          }
        }
      } else {
        const len = Math.max(aArr.length, bArr.length)
        for (let i = 0; i < len; i++) {
          const aItem = aArr[i]
          const bItem = bArr[i]
          if (!aItem) {
            blocks.push({ title: this.blockLabel(bItem), fields: [{ label: '', removed: [{ value: '\u00a0', highlight: false }], added: [{ value: this.$gettext('Added'), highlight: true }] }] })
          } else if (!bItem) {
            blocks.push({ title: this.blockLabel(aItem), fields: [{ label: '', removed: [{ value: this.$gettext('Removed'), highlight: true }], added: [{ value: '\u00a0', highlight: false }] }] })
          } else {
            diffBlock(aItem, bItem)
          }
        }
      }

      return blocks.map((b, i) => ({ ...b, diffKey: `content:${i}` }))
    },

    diffKeys(idx) {
      const diffs = idx === 'current' ? this.currentDiffs : this.versionDiffs(idx)
      const keys = []
      for (const [name, entries] of Object.entries(diffs)) {
        if (name === 'content') {
          entries.forEach(block => keys.push(block.diffKey))
        } else {
          entries.forEach(entry => keys.push(entry.diffKey))
        }
      }
      return keys
    },

    filesdiff(map1, map2) {
      const keys1 = Object.keys(map1 || {})
      const keys2 = Object.keys(map2 || {})

      const only1 = keys1.filter((key) => !keys2.includes(key))
      const only2 = keys2.filter((key) => !keys1.includes(key))

      const diff1 = Object.fromEntries(
        Object.entries(map1 || {})
          .filter(([key]) => only1.includes(key))
          .map(([key, value]) => [key, { ...value, css: 'added' }])
      )
      const diff2 = Object.fromEntries(
        Object.entries(map2 || {})
          .filter(([key]) => only2.includes(key))
          .map(([key, value]) => [key, { ...value, css: 'removed' }])
      )

      return { ...diff1, ...diff2 }
    },

    blockLabel(block) {
      if (!block?.type) return ''
      const title = block.data?.title || block.data?.text || ''
      return this.$pgettext('st', block.type) + (title ? ': ' + title.substring(0, 60) : '')
    },

    getChangedFields(a, b, rootKey = null) {
      const fields = []

      for (const k of new Set([...Object.keys(a || {}), ...Object.keys(b || {})])) {
        const aVal = a?.[k]
        const bVal = b?.[k]
        if (JSON.stringify(aVal) === JSON.stringify(bVal)) continue
        if (empty(aVal) && empty(bVal)) continue

        const rk = rootKey || k
        const label = this.$pgettext('fn', k)

        if (aVal && bVal && typeof aVal === 'object' && !Array.isArray(aVal)
            && typeof bVal === 'object' && !Array.isArray(bVal)) {
          const sub = this.getChangedFields(aVal, bVal, rk)
          fields.push(...sub.map(f => ({ ...f, label: `${label} › ${f.label}` })))
        } else {
          fields.push({ label, old: stringify(aVal), new: stringify(bVal), rootKey: rk })
        }
      }

      return fields
    },

    isAllChecked(idx) {
      const prefix = `${idx}:`
      return !Object.keys(this.unchecked).some(k => k.startsWith(prefix))
    },

    isChecked(idx, diffKey) {
      return !this.unchecked[`${idx}:${diffKey}`]
    },

    isModified(v1, v2) {
      if (!v1 || !v2) return false
      const a = v1.data || {}
      const b = v2.data || {}
      for (const k of new Set([...Object.keys(a), ...Object.keys(b)])) {
        if (JSON.stringify(a[k]) !== JSON.stringify(b[k]) && !(empty(a[k]) && empty(b[k]))) return true
      }
      return false
    },

    laterVersion(idx) {
      return idx === 0 ? this.latest : this.versions[idx - 1]
    },

    reset() {
      this.list = []
      this.latest = null
    },

    sectionDiffs(a, b) {
      const sections = ['meta', 'config', 'content']
      const result = {}

      const dataA = Object.fromEntries(Object.entries(a).filter(([k]) => !sections.includes(k)))
      const dataB = Object.fromEntries(Object.entries(b).filter(([k]) => !sections.includes(k)))
      const dataFields = this.getChangedFields(dataA, dataB)
      if (dataFields.length) result.data = this.buildFieldDiffs(dataFields, 'data')

      for (const section of ['meta', 'config']) {
        const fields = this.getChangedFields(a[section] || {}, b[section] || {})
        if (fields.length) result[section] = this.buildFieldDiffs(fields, section)
      }

      const contentBlocks = this.contentDiffs(a.content || [], b.content || [])
      if (contentBlocks.length) result.content = contentBlocks

      return result
    },

    toggleAll(idx) {
      if (this.isAllChecked(idx)) {
        for (const key of this.diffKeys(idx)) {
          this.unchecked[`${idx}:${key}`] = true
        }
      } else {
        for (const k of Object.keys(this.unchecked)) {
          if (k.startsWith(`${idx}:`)) delete this.unchecked[k]
        }
      }
    },

    toggleDiff(idx, diffKey) {
      const key = `${idx}:${diffKey}`
      if (this.unchecked[key]) {
        delete this.unchecked[key]
      } else {
        this.unchecked[key] = true
      }
    },

    versionDiffs(idx) {
      const version = this.versions[idx]
      const later = this.laterVersion(idx)
      if (!version?.data || !later?.data) return {}
      return this.sectionDiffs(later.data, version.data)
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        if (val && !this.latest) {
          this.loading = true

          this.load()
            .then((versions) => {
              this.list = versions || []
              this.latest = versions?.shift() || null
            })
            .finally(() => {
              this.loading = false
            })
        }
      }
    }
  }
}
</script>

<template>
  <v-dialog
    :aria-label="$gettext('History')"
    :modelValue="modelValue"
    @afterLeave="$emit('update:modelValue', false)"
    max-width="1200"
    scrollable
  >
    <v-card :prepend-icon="mdiHistory">
      <template v-slot:append>
        <v-btn
          :aria-label="$gettext('Close')"
          @click="$emit('update:modelValue', false)"
          :icon="mdiClose"
          variant="text"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('History') }}
      </template>

      <v-divider></v-divider>

      <v-card-text>
        <v-timeline side="end" align="start">
          <v-timeline-item v-if="loading" dot-color="grey-lighten-1" size="small" width="100%">
            <div class="loading" role="status">
              {{ $gettext('Loading') }}
              <svg
                class="spinner"
                aria-hidden="true"
                width="32"
                height="32"
                fill="currentColor"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
              >
                <circle class="spin1" cx="4" cy="12" r="3" />
                <circle class="spin1 spin2" cx="12" cy="12" r="3" />
                <circle class="spin1 spin3" cx="20" cy="12" r="3" />
              </svg>
            </div>
          </v-timeline-item>

          <v-timeline-item
            v-if="!loading && !(hasCurrentChanges || versions.length)"
            dot-color="grey-lighten-1"
            width="100%"
            size="small"
          >
            <span role="status">{{ $gettext('No changes') }}</span>
          </v-timeline-item>

          <v-timeline-item
            v-if="!loading && hasCurrentChanges"
            dot-color="blue"
            width="100%"
            size="small"
          >
            <v-card :elevation="2">
              <v-card-title>{{ $gettext('Current') }}</v-card-title>
              <v-card-text>
                <div class="version-diffs">
                  <template v-for="(entries, name) in currentDiffs" :key="name">
                    <h4 class="section-header">
                      <v-chip size="small" label>{{ labels[name] || name }}</v-chip>
                    </h4>
                    <template v-if="name === 'content'">
                      <template v-for="(block, bi) in entries" :key="bi">
                        <div role="group" :aria-label="block.title">
                          <div class="diff-block-title">{{ block.title }}</div>
                          <div v-for="(entry, ei) in block.fields" :key="ei" class="diff-group" role="group" :aria-label="entry.label">
                            <div class="diff-row change-old" :aria-label="$gettext('Removed')">
                              <span class="diff-symbol" aria-hidden="true">−</span>
                              <span class="diff-label">{{ entry.label }}</span>
                              <div class="diff-text"><span
                                v-for="(word, wi) in entry.removed" :key="wi"
                                :class="{ highlight: word.highlight }"
                              >{{ word.value }}</span></div>
                            </div>
                            <div class="diff-row change-new" :aria-label="$gettext('Added')">
                              <span class="diff-symbol" aria-hidden="true">+</span>
                              <span class="diff-label">{{ entry.label }}</span>
                              <div class="diff-text"><span
                                v-for="(word, wi) in entry.added" :key="wi"
                                :class="{ highlight: word.highlight }"
                              >{{ word.value }}</span></div>
                            </div>
                          </div>
                        </div>
                        <v-checkbox
                          class="diff-check"
                          :model-value="isChecked('current', block.diffKey)"
                          @update:model-value="toggleDiff('current', block.diffKey)"
                          :aria-label="block.title"
                          hide-details
                          density="compact"
                        />
                      </template>
                    </template>
                    <template v-else>
                      <template v-for="(entry, ei) in entries" :key="ei">
                        <div class="diff-group" role="group" :aria-label="entry.label">
                          <div class="diff-row change-old" :aria-label="$gettext('Removed')">
                            <span class="diff-symbol" aria-hidden="true">−</span>
                            <span class="diff-label">{{ entry.label }}</span>
                            <div class="diff-text"><span
                              v-for="(word, wi) in entry.removed" :key="wi"
                              :class="{ highlight: word.highlight }"
                            >{{ word.value }}</span></div>
                          </div>
                          <div class="diff-row change-new" :aria-label="$gettext('Added')">
                            <span class="diff-symbol" aria-hidden="true">+</span>
                            <span class="diff-label">{{ entry.label }}</span>
                            <div class="diff-text"><span
                              v-for="(word, wi) in entry.added" :key="wi"
                              :class="{ highlight: word.highlight }"
                            >{{ word.value }}</span></div>
                          </div>
                        </div>
                        <v-checkbox
                          class="diff-check"
                          :model-value="isChecked('current', entry.diffKey)"
                          @update:model-value="toggleDiff('current', entry.diffKey)"
                          :aria-label="entry.label"
                          hide-details
                          density="compact"
                        />
                      </template>
                    </template>
                  </template>
                  <div class="media-list">
                    <div
                      v-for="file of filesdiff(current.files, latest?.files)"
                      :key="file.id"
                      :class="file.css"
                      class="file"
                    >
                      <v-img
                        v-if="file.mime?.startsWith('image/')"
                        :srcset="srcset(file.previews)"
                        :src="url(file.path)"
                        :alt="file.name"
                        draggable="false"
                        loading="lazy"
                      />
                      <video
                        v-else-if="file.mime?.startsWith('video/')"
                        :poster="url(Object.values(file.previews).shift())"
                        :src="url(file.path)"
                        crossorigin="anonymous"
                        draggable="false"
                        loading="lazy"
                        controls
                      />
                      <div v-else-if="file.mime?.startsWith('audio/')">
                        <audio
                          :src="url(file.path)"
                          crossorigin="anonymous"
                          draggable="false"
                          loading="lazy"
                          controls
                        />
                        {{ file.name }}
                      </div>
                      <div v-else>
                        {{ file.name }}
                      </div>
                    </div>
                  </div>
                  <div v-if="!readonly" class="diff-actions">
                    <v-btn variant="tonal" color="success" @click.stop="$emit('revert', latest)">
                      {{ $gettext('Revert') }}
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="tonal" color="info" @click.stop="apply('current')">
                      {{ $gettext('Apply changes') }}
                    </v-btn>
                  </div>
                  <v-checkbox
                    v-if="!readonly"
                    class="diff-check"
                    :model-value="isAllChecked('current')"
                    @update:model-value="toggleAll('current')"
                    :aria-label="$gettext('Toggle all')"
                    hide-details
                    density="compact"
                  />
                </div>
              </v-card-text>
            </v-card>
          </v-timeline-item>

          <v-timeline-item
            v-for="(version, idx) in versions"
            :key="idx"
            :dot-color="version.published ? 'success' : 'grey-lighten-1'"
            :class="{ publish: version.publish_at }"
            width="100%"
            size="small"
          >
            <v-card :elevation="2">
              <v-card-title>
                {{
                  new Date(version.publish_at || version.created_at).toLocaleString(
                    $vuetify.locale.current
                  )
                }}
              </v-card-title>
              <v-card-subtitle>
                {{ version.editor }}
              </v-card-subtitle>
              <v-card-text>
                <div class="version-diffs">
                  <template v-for="(entries, name) in versionDiffs(idx)" :key="name">
                    <h4 class="section-header">
                      <v-chip size="small" label>{{ labels[name] || name }}</v-chip>
                    </h4>
                    <template v-if="name === 'content'">
                      <template v-for="(block, bi) in entries" :key="bi">
                        <div role="group" :aria-label="block.title">
                          <div class="diff-block-title">{{ block.title }}</div>
                          <div v-for="(entry, didx) in block.fields" :key="didx" class="diff-group" role="group" :aria-label="entry.label">
                            <div class="diff-row change-old" :aria-label="$gettext('Removed')">
                              <span class="diff-symbol" aria-hidden="true">−</span>
                              <span class="diff-label">{{ entry.label }}</span>
                              <div class="diff-text"><span
                                v-for="(word, wi) in entry.removed" :key="wi"
                                :class="{ highlight: word.highlight }"
                              >{{ word.value }}</span></div>
                            </div>
                            <div class="diff-row change-new" :aria-label="$gettext('Added')">
                              <span class="diff-symbol" aria-hidden="true">+</span>
                              <span class="diff-label">{{ entry.label }}</span>
                              <div class="diff-text"><span
                                v-for="(word, wi) in entry.added" :key="wi"
                                :class="{ highlight: word.highlight }"
                              >{{ word.value }}</span></div>
                            </div>
                          </div>
                        </div>
                        <v-checkbox
                          class="diff-check"
                          :model-value="isChecked(idx, block.diffKey)"
                          @update:model-value="toggleDiff(idx, block.diffKey)"
                          :aria-label="block.title"
                          hide-details
                          density="compact"
                        />
                      </template>
                    </template>
                    <template v-else>
                      <template v-for="(entry, didx) in entries" :key="didx">
                        <div class="diff-group" role="group" :aria-label="entry.label">
                          <div class="diff-row change-old" :aria-label="$gettext('Removed')">
                            <span class="diff-symbol" aria-hidden="true">−</span>
                            <span class="diff-label">{{ entry.label }}</span>
                            <div class="diff-text"><span
                              v-for="(word, wi) in entry.removed" :key="wi"
                              :class="{ highlight: word.highlight }"
                            >{{ word.value }}</span></div>
                          </div>
                          <div class="diff-row change-new" :aria-label="$gettext('Added')">
                            <span class="diff-symbol" aria-hidden="true">+</span>
                            <span class="diff-label">{{ entry.label }}</span>
                            <div class="diff-text"><span
                              v-for="(word, wi) in entry.added" :key="wi"
                              :class="{ highlight: word.highlight }"
                            >{{ word.value }}</span></div>
                          </div>
                        </div>
                        <v-checkbox
                          class="diff-check"
                          :model-value="isChecked(idx, entry.diffKey)"
                          @update:model-value="toggleDiff(idx, entry.diffKey)"
                          :aria-label="entry.label"
                          hide-details
                          density="compact"
                        />
                      </template>
                    </template>
                  </template>
                  <div class="media-list">
                    <div
                      v-for="file of filesdiff(version.files, laterVersion(idx)?.files)"
                      :key="file.id"
                      :class="file.css"
                      class="file"
                    >
                      <v-img
                        v-if="file.mime?.startsWith('image/')"
                        :srcset="srcset(file.previews)"
                        :src="url(file.path)"
                        :alt="file.name"
                        draggable="false"
                        loading="lazy"
                      />
                      <video
                        v-else-if="file.mime?.startsWith('video/')"
                        :poster="url(Object.values(file.previews).shift())"
                        :src="url(file.path)"
                        crossorigin="anonymous"
                        draggable="false"
                        loading="lazy"
                        controls
                      />
                      <div v-else-if="file.mime?.startsWith('audio/')">
                        <audio
                          :src="url(file.path)"
                          crossorigin="anonymous"
                          draggable="false"
                          loading="lazy"
                          controls
                        />
                        {{ file.name }}
                      </div>
                      <div v-else>
                        {{ file.name }}
                      </div>
                    </div>
                  </div>
                  <div v-if="!readonly" class="diff-actions">
                    <v-btn variant="tonal" color="success" @click.stop="$emit('use', version)">
                      {{ $gettext('Revert to version') }}
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="tonal" color="info" @click.stop="apply(idx)">
                      {{ $gettext('Apply changes') }}
                    </v-btn>
                  </div>
                  <v-checkbox
                    v-if="!readonly"
                    class="diff-check"
                    :model-value="isAllChecked(idx)"
                    @update:model-value="toggleAll(idx)"
                    :aria-label="$gettext('Toggle all')"
                    hide-details
                    density="compact"
                  />
                </div>
              </v-card-text>
            </v-card>
          </v-timeline-item>
        </v-timeline>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.v-timeline--vertical {
  grid-template-columns: 0 min-content auto;
}

.v-timeline-item__opposite {
  display: none;
}

.v-timeline-item.publish .v-card-title {
  color: rgb(var(--v-theme-success));
}

h4.section-header {
  font-size: inherit;
  font-weight: normal;
  margin-bottom: 8px;
  margin-top: 12px;
}

h4.section-header:first-child {
  margin-top: 0;
}

.version-diffs {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px 8px;
  align-items: center;
}

.version-diffs > .section-header {
  grid-column: 1 / -1;
}

.diff-block-title {
  font-weight: 600;
}

.diff-actions {
  display: flex;
  align-items: center;
  padding-top: 4px;
}

.diff-check {
  align-self: center;
  justify-self: center;
  min-height: 44px;
  min-width: 44px;
}

.diff-group {
  display: grid;
  grid-template-columns: auto auto 1fr;
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  padding: 6px 12px;
  gap: 2px 8px;
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.5;
}

.diff-row {
  display: grid;
  grid-template-columns: subgrid;
  grid-column: 1 / -1;
  align-items: baseline;
}

.diff-symbol {
  font-weight: 600;
  user-select: none;
}

.diff-label {
  font-weight: 500;
  opacity: 0.78;
  white-space: nowrap;
}

.diff-text {
  min-width: 0;
}

.change-old .diff-text {
  background-color: rgba(var(--v-theme-error), 0.08);
  border-radius: 4px;
  padding: 2px 6px;
}

.change-new .diff-text {
  background-color: rgba(var(--v-theme-success), 0.08);
  border-radius: 4px;
  padding: 2px 6px;
}

.change-old .highlight {
  background-color: rgba(var(--v-theme-error), 0.4);
}

.change-new .highlight {
  background-color: rgba(var(--v-theme-success), 0.4);
}

.v-timeline-item .media-list {
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  grid-column: 1 / -1;
  display: grid;
}

.v-timeline-item .file {
  border: 2px solid transparent;
  justify-content: center;
  align-items: center;
  position: relative;
  text-align: center;
  overflow: hidden;
  max-height: 150px;
  display: flex;
  margin: 4px;
}

.v-timeline-item .file.added {
  border: 2px dashed rgb(var(--v-theme-success));
}

.v-timeline-item .file.removed {
  border: 2px dashed rgb(var(--v-theme-error));
  opacity: 0.66;
}

.v-timeline-item .file video,
.v-timeline-item .file audio {
  width: 100%;
}
</style>
