/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiMenuDown, mdiSort } from '@mdi/js'

export default {
  props: {
    modelValue: { type: Object, default: () => ({}) },
    options: { type: Array, default: () => [] }
  },

  emits: ['update:modelValue'],

  setup() {
    return {
      mdiMenuDown,
      mdiSort
    }
  },

  computed: {
    labels() {
      return {
        Editor: this.$gettext('Editor'),
        Language: this.$gettext('Language'),
        Latest: this.$gettext('Latest'),
        'Latest edit': this.$gettext('Latest edit'),
        MIME: this.$gettext('MIME'),
        Name: this.$gettext('Name'),
        Oldest: this.$gettext('Oldest'),
        'Oldest edit': this.$gettext('Oldest edit'),
        Tree: this.$gettext('Tree'),
        Type: this.$gettext('Type'),
        Usage: this.$gettext('Usage')
      }
    },

    order() {
      const option = this.options.find((option) => {
        return option.column === this.modelValue?.column && option.order === this.modelValue?.order
      })

      return this.labels[option?.label] || option?.label || this.modelValue?.column || ''
    }
  },

  methods: {
    select(option) {
      this.$emit('update:modelValue', { column: option.column, order: option.order })
    }
  }
}
</script>

<template>
  <span class="btn-sort">
    <v-menu>
      <template #activator="{ props }">
        <v-btn
          v-bind="props"
          :title="$gettext('Sort by')"
          :aria-label="$gettext('Sort by')"
          :append-icon="mdiMenuDown"
          :prepend-icon="mdiSort"
          variant="text"
        >
          {{ order }}
        </v-btn>
      </template>
      <v-list>
        <v-list-item
          v-for="option in options"
          :key="`${option.column}-${option.order}`"
        >
          <v-btn variant="text" @click="select(option)">
            {{ labels[option.label] || option.label }}
          </v-btn>
        </v-list-item>
      </v-list>
    </v-menu>
  </span>
</template>
