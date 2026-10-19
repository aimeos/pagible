/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiRefresh } from '@mdi/js'

/**
 * Reload button of the lists and the refresh hint if another user changed the entries
 */
export default {
  props: {
    loading: { type: Boolean, default: false },
    outdated: { type: Boolean, default: false },
    title: { type: String, required: true }
  },

  emits: ['reload'],

  setup() {
    return { mdiRefresh }
  }
}
</script>

<template>
  <v-btn
    v-if="outdated"
    @click="$emit('reload')"
    :prepend-icon="mdiRefresh"
    :title="$gettext('Updated by another user')"
    color="warning"
    variant="tonal"
    size="small"
    rounded="lg"
    class="btn-outdated"
    >{{ $gettext('Refresh') }}</v-btn
  >

  <v-btn
    @click="$emit('reload')"
    :loading="loading"
    :title="title"
    :icon="mdiRefresh"
    class="btn-reload"
    variant="text"
  />
</template>
