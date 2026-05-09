/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
let diffWordsFn = null

import { mdiClose } from '@mdi/js'
import { empty, stringify, url, srcset } from '../utils'

const SECTION_NAMES = ['meta', 'config', 'content']
const EMPTY_SPACE = { value: '\u00a0', highlight: false }

export default {
  props: {
    modelValue: { type: Boolean, required: true },
    readonly: { type: Boolean, required: false },
    current: { type: Object, required: true },
    load: { type: Function, required: true }
  },

  emits: ['update:modelValue', 'apply', 'use', 'revert'],

  setup() {
    return { mdiClose, url, srcset }
  },

  data: () => ({
    labels: null,
    list: [],
    latest: null,
    loading: false,
    unchecked: {}
  }),

  created() {
    this.labels = {
      data: this.$gettext('Fields'),
      meta: this.$gettext('Meta tags'),
      config: this.$gettext('Configuration'),
      content: this.$gettext('Content')
    }
  },

  beforeUnmount() {
    this.list = null
    this.latest = null
    this.unchecked = null
    this.labels = null
    this.diffCache = null
  },

  computed: {
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
      const seen = {}

      for (const key in version.data) {
        if (!SECTION_NAMES.includes(key)) seen[key] = true
      }
      for (const key in later?.data) {
        if (!SECTION_NAMES.includes(key)) seen[key] = true
      }
      for (const key in seen) {
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
        const sectionSeen = {}
        let hasChanges = false

        for (const key in vSec) sectionSeen[key] = true
        for (const key in lSec) sectionSeen[key] = true
        for (const key in sectionSeen) {
          if (JSON.stringify(vSec[key]) !== JSON.stringify(lSec[key]) && this.isChecked(idx, `${section}:${key}`)) {
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

    addedBlock(item) {
      return {
        title: this.blockLabel(item),
        fields: [{
          label: '', removed: [EMPTY_SPACE],
          added: [{ value: this.$gettext('Added'), highlight: true }]
        }]
      }
    },

    buildFieldDiffs(fields, prefix = '') {
      return fields.map(({ label, old: oldVal, new: newVal, rootKey }) => {
        const words = diffWordsFn(oldVal || '', newVal || '')
        const removed = words.filter(w => !w.added).map(w => ({ value: w.value, highlight: !!w.removed }))
        const added = words.filter(w => !w.removed).map(w => ({ value: w.value, highlight: !!w.added }))

        return {
          label,
          removed: removed.some(w => w.value) ? removed : [EMPTY_SPACE],
          added: added.some(w => w.value) ? added : [EMPTY_SPACE],
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
            blocks.push(this.removedBlock(aItem))
          } else {
            diffBlock(aItem, bItem)
          }
        }
        for (const [key, bItem] of bMap) {
          if (!aMap.has(key)) {
            blocks.push(this.addedBlock(bItem))
          }
        }
      } else {
        const len = Math.max(aArr.length, bArr.length)

        for (let i = 0; i < len; i++) {
          const aItem = aArr[i]
          const bItem = bArr[i]

          if (!aItem) {
            blocks.push(this.addedBlock(bItem))
          } else if (!bItem) {
            blocks.push(this.removedBlock(aItem))
          } else {
            diffBlock(aItem, bItem)
          }
        }
      }

      for (let i = 0; i < blocks.length; i++) {
        blocks[i].diffKey = `content:${i}`
      }

      return blocks
    },

    formatDate(dateStr) {
      return new Date(dateStr).toLocaleString(this.$vuetify.locale.current)
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
      const m1 = map1 || {}
      const m2 = map2 || {}
      const result = {}

      for (const key in m1) {
        if (!(key in m2)) {
          result[key] = Object.assign({}, m1[key], { css: 'added' })
        }
      }

      for (const key in m2) {
        if (!(key in m1)) {
          result[key] = Object.assign({}, m2[key], { css: 'removed' })
        }
      }

      return result
    },

    blockLabel(block) {
      if (!block?.type) return ''

      const title = block.data?.title || block.data?.text || ''

      return this.$pgettext('st', block.type) + (title ? ': ' + title.substring(0, 60) : '')
    },

    getChangedFields(a, b, rootKey = null) {
      const fields = []
      const seen = {}
      const aObj = a || {}
      const bObj = b || {}

      for (const k in aObj) seen[k] = true
      for (const k in bObj) seen[k] = true

      for (const k in seen) {
        const aVal = aObj[k]
        const bVal = bObj[k]

        if (JSON.stringify(aVal) === JSON.stringify(bVal)) continue
        if (empty(aVal) && empty(bVal)) continue

        const rk = rootKey || k
        const label = this.$pgettext('fn', k)

        if (aVal && bVal && typeof aVal === 'object' && !Array.isArray(aVal)
            && typeof bVal === 'object' && !Array.isArray(bVal)) {
          for (const f of this.getChangedFields(aVal, bVal, rk)) {
            f.label = `${label} › ${f.label}`
            fields.push(f)
          }
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

      for (const k in a) {
        if (JSON.stringify(a[k]) !== JSON.stringify(b[k]) && !(empty(a[k]) && empty(b[k]))) return true
      }

      for (const k in b) {
        if (!(k in a) && !empty(b[k])) return true
      }

      return false
    },

    laterVersion(idx) {
      return idx === 0 ? this.latest : this.versions[idx - 1]
    },

    removedBlock(item) {
      return { title: this.blockLabel(item), fields: [{ label: '', removed: [{ value: this.$gettext('Removed'), highlight: true }], added: [EMPTY_SPACE] }] }
    },

    reset() {
      this.list = []
      this.latest = null
      this.unchecked = {}
      this.diffCache = {}
    },

    sectionDiffs(a, b) {
      const result = {}
      const dataA = {}
      const dataB = {}

      for (const k in a) {
        if (!SECTION_NAMES.includes(k)) dataA[k] = a[k]
      }

      for (const k in b) {
        if (!SECTION_NAMES.includes(k)) dataB[k] = b[k]
      }

      const dataFields = this.getChangedFields(dataA, dataB)

      if (dataFields.length) result.data = Object.freeze(this.buildFieldDiffs(dataFields, 'data'))

      for (const section of ['meta', 'config']) {
        const fields = this.getChangedFields(a[section] || {}, b[section] || {})

        if (fields.length) result[section] = Object.freeze(this.buildFieldDiffs(fields, section))
      }

      const contentBlocks = this.contentDiffs(a.content || [], b.content || [])

      if (contentBlocks.length) result.content = Object.freeze(contentBlocks)

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
      if (this.diffCache?.[idx]) return this.diffCache[idx]

      const version = this.versions[idx]
      const later = this.laterVersion(idx)

      if (!version?.data || !later?.data) return {}

      const result = Object.freeze(this.sectionDiffs(later.data, version.data))

      if (!this.diffCache) this.diffCache = {}

      const keys = Object.keys(this.diffCache)

      if (keys.length >= 3) {
        delete this.diffCache[keys[0]]
      }

      this.diffCache[idx] = result

      return result
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      async handler(val) {
        if (!val) {
          this.list = []
          this.latest = null
          this.diffCache = {}
          return
        }

        if (val && !this.latest) {
          this.loading = true

          if (!diffWordsFn) {
            const mod = await import('diff')
            diffWordsFn = mod.diffWords
          }

          this.load()
            .then((versions) => {
              this.latest = versions?.[0] || null
              this.list = Object.freeze(versions?.slice(1) || [])
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
    @afterLeave="reset(); $emit('update:modelValue', false)"
    max-width="1200"
    scrollable
  >
    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('History') }}</v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="$emit('update:modelValue', false)" />
      </v-toolbar>
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
                {{ formatDate(version.publish_at || version.created_at) }}
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
