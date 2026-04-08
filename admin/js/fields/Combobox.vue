/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import Autocomplete from './Autocomplete.vue'
import { debounce } from '../utils'

export default {
  extends: Autocomplete,

  setup() {
    return { debounce }
  }
}
</script>

<template>
  <v-combobox
    :error="hasError"
    :rules="rules"
    :items="list"
    :loading="loading"
    :readonly="readonly"
    :clearable="!readonly"
    :no-data-text="
      !loading
        ? config['empty-text'] || $gettext('No data available')
        : $gettext('Loading') + ' ...'
    "
    :placeholder="config.placeholder || ''"
    :multiple="config.multiple"
    :chips="config.multiple"
    :modelValue="modelValue ?? config.default ?? null"
    @update:modelValue="$emit('update:modelValue', $event)"
    @update:search="search($event)"
    @update:menu="search('')"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    item-title="label"
    item-value="value"
  ></v-combobox>
</template>
