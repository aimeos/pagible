/** @license MIT, https://opensource.org/license/mit */

<script>
// Action menu entry, attributes and listeners are passed to the button
export default {
  inheritAttrs: false
}
</script>

<template>
  <v-list-item>
    <v-btn variant="text" v-bind="$attrs"><slot /></v-btn>
  </v-list-item>
</template>
