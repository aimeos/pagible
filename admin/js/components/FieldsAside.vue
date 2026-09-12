/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent } from 'vue'
import Fields from './Fields.vue'
import { useDrawerStore, useSchemaStore } from '../stores'
import { clone } from '../utils'
import {
  mdiCellphone,
  mdiClose,
  mdiMonitor,
  mdiSwapHorizontal,
  mdiTablet,
  mdiTableRowPlusBefore,
  mdiTableRowPlusAfter,
  mdiTrashCanOutline,
  mdiUndoVariant
} from '@mdi/js'

const SchemaDialog = defineAsyncComponent(() => import('./SchemaDialog.vue'))

export default {
  components: {
    Fields,
    SchemaDialog
  },

  props: {
    actions: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    element: { type: Object, required: true },
    assets: { type: Object, default: () => ({}) },
    previewSize: { type: String, default: 'computer' },
    saveCount: { type: Number, default: 0 },
    type: { type: String, default: 'content' }
  },

  emits: ['add-after', 'add-before', 'change', 'remove', 'update:previewSize'],

  setup() {
    const drawer = useDrawerStore()
    const schemas = useSchemaStore()

    return {
      drawer,
      schemas,
      mdiCellphone,
      mdiClose,
      mdiMonitor,
      mdiSwapHorizontal,
      mdiTablet,
      mdiTableRowPlusBefore,
      mdiTableRowPlusAfter,
      mdiTrashCanOutline,
      mdiUndoVariant
    }
  },

  data() {
    return {
      modified: false,
      original: null,
      vschemas: false
    }
  },

  computed: {
    open: {
      get() {
        return !!this.drawer.aside
      },

      set(value) {
        this.drawer.aside = value
      }
    },

    responsiveIcon() {
      return this.responsiveViews.find((item) => item.value === this.previewSize)?.icon || mdiMonitor
    },

    responsiveViews() {
      return [
        { icon: mdiCellphone, label: this.$gettext('Mobile'), value: 'mobile', width: '384 px' },
        { icon: mdiTablet, label: this.$gettext('Tablet'), value: 'tablet', width: '768 px' },
        { icon: mdiMonitor, label: this.$gettext('Computer'), value: 'computer', width: this.$gettext('Full width') }
      ]
    }
  },

  methods: {
    change() {
      this.modified = this.differs()
      this.element._changed = true
      this.$emit('change', this.element)
    },

    changeTo(item) {
      this.vschemas = false

      if (this.readonly || !item?.type || item.type === this.element.type) return

      this.element.type = item.type
      delete this.element._error
      this.change()
    },

    close() {
      if (this.element._changed) {
        this.$emit('change', this.element)
      }

      this.drawer.aside = false
    },

    differs() {
      return !!this.original && (
        this.element.type !== this.original.type ||
        JSON.stringify(this.element.data || {}) !== JSON.stringify(this.original.data || {}) ||
        JSON.stringify(this.element.files || []) !== JSON.stringify(this.original.files || [])
      )
    },

    fields(type) {
      if (!this.schemas[this.type] || !this.schemas[this.type][type]?.fields) {
        console.warn(`No definition of fields for "${type}" (${this.type}) schemas`)
        return []
      }

      return this.schemas.content[type]?.fields
    },

    revert() {
      if (this.readonly || !this.original) return

      const restored = clone(this.original)

      for (const key of Object.keys(this.element)) delete this.element[key]
      Object.assign(this.element, restored)

      this.modified = false
      this.$nextTick(() => this.$refs.fields?.resetDirty?.())
      this.$emit('change', this.element)
    },

    snapshot() {
      this.original = clone(this.element)
      this.modified = false
      this.vschemas = false
      this.$nextTick(() => this.$refs.fields?.resetDirty?.())
    },

    updateFiles(files) {
      this.element.files = files
      this.change()
    }
  },

  watch: {
    element: {
      immediate: true,
      handler() {
        this.snapshot()
      }
    },

    saveCount() {
      this.snapshot()
    }
  }
}
</script>

