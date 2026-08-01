/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiLock } from '@mdi/js'

export default {
  props: {
    disabled: { type: Boolean, default: false },
    labelled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    modelValue: { type: Boolean, default: false },
    name: { type: String, default: '' },
    locked: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false }
  },

  emits: ['update:modelValue'],

  setup() {
    return {
      mdiLock
    }
  }
}
</script>

<template>
  <div v-if="labelled || !readonly" class="field-protect label">
    <div class="field-name">
      <span class="field-label">
        <v-icon v-if="locked" :icon="mdiLock" class="field-lock" aria-hidden="true" />
        <span>{{ name }}</span>
      </span>
      <slot />
    </div>
    <label v-if="!readonly" :aria-busy="loading" class="protect">
      <span class="protect-label">{{ $gettext('Protect access') }}</span>
      <v-progress-circular
        v-if="loading"
        aria-hidden="true"
        color="primary"
        indeterminate
        size="24"
        width="2"
      />
      <v-switch
        v-else
        :disabled="disabled"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        color="primary"
        density="compact"
        hide-details
      />
    </label>
  </div>
</template>

<style scoped>
.field-protect {
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-transform: capitalize;
  font-weight: bold;
  margin-bottom: 4px;
  min-height: 48px;
}

.field-name,
.protect {
  flex: 1 1 50%;
}

.field-name {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
}

.field-label {
  display: flex;
  align-items: center;
  min-width: 0;
}

.field-lock {
  color: rgb(var(--v-theme-info));
  flex: 0 0 auto;
  margin-inline-end: 4px;
}

.protect {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}
</style>
