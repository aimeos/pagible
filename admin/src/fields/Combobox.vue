<script>
  import Autocomplete from './Autocomplete.vue'

  export default {
    extends: Autocomplete,
  }
</script>

<template>
  <v-combobox ref="field"
    :rules="[
      v => !config.required || !!v || `Value is required`,
    ]"
    :items="list"
    :loading="loading"
    :readonly="readonly"
    :clearable="!readonly"
    :no-data-text="config['empty-text'] || $gettext('No data available')"
    :placeholder="config.placeholder || ''"
    :multiple="config.multiple"
    :chips="config.multiple"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    @update:search="search($event)"
    @update:menu="search('')"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    item-title="label"
    item-value="value"
  ></v-combobox>
</template>
