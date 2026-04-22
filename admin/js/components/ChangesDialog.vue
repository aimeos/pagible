/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { mdiClose } from '@mdi/js'

export default {
  props: {
    modelValue: { type: Boolean, default: false },
    changed: { type: Object, default: null }
  },

  emits: ['update:modelValue'],

  setup() {
    return { mdiClose }
  },

  computed: {
    labels() {
      return {
        data: this.$gettext('Fields'),
        meta: this.$gettext('Meta tags'),
        config: this.$gettext('Configuration'),
        content: this.$gettext('Content blocks')
      }
    },

    sections() {
      if (!this.changed) return {}
      const { editor, latest, ...sections } = this.changed
      return sections
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

  methods: {
    format(value) {
      if (value == null) return ''
      return typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
    },

    hasConflicts(section) {
      return Object.values(section).some((v) => v.overwritten)
    }
  }
}
</script>

<template>
  <v-dialog v-model="show" max-width="700" scrollable>
    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>
          {{ $gettext('Changes from %{editor}', { editor: changed?.editor }) }}
        </v-toolbar-title>
        <v-btn :icon="mdiClose" @click="show = false" />
      </v-toolbar>
      <v-card-text>
        <template v-for="(section, name) in sections" :key="name">
          <h3 :class="{ 'text-error': hasConflicts(section) }">{{ labels[name] || name }}</h3>
          <div
            v-for="(info, key) in section"
            :key="key"
            :class="info.overwritten ? 'conflict-item' : 'change-item'"
          >
            <div class="change-key">{{ key }}</div>
            <div v-if="info.overwritten" class="change-overwritten text-error">
              {{ format(info.overwritten) }}
            </div>
            <div class="change-previous">{{ format(info.previous) }}</div>
            <div class="change-arrow">&rarr;</div>
            <div class="change-current">{{ format(info.current) }}</div>
          </div>
        </template>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
h3 {
  margin-top: 16px;
  margin-bottom: 8px;
}

h3:first-child {
  margin-top: 0;
}

.change-item,
.conflict-item {
  display: grid;
  grid-template-columns: 120px 1fr auto 1fr;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  align-items: start;
}

.conflict-item {
  grid-template-columns: 120px 1fr;
  background: rgba(var(--v-theme-error), 0.05);
  padding: 6px 8px;
  border-radius: 4px;
}

.change-key {
  font-weight: 500;
  word-break: break-word;
}

.change-arrow {
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.change-previous {
  color: rgba(var(--v-theme-on-surface), 0.6);
  word-break: break-word;
  white-space: pre-wrap;
}

.change-current {
  word-break: break-word;
  white-space: pre-wrap;
}

.change-overwritten {
  grid-column: 2;
  word-break: break-word;
  white-space: pre-wrap;
  margin-bottom: 4px;
}
</style>