<template>
  <v-navigation-drawer
    v-model="open"
    :aria-label="$gettext('Content Element')"
    mobile-breakpoint="md"
    location="end"
    tag="aside"
    width="480"
  >
    <v-toolbar class="element-toolbar" color="surface" density="compact">
      <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="close" />
      <v-spacer />
      <div class="element-actions">
        <v-btn
          v-if="actions"
          @click="$emit('add-before')"
          :title="$gettext('Add element before')"
          :icon="mdiTableRowPlusBefore"
          class="btn-add-before"
          variant="text"
        />
        <v-btn
          v-if="actions"
          @click="$emit('add-after')"
          :title="$gettext('Add element after')"
          :icon="mdiTableRowPlusAfter"
          class="btn-add-after"
          variant="text"
        />
        <v-btn
          v-if="actions"
          @click="$emit('remove')"
          :title="$gettext('Remove element')"
          :icon="mdiTrashCanOutline"
          class="btn-remove"
          variant="text"
        />
      </div>
      <v-spacer />
      <v-menu location="bottom end">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :aria-label="$gettext('Responsive preview')"
            :title="$gettext('Responsive preview')"
            :icon="responsiveIcon"
            class="btn-responsive"
            variant="text"
          />
        </template>
        <v-list density="compact">
          <v-list-item
            v-for="view in responsiveViews"
            :key="view.value"
            :active="previewSize === view.value"
            :prepend-icon="view.icon"
            :subtitle="view.width"
            :title="view.label"
            @click="$emit('update:previewSize', view.value)"
            class="preview-size-option"
          />
        </v-list>
      </v-menu>
    </v-toolbar>
    <div class="element-info">
      <span class="element-type">{{ $pgettext('st', element.type) }}</span>
      <v-spacer />
      <template v-if="actions && !readonly">
        <v-btn
          @click="vschemas = true"
          :title="$gettext('Change to')"
          :icon="mdiSwapHorizontal"
          class="btn-change-type"
          variant="text"
        />
        <v-btn
          @click="revert"
          :disabled="!modified"
          :title="$gettext('Revert')"
          :icon="mdiUndoVariant"
          class="btn-revert"
          variant="text"
        />
      </template>
    </div>
    <div class="fields">
      <Fields
        :key="`${element.id || ''}-${element.type}`"
        ref="fields"
        v-model:data="element.data"
        :files="element.files"
        :fields="fields(element.type)"
        :readonly="readonly"
        :type="element.type"
        :assets="assets"
        @error="element._error = $event"
        @change="change"
        @update:files="updateFiles"
      />
    </div>
    <SchemaDialog v-model="vschemas" :elements="false" @add="changeTo" />
  </v-navigation-drawer>
</template>

<style scoped>
.v-navigation-drawer {
  border-radius: 0 !important;
  border-start-start-radius: 8px !important;
  max-width: 100%;
  overflow: hidden;
}

.element-toolbar {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  position: sticky;
  top: 0;
  z-index: 2;
}

.element-actions {
  display: flex;
  flex: 0 0 auto;
}

.element-info {
  align-items: center;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  min-height: 48px;
  padding: 4px 8px 4px 16px;
}

.element-type {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  text-transform: capitalize;
  white-space: nowrap;
}

.fields {
  overflow-x: hidden;
  padding: 0 16px max(16px, env(safe-area-inset-bottom));
}

:deep(.v-navigation-drawer__content) {
  overscroll-behavior: contain;
}

@media (max-width: 959px) {
  .v-navigation-drawer {
    border-radius: 0 !important;
    width: 100% !important;
  }

  .element-toolbar {
    padding-left: max(2px, env(safe-area-inset-left));
    padding-right: max(2px, env(safe-area-inset-right));
  }

  .element-toolbar :deep(.v-btn) {
    min-height: 44px;
    min-width: 44px;
  }

  .element-info {
    padding-left: max(12px, env(safe-area-inset-left));
    padding-right: max(4px, env(safe-area-inset-right));
  }

  .element-info :deep(.v-btn) {
    min-height: 44px;
    min-width: 44px;
  }

  .fields {
    padding-inline: 12px;
  }

  .fields :deep(.item) {
    margin-block: 16px;
    padding-inline-start: 6px;
  }

  .fields :deep(.label) {
    flex-wrap: wrap;
    gap: 4px;
  }
}
</style>
