/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiCloseCircleOutline } from '@mdi/js'
import ListSkeleton from './ListSkeleton.vue'
import LoadingSpinner from './LoadingSpinner.vue'

// Loading skeleton/spinner and "no entries" message below the item lists
export default {
  components: { ListSkeleton, LoadingSpinner },

  props: {
    empty: { type: Boolean, default: false },
    filtered: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    resettable: { type: Boolean, default: false }
  },

  emits: ['reset'],

  setup() {
    return { mdiCloseCircleOutline }
  }
}
</script>

<template>
  <ListSkeleton v-if="loading && empty" />
  <p v-else-if="loading" class="loading">
    {{ $gettext('Loading') }}
    <LoadingSpinner width="32" height="32" />
  </p>

  <p v-if="!loading && empty" class="notfound">
    <template v-if="filtered">
      {{ $gettext('No entries found') }}
      <v-btn
        v-if="resettable"
        class="btn-reset-filter"
        variant="text"
        :prepend-icon="mdiCloseCircleOutline"
        @click="$emit('reset')"
        >{{ $gettext('Reset') }}</v-btn
      >
    </template>
    <template v-else>{{ $gettext('No entries yet') }}</template>
  </p>
</template>
