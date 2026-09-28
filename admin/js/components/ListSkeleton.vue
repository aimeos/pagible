/** @license MIT, https://opensource.org/license/mit */

<script>
export default {
  props: {
    rows: { type: Number, default: 6 }
  }
}
</script>

<template>
  <div class="list-skeleton" role="status">
    <span class="v-sr-only">{{ $gettext('Loading') }}</span>
    <div v-for="i in rows" :key="i" class="skeleton-row" :style="{ '--i': i }" aria-hidden="true">
      <span class="skeleton-box"></span>
      <span class="skeleton-lines">
        <span class="skeleton-line"></span>
        <span class="skeleton-line short"></span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.list-skeleton {
  padding: 4px 1%;
}

.skeleton-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 8px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  opacity: calc(1 - var(--i) * 0.12);
}

.skeleton-lines {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
}

.skeleton-box,
.skeleton-line {
  background: linear-gradient(
      90deg,
      transparent 0%,
      rgba(var(--v-theme-primary), 0.06) 50%,
      transparent 100%
    )
    rgba(var(--v-theme-on-surface), 0.08);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.4s ease-in-out infinite;
  border-radius: 6px;
}

.skeleton-box {
  flex: 0 0 auto;
  width: 24px;
  height: 24px;
}

.skeleton-line {
  height: 12px;
  width: 60%;
}

.skeleton-line.short {
  width: 30%;
  height: 10px;
}

.skeleton-row:nth-child(3n) .skeleton-line {
  width: 45%;
}

.skeleton-row:nth-child(3n + 1) .skeleton-line.short {
  width: 22%;
}

@keyframes skeleton-shimmer {
  from {
    background-position: 150% 0;
  }
  to {
    background-position: -50% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton-box,
  .skeleton-line {
    animation: none;
  }
}
</style>
