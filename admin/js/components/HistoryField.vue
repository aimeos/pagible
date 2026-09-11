/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose } from '@mdi/js'
import { filepairs, words } from '../history'
import { fileurl, filesrcset } from '../utils'

export default {
  props: {
    field: { type: Object, required: true },
    rawDetails: { type: Boolean, default: true }
  },

  setup() { return { fileurl, filesrcset, mdiClose } },
  data: () => ({ preview: null, activator: null, failed: {} }),

  computed: {
    rows() {
      return filepairs(this.field.media?.before || [], this.field.media?.after || [])
    },

    values() {
      const raw = value => typeof this.field.before !== typeof this.field.after && typeof value === 'string'
        ? JSON.stringify(value) : this.raw(value)
      const before = raw(this.field.before), after = raw(this.field.after)
      const parts = words(before, after)
      return { before: parts.filter(part => !part.added), after: parts.filter(part => !part.removed) }
    }
  },

  methods: {
    name(file) {
      return file.name || file.path?.split('/').pop() || '---'
    },

    positions(row) {
      return row.kind === 'unchanged' && !row.moved ? ['after'] : ['before', 'after']
    },

    raw(value) {
      return value === undefined ? '---' : typeof value === 'string' ? value : JSON.stringify(value, null, 2)
    },

    space(part) {
      if (!(part.added || part.removed) || !/^[ \t\r\n\u00a0]+$/.test(part.value)) return undefined
      return part.value.replace(/[ \t\r\n\u00a0]/g, value => ({ ' ': '·', '\t': '⇥', '\r': '␍', '\n': '↵\n', '\u00a0': '⍽' })[value])
    }
  }
}
</script>

<template>
  <div class="field-comparison">
    <div v-if="rows.length" class="media-list">
      <div v-for="row in rows" :key="row.key" class="media-row" :aria-label="name(row.after || row.before)">
        <div class="media-pair" :class="{ 'single-media': positions(row).length === 1 }">
          <div v-for="position in positions(row)" :key="position" class="media-side">
            <div v-if="row.kind !== 'unchanged' || row.moved" class="media-label">
              {{ position === 'before' ? $gettext('Previous value') : $gettext('Current value') }}
            </div>
            <div v-if="!row[position]" class="empty-media">---</div>
            <figure v-else class="file" :class="row.kind === 'unchanged' ? '' : position === 'before' ? 'removed' : 'added'">
              <figcaption>{{ name(row[position]) }}</figcaption>
              <button v-if="row[position].mime?.startsWith('image/')" class="media-zoom"
                :aria-label="$gettext('Enlarge %{name}', { name: name(row[position]) })" @click="activator = $event.currentTarget; preview = { file: row[position], position }"
              >
                <v-img v-if="fileurl(row[position], Object.values(row[position].previews || {})[0] ?? row[position].path)"
                  :srcset="filesrcset(row[position])" :src="fileurl(row[position], Object.values(row[position].previews || {})[0] ?? row[position].path)"
                  :alt="name(row[position])" height="150" draggable="false" loading="lazy"
                >
                  <template #placeholder><div class="media-loading" role="status"><v-progress-circular indeterminate size="24" aria-hidden="true" />{{ $gettext('Loading preview') }}</div></template>
                  <template #error><div class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div></template>
                </v-img>
                <div v-else class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div>
              </button>
              <component v-else-if="/^(audio|video)\//.test(row[position].mime)" :is="row[position].mime.split('/')[0]"
                :src="fileurl(row[position])" crossorigin="anonymous" preload="none" controls
                @error="failed[fileurl(row[position])] = true" @loadeddata="delete failed[fileurl(row[position])]" />
              <div v-if="failed[fileurl(row[position])]" class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div>
            </figure>
          </div>
        </div>
      </div>
      <v-dialog :activator="activator" :open-on-click="false" :model-value="!!preview" @update:model-value="!$event && (preview = null)" max-width="1000" :aria-label="$gettext('Image preview')">
        <v-card v-if="preview">
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Image preview') }}</v-toolbar-title>
            <v-btn :icon="mdiClose" :aria-label="$gettext('Close preview')" @click="preview = null" />
          </v-toolbar>
          <v-card-text>
            <p class="media-label">
              {{ preview.position === 'before' ? $gettext('Previous value') : $gettext('Current value') }} · {{ name(preview.file) }}
            </p>
            <v-img v-if="fileurl(preview.file)" :src="fileurl(preview.file)" :alt="name(preview.file)" height="60vh">
              <template #placeholder><div class="media-loading" role="status"><v-progress-circular indeterminate size="24" aria-hidden="true" />{{ $gettext('Loading preview') }}</div></template>
              <template #error><div class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div></template>
            </v-img>
            <div v-else class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div>
          </v-card-text>
        </v-card>
      </v-dialog>
    </div>
    <details v-if="rawDetails" class="raw-details">
      <summary>{{ $gettext('Show raw details') }}</summary>
      <div class="diff-columns">
        <div v-for="(position, index) in ['before', 'after']" :key="position" :class="index ? 'change-new' : 'change-old'">
          <div class="side-label">{{ index ? $gettext('Current value') : $gettext('Previous value') }}</div>
          <pre><span v-for="(part, partIndex) in values[position]" :key="partIndex" :class="{ highlight: part[index ? 'added' : 'removed'], whitespace: space(part) }" :data-space="space(part)"><span>{{ part.value }}</span></span></pre>
        </div>
      </div>
    </details>
  </div>
</template>

<style scoped>
.field-comparison {
  min-width: 0;
}

.media-pair {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
}

.single-media {
  grid-template-columns: minmax(0, 1fr);
}

.media-row + .media-row {
  margin-top: 12px;
}

.media-side {
  min-width: 0;
}

.media-loading, .media-error {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  min-height: 150px;
  height: 100%;
  padding: 12px;
  font-size: 0.875rem;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.file > .media-error {
  min-height: 0;
}

.media-label {
  font-size: 0.75rem;
  font-weight: 600;
  overflow-wrap: anywhere;
  margin-bottom: 6px;
}

.empty-media {
  font-size: 0.875rem;
  opacity: 0.7;
}

.file {
  min-width: 0;
  padding: 8px;
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  margin-bottom: 8px;
}

.file figcaption {
  font-size: 0.8rem;
  overflow-wrap: anywhere;
  margin-bottom: 6px;
}

.file.removed {
  border-color: rgba(var(--v-theme-error), 0.5);
}

.file.added {
  border-color: rgba(var(--v-theme-success), 0.5);
}

.file video, .file audio {
  max-width: 100%;
  max-height: 150px;
}

.media-zoom {
  display: block;
  width: 100%;
  cursor: zoom-in;
  border: 0;
  background: transparent;
  color: inherit;
}

.media-zoom:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
}

.raw-details {
  margin-top: 8px;
  font-size: 0.85rem;
}

.raw-details summary {
  cursor: pointer;
  padding: 6px 0;
}

.diff-columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
}

.change-old, .change-new {
  min-width: 0;
  padding: 8px;
  border-radius: 4px;
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

.side-label {
  font-size: 0.75rem;
  font-weight: 600;
  margin-bottom: 4px;
}

pre {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  line-height: 1.6;
}

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
</style>
