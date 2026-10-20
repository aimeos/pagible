/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * Label, actions, input component and hint of a single schema field
 * The default slot is rendered between the label and the input component
 */
import FieldActions from './FieldActions.vue'
import { hintTypes, protectTypes, toName } from '../fieldtypes'
import { TEXT_TYPES } from '../field'
import { mdiUndoVariant } from '@mdi/js'

export default {
  components: { FieldActions },

  props: {
    field: { type: Object, required: true },
    code: { type: String, required: true },
    label: { type: String, default: '' },
    modelValue: { default: undefined },
    rel: { default: undefined },
    context: { type: Object, default: () => ({}) },
    assets: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    dirty: { type: Boolean, default: false },
    prompt: { type: String, default: '' }
  },

  emits: ['addFile', 'error', 'removeFile', 'revert', 'update:modelValue', 'update:rel'],

  setup() {
    return {
      mdiUndoVariant,
      textTypes: TEXT_TYPES
    }
  },

  computed: {
    name() {
      return toName(this.field.type)
    },

    hidden() {
      return this.field.type === 'hidden'
    },

    // fields showing the label and the actions themselves
    protect() {
      return protectTypes.has(this.name)
    },

    showHint() {
      return this.field.hint && !this.hidden && !hintTypes.has(this.name)
    },

    textType() {
      return this.textTypes.includes(this.field.type)
    }
  }
}
</script>

<template>
  <div v-if="!hidden && !protect" class="label">
    {{ label }}
    <div v-if="!readonly && (textType || dirty)" class="actions">
      <FieldActions
        v-if="textType"
        :code="code"
        :field="field"
        :value="modelValue"
        :context="context"
        :prompt="prompt"
        @update="$emit('update:modelValue', $event)"
      />
      <v-btn
        v-if="dirty"
        :title="$gettext('Revert')"
        @click="$emit('revert')"
        :icon="mdiUndoVariant"
        variant="text"
      />
    </div>
  </div>
  <slot></slot>
  <component
    :is="name"
    :key="field.type"
    :aria-label="label"
    :context="context"
    :assets="assets"
    :config="field"
    :label="protect ? label : null"
    :readonly="readonly"
    :modelValue="modelValue"
    v-bind="field.rel ? { rel } : {}"
    v-on="field.rel ? { 'update:rel': value => $emit('update:rel', value) } : {}"
    @addFile="$emit('addFile', $event)"
    @removeFile="$emit('removeFile', $event)"
    @update:modelValue="$emit('update:modelValue', $event)"
    @error="$emit('error', $event)"
  >
    <template v-if="protect" #label>
      <v-btn
        v-if="!readonly && dirty"
        :title="$gettext('Revert')"
        @click="$emit('revert')"
        :icon="mdiUndoVariant"
        variant="text"
      />
    </template>
  </component>
  <div v-if="showHint" class="v-input__details hint">
    <div class="v-messages">
      <div class="v-messages__message">{{ $pgettext('fh', field.hint) }}</div>
    </div>
  </div>
</template>

<style scoped>
.label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-transform: capitalize;
  font-weight: bold;
  margin-bottom: 4px;
}
</style>
