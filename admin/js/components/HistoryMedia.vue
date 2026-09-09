/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose } from '@mdi/js'
import equal from 'fast-deep-equal'
import { filepairs } from '../history'
import { fileurl, filesrcset } from '../utils'

export default {
  props: {
    before: { type: Array, default: () => [] },
    after: { type: Array, default: () => [] },
    side: { type: String, default: null },
    beforeLabel: { type: String, required: true },
    afterLabel: { type: String, required: true }
  },

  setup() { return { fileurl, filesrcset, mdiClose } },
  data: () => ({ preview: null, activator: null, failed: {} }),

  computed: {
    rows() { return filepairs(this.before, this.after).map(row => ({ ...row, changes: this.changes(row) })) }
  },

  methods: {
    changes(row) {
      if (this.side || !row.before || !row.after) return []
      const values = [
        { key: 'name', label: this.$gettext('Filename') },
        { key: 'path', label: this.$gettext('File path') },
        { key: 'mime', label: this.$gettext('File type') }
      ].map(field => ({ ...field, before: row.before[field.key], after: row.after[field.key] }))
      const before = row.before.previews || {}, after = row.after.previews || {}
      for (const size of new Set([...Object.keys(before), ...Object.keys(after)])) {
        values.push({ key: 'preview:' + size, label: this.$gettext('Preview (%{size})', { size }), before: before[size], after: after[size] })
      }
      return values.filter(value => !equal(value.before, value.after))
    },

    positions(row) {
      return this.side ? [this.side] : row.kind === 'unchanged' ? ['after'] : ['before', 'after']
    },

    value(value) {
      return value == null ? this.$gettext('Not present') : value === '' ? this.$gettext('Empty') : value
    }
  }
}
</script>

<template>
  <div class="media-list">
    <div v-for="row in rows" :key="row.key" class="media-row" :class="{ unchanged: row.kind === 'unchanged' && !row.moved }"
      :aria-label="(row.after || row.before).name"
    >
      <div v-if="!side" class="media-status">
        <v-chip v-if="row.kind === 'added'" size="x-small" color="success" label>{{ $gettext('File added') }}</v-chip>
        <v-chip v-else-if="row.kind === 'removed'" size="x-small" color="error" label>{{ $gettext('File removed') }}</v-chip>
        <v-chip v-else-if="row.kind === 'changed'" size="x-small" label>{{ row.before.id !== row.after.id ? $gettext('File replaced') : $gettext('File changed') }}</v-chip>
        <span v-else-if="!row.moved">{{ $gettext('Unchanged') }}</span>
        <span v-if="row.moved">{{ $gettext('Moved from position %{from} to %{to}', { from: row.from + 1, to: row.to + 1 }) }}</span>
      </div>
      <dl v-if="row.changes.length" class="media-changes">
        <div v-for="change in row.changes" :key="change.key">
          <dt>{{ change.label }}</dt>
          <dd><span :aria-label="beforeLabel">{{ value(change.before) }}</span><span aria-hidden="true"> → </span><span :aria-label="afterLabel">{{ value(change.after) }}</span></dd>
        </div>
      </dl>
      <div class="media-pair" :class="{ 'single-media': positions(row).length === 1 }">
        <div v-for="position in positions(row)" :key="position" class="media-side">
          <div v-if="!side && row.kind !== 'unchanged'" class="media-label">{{ position === 'before' ? beforeLabel : afterLabel }}</div>
          <div v-if="!row[position]" class="empty-media">{{ $gettext('No media') }}</div>
          <figure v-else class="file" :class="side || row.kind === 'unchanged' ? '' : position === 'before' ? 'removed' : 'added'">
            <figcaption>{{ row[position].name }}</figcaption>
            <button v-if="row[position].mime?.startsWith('image/')" class="media-zoom"
              :aria-label="$gettext('Enlarge %{name}', { name: row[position].name })" @click="activator = $event.currentTarget; preview = { file: row[position], label: position === 'before' ? beforeLabel : afterLabel }"
            >
              <v-img v-if="fileurl(row[position], Object.values(row[position].previews || {})[0] ?? row[position].path)"
                :srcset="filesrcset(row[position])" :src="fileurl(row[position], Object.values(row[position].previews || {})[0] ?? row[position].path)"
                :alt="row[position].name" height="150" draggable="false" loading="lazy"
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
          <p class="media-label">{{ preview.label }} · {{ preview.file.name }}</p>
          <v-img v-if="fileurl(preview.file)" :src="fileurl(preview.file)" :alt="preview.file.name" height="60vh">
            <template #placeholder><div class="media-loading" role="status"><v-progress-circular indeterminate size="24" aria-hidden="true" />{{ $gettext('Loading preview') }}</div></template>
            <template #error><div class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div></template>
          </v-img>
          <div v-else class="media-error" role="status">{{ $gettext('Preview unavailable') }}</div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
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

.media-status {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 0.8rem;
  margin-bottom: 6px;
}

.media-side {
  min-width: 0;
}

.media-changes {
  margin-bottom: 8px;
  font-size: 0.8rem;
  overflow-wrap: anywhere;
}

.media-changes > div {
  margin-bottom: 4px;
}

.media-changes dt {
  font-weight: 600;
}

.media-changes dd {
  margin: 0;
  white-space: pre-wrap;
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

@media (max-width: 700px) {

  .media-pair {
    grid-template-columns: minmax(0, 1fr);
  }

}
</style>
