/** @license MIT, https://opensource.org/license/mit */

<script>
import { required } from '../rules'
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  computed: {
    rules() {
      return [
        required(this.$gettext, this.config.required),
        (v) => !v || /^#[0-9A-F]{3,8}$/i.test(v) || this.$gettext(`Value must be a hex color code`)
      ]
    }
  }
}
</script>

<template>
  <!-- disabled instead of readonly: v-color-input still opens its picker when readonly;
       clearing emits an empty string so the default value isn't shown again -->
  <v-color-input
    :hint="config.hint && $pgettext('fh', config.hint)"
    :rules="rules"
    :error="hasError"
    :clearable="!readonly"
    :disabled="readonly"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event ?? '')"
  ></v-color-input>
</template>
