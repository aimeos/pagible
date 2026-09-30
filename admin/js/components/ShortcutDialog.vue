/** @license MIT, https://opensource.org/license/mit */

<script>
import CmsDialog from './Dialog.vue'
import { allowed, commands, routes, shortcuts } from '../shortcuts'
import { useUserStore } from '../stores'

export default {
  components: {
    CmsDialog
  },

  setup() {
    const user = useUserStore()
    return { shortcuts, user }
  },

  computed: {
    groups() {
      const can = (perms) => this.user.can(perms)
      const pick = (...names) =>
        names.filter((name) => allowed(name)).map((name) => ({ keys: commands[name].keys, label: commands[name].label() }))
      const go = Object.entries(routes)
        .filter(([name]) => can(name))
        .map(([, route]) => ({ keys: route.keys, then: true, label: route.label() }))

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
          title: this.$gettext('Commands'),
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
          items: can('page:save') ? [{ keys: ['Alt', '↑ ↓'], label: this.$gettext('Move content element up or down') }] : []
        },
        {
          title: this.$gettext('Page tree'),
          items: can('page:view')
            ? [
                { keys: ['↑', '↓'], label: this.$gettext('Move between pages') },
                { keys: ['→'], label: this.$gettext('Expand page') },
                ...(can('page:add') ? [{ keys: ['N'], label: this.$gettext('Add subpage to focused page') }] : []),
                ...(can('page:move') ? [{ keys: ['Alt', '↑ ↓ ← →'], label: this.$gettext('Reorder or re-nest page') }] : []),
                ...pick('copy', 'cut', 'paste')
              ]
            : []
        },
        {
          title: this.$gettext('AI chat'),
          items: can(['page:chat', 'file:chat', 'element:chat'])
            ? [
                { keys: ['Enter'], label: this.$gettext('Send message') },
                { keys: ['Shift', 'Enter'], label: this.$gettext('New line') },
                { keys: ['↑', '↓'], label: this.$gettext('Recall previous messages') }
              ]
            : []
        }
      ].filter((group) => group.items.length)
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
  opacity: var(--v-medium-emphasis-opacity);
}

kbd {
  display: inline-block;
  min-width: 1.75em;
  padding: 2px 6px;
  border: 1px solid rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  border-bottom-width: 2px;
  border-radius: 4px;
  background: transparent;
  font-family: inherit;
  font-size: 0.8125rem;
  text-align: center;
}
</style>
