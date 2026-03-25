/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { url } from '../utils'

export default {
  props: {
    item: { type: Object, required: true }
  },

  setup() {
    return { url }
  }
}
</script>

<template>
  <audio :src="url(item.path)" crossorigin="anonymous" class="element" controls></audio>
</template>

<style scoped>
.element {
  max-width: 100%;
  max-height: 100%;
}
</style>
