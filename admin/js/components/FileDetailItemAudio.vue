/** @license MIT, https://opensource.org/license/mit */

<script>
import { fileurl } from '../utils'

export default {
  props: {
    item: { type: Object, required: true }
  },

  setup() {
    return { fileurl }
  }
}
</script>

<template>
  <audio :src="fileurl(item)" crossorigin="anonymous" class="element" controls></audio>
</template>

<style scoped>
.element {
  max-width: 100%;
  max-height: 100%;
}
</style>
