/** @license MIT, https://opensource.org/license/mit */

<script>
import CmsDialog from './Dialog.vue'
import { commands, routes, shortcuts } from '../shortcuts'

export default {
  components: {
    CmsDialog
  },

  setup() {
    return { shortcuts }
  },

  computed: {
    groups() {
      const pick = (...names) => names.map((name) => ({ keys: commands[name].keys, label: commands[name].label() }))
      const go = Object.values(routes).map((route) => ({ keys: route.keys, then: true, label: route.label() }))

      return [
        {
          title: this.$gettext('General'),
          items: [
            ...pick('palette', 'sheet'),
            ...go,
            ...pick('aside', 'confirm'),
            { keys: ['Esc'], label: this.$gettext('Close dialog or menu') }
          ]
        },
        {
          title: this.$gettext('Command palette'),
          items: [
            { keys: ['↑', '↓'], label: this.$gettext('Move between commands and results') },
            { keys: ['Enter'], label: this.$gettext('Run command or open result') },
            { keys: commands.palette.keys, label: this.$gettext('Close command palette') }
          ]
        },
        {
          title: this.$gettext('Lists'),
          items: pick('search', 'create', 'select', 'drop')
        },
        {
          title: this.$gettext('Edit views'),
          items: pick('save', 'publish', 'prevTab', 'nextTab', 'back')
        },
        {
          title: this.$gettext('Page content'),
          items: [{ keys: ['Alt', '↑ ↓'], label: this.$gettext('Move content element up or down') }]
        },
        {
          title: this.$gettext('Page tree'),
          items: [
            { keys: ['↑', '↓'], label: this.$gettext('Move between pages') },
            { keys: ['→'], label: this.$gettext('Expand page') },
            { keys: ['N'], label: this.$gettext('Add subpage to focused page') },
            { keys: ['Alt', '↑ ↓ ← →'], label: this.$gettext('Reorder or re-nest page') },
            ...pick('copy', 'cut', 'paste')
          ]
        },
        {
          title: this.$gettext('AI chat'),
          items: [
            { keys: ['Enter'], label: this.$gettext('Send message') },
            { keys: ['Shift', 'Enter'], label: this.$gettext('New line') },
            { keys: ['↑', '↓'], label: this.$gettext('Recall previous messages') }
          ]
        }
      ]
    }
  }
}
</script>

<template>
  <CmsDialog
    v-model="shortcuts.sheet"
    :title="$gettext('Keyboard shortcuts')"
    class="shortcut-dialog"
    max-width="560"
  >
    <section v-for="group in groups" :key="group.title" class="shortcut-group">
      <h2>{{ group.title }}</h2>
      <dl>
        <div v-for="item in group.items" :key="item.label" class="shortcut">
          <dt>
            <template v-for="(key, idx) in item.keys" :key="idx">
              <span v-if="idx && item.then" class="plus">{{ $gettext('then') }}</span>
              <span v-else-if="idx" class="plus" aria-hidden="true">+</span>
              <kbd>{{ key }}</kbd>
            </template>
          </dt>
          <dd>{{ item.label }}</dd>
        </div>
      </dl>
    </section>
  </CmsDialog>
</template>

<style scoped>
.shortcut-group + .shortcut-group {
  margin-top: 24px;
}

.shortcut-group h2 {
  margin-bottom: 8px;
  font-size: 1rem;
  font-weight: 500;
}

.shortcut {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 6px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.shortcut dt {
  order: 2;
  flex-shrink: 0;
  white-space: nowrap;
}

.shortcut dd {
  margin: 0;
}

.plus {
  margin: 0 4px;
  opacity: 0.6;
}

kbd {
  display: inline-block;
  min-width: 1.75em;
  padding: 2px 6px;
  border: 1px solid rgba(var(--v-border-color), 0.38);
  border-bottom-width: 2px;
  border-radius: 4px;
  background: rgb(var(--v-theme-surface));
  font-family: inherit;
  font-size: 0.8125rem;
  text-align: center;
}
</style>
