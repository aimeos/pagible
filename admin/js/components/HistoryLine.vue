/** @license MIT, https://opensource.org/license/mit */

<script>
export default {
  props: {
    list: { type: Object, default: null },
    changed: { type: Boolean, default: false }
  }
}
</script>

<template>
  <component :is="list?.first ? list.kind : 'div'" class="history-line" :class="{ listed: list, 'list-change': changed }"
    :style="list ? { '--depth': Math.min(list.depth - 1, 6) } : undefined"
    :start="list?.first && list.kind === 'ol' ? list.number : undefined" :type="list?.first && list.kind === 'ol' ? list.type : undefined"
  ><li v-if="list?.first" :aria-level="list.depth"><slot /></li><slot v-else /></component>
</template>

<style scoped>
.history-line {
  margin: 0;
  padding: 0;
}

.history-line.listed {
  padding-inline-start: calc(1.8em + var(--depth) * 1em);
  list-style-position: outside;
}

ul.history-line { list-style-type: disc; }

.history-line > li { padding: 0; }

.list-change > li::marker {
  color: rgb(var(--v-theme-info));
  font-weight: 700;
}
</style>
