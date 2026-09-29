/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import CmsDialog from './Dialog.vue'
import { pluginLabel } from '../i18n'
import { available, commands, execute, routes, shortcuts } from '../shortcuts'
import { usePluginStore, useUserStore } from '../stores'
import { safeParse } from '../utils'
import { mdiFileDocumentOutline, mdiFileImageOutline, mdiPuzzleOutline, mdiShareVariant } from '@mdi/js'

const LIMIT = 5

// searchable content types, their icon and the additional info shown for each result
const TYPES = {
  page: { icon: mdiFileDocumentOutline, info: (data) => (data.path ? '/' + data.path : '') },
  file: { icon: mdiFileImageOutline, info: (data) => data.mime || '' },
  element: { icon: mdiShareVariant, info: (data) => data.type || '' }
}

const SEARCH = gql`
  query ($term: String!, $limit: Int!, $page: Boolean!, $file: Boolean!, $element: Boolean!) {
    pages(filter: { any: $term }, first: $limit) @include(if: $page) {
      data {
        id
        lang
        name
        title
        path
        latest {
          id
          data
        }
      }
    }
    files(filter: { any: $term }, first: $limit) @include(if: $file) {
      data {
        id
        lang
        name
        mime
        latest {
          id
          data
        }
      }
    }
    elements(filter: { any: $term }, first: $limit) @include(if: $element) {
      data {
        id
        lang
        name
        type
        latest {
          id
          data
        }
      }
    }
  }
`

const empty = () => ({ page: [], file: [], element: [] })

export default {
  components: {
    CmsDialog
  },

  data: () => ({
    active: 0,
    failed: false,
    found: empty(),
    searching: false,
    seq: 0,
    term: '',
    timer: null
  }),

  setup() {
    const plugin = usePluginStore()
    const user = useUserStore()

    return { plugin, shortcuts, user }
  },

  beforeUnmount() {
    clearTimeout(this.timer)
  },

  computed: {
    actions() {
      return Object.entries(commands)
        .filter(([name]) => name !== 'palette' && available(name))
        .map(([name, cmd]) => ({ id: name, icon: cmd.icon, label: cmd.label(), keys: cmd.keys, run: () => execute(name) }))
    },

    groups() {
      const words = this.term.toLowerCase().split(/\s+/).filter(Boolean)
      const match = (item) => words.every((word) => item.label.toLowerCase().includes(word))
      let idx = 0

      return [
        { title: this.$gettext('Actions'), items: this.actions.filter(match) },
        { title: this.$gettext('Go to'), items: this.panels.filter(match) },
        { title: this.$gettext('Pages'), items: this.found.page },
        { title: this.$gettext('Media'), items: this.found.file },
        { title: this.$gettext('Shared elements'), items: this.found.element }
      ]
        .filter((group) => group.items.length)
        .map((group) => ({ ...group, items: group.items.map((item) => ({ ...item, idx: idx++ })) }))
    },

    items() {
      return this.groups.flatMap((group) => group.items)
    },

    panels() {
      const go = (name) => () => this.$router?.push({ name })
      const list = Object.entries(routes)
        .filter(([name]) => this.user.can(name))
        .map(([name, route]) => ({
          id: name,
          icon: route.icon,
          label: route.title(),
          keys: route.keys,
          then: true,
          run: go(name)
        }))

      for (const [key, panel] of Object.entries(this.plugin.panels)) {
        if (this.user.can(panel.permission)) {
          list.push({ id: 'plugin:' + key, icon: mdiPuzzleOutline, label: pluginLabel(panel, this), run: go(key) })
        }
      }

      return list
    }
  },

  watch: {
    'shortcuts.palette'(open) {
      // keep the results while the palette fades out but don't search afterwards
      open ? this.reset() : clearTimeout(this.timer)
    },

    term(value) {
      this.active = 0
      clearTimeout(this.timer)

      const term = value.trim()

      if (term.length < 2) {
        this.clear()
        return
      }

      this.timer = setTimeout(() => this.search(term), 250)
    },

    items(list) {
      if (this.active >= list.length) {
        this.active = Math.max(list.length - 1, 0)
      }
    }
  },

  methods: {
    clear() {
      clearTimeout(this.timer)
      this.seq++ // ignore running searches
      this.failed = false
      this.searching = false
      this.found = empty()
    },

    keydown(ev) {
      const count = this.items.length

      switch (ev.key) {
        case 'ArrowDown':
          ev.preventDefault()
          this.move(count ? (this.active + 1) % count : 0)
          break
        case 'ArrowUp':
          ev.preventDefault()
          this.move(count ? (this.active - 1 + count) % count : 0)
          break
        case 'Enter':
          if (!ev.isComposing && this.items[this.active]) {
            ev.preventDefault()
            this.select(this.items[this.active])
          }
          break
      }
    },

    move(idx) {
      this.active = idx
      this.$nextTick(() => {
        document.getElementById(this.optionId(idx))?.scrollIntoView?.({ block: 'nearest' })
      })
    },

    open(type, id) {
      return () => this.$router?.push({ name: `${type}:detail`, params: { id } })
    },

    optionId(idx) {
      return `command-palette-option-${idx}`
    },

    reset() {
      this.clear()
      this.active = 0
      this.term = ''
    },

    result(type, entry) {
      const data = { ...entry, ...safeParse(entry.latest?.data) }
      const lang = data.lang ? ` (${data.lang})` : ''

      return {
        id: `${type}:${entry.id}`,
        icon: TYPES[type].icon,
        label: (data.name || data.title || this.$gettext('New')) + lang,
        info: TYPES[type].info(data),
        run: this.open(type, entry.id)
      }
    },

    search(term) {
      const types = Object.keys(TYPES)
      const allowed = Object.fromEntries(types.map((type) => [type, this.user.can(`${type}:view`)]))

      if (!Object.values(allowed).some(Boolean) || !this.$apollo) {
        return Promise.resolve()
      }

      const seq = ++this.seq
      this.failed = false
      this.searching = true

      return this.$apollo
        .query({ query: SEARCH, variables: { term, limit: LIMIT, ...allowed }, fetchPolicy: 'no-cache' })
        .then((result) => {
          if (seq === this.seq) {
            this.found = Object.fromEntries(
              types.map((type) => [type, (result.data?.[type + 's']?.data || []).map((entry) => this.result(type, entry))])
            )
          }
        })
        .catch((error) => {
          if (seq === this.seq) {
            this.$log?.(`CommandPalette::search(): Error searching`, error)
            this.failed = true
            this.found = empty()
          }
        })
        .finally(() => {
          if (seq === this.seq) {
            this.searching = false
          }
        })
    },

    select(item) {
      shortcuts.palette = false
      // run after the palette has been closed so it doesn't get the focus back
      this.$nextTick(item.run)
    }
  }
}
</script>

<template>
  <CmsDialog
    :model-value="shortcuts.palette"
    @update:model-value="shortcuts.palette = $event"
    :title="$gettext('Command palette')"
    :card-loading="searching ? 'primary' : false"
    content-class="command-palette-body"
    class="command-palette"
    max-width="640"
  >
    <v-text-field
      v-model="term"
      @keydown="keydown"
      :placeholder="$gettext('Type a command or search pages, files and elements')"
      :aria-label="$gettext('Command or search term')"
      :aria-activedescendant="items.length ? optionId(active) : undefined"
      aria-controls="command-palette-list"
      aria-autocomplete="list"
      role="combobox"
      aria-expanded="true"
      class="palette-input"
      variant="outlined"
      density="comfortable"
      hide-details
      autofocus
    />

    <div id="command-palette-list" class="palette-list" role="listbox" :aria-label="$gettext('Commands')">
      <template v-for="group in groups" :key="group.title">
        <div class="palette-group" role="presentation">{{ group.title }}</div>
        <div
          v-for="item in group.items"
          :key="item.id"
          :id="optionId(item.idx)"
          :class="{ active: item.idx === active }"
          :aria-selected="item.idx === active"
          @click="select(item)"
          @mousemove="active = item.idx"
          class="palette-item"
          role="option"
        >
          <v-icon :icon="item.icon" class="icon" size="small" />
          <span class="label">{{ item.label }}</span>
          <span v-if="item.info" class="info">{{ item.info }}</span>
          <span v-if="item.keys" class="keys" aria-hidden="true">
            <template v-for="(key, idx) in item.keys" :key="idx">
              <span v-if="idx && item.then" class="sep">{{ $gettext('then') }}</span>
              <kbd>{{ key }}</kbd>
            </template>
          </span>
        </div>
      </template>

      <p v-if="failed" class="palette-error" role="alert">{{ $gettext('Search failed, please try again') }}</p>
      <p v-else-if="!items.length && !searching" class="palette-empty">{{ $gettext('No results') }}</p>
    </div>
  </CmsDialog>
</template>

<style>
.command-palette .v-overlay__content {
  align-self: flex-start;
  margin-top: 10vh;
}

.command-palette-body {
  padding: 12px !important;
}
</style>

<style scoped>
.palette-list {
  margin-top: 8px;
  max-height: 60vh;
  overflow-y: auto;
}

.palette-group {
  padding: 12px 8px 4px;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  opacity: 0.7;
}

.palette-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-radius: 6px;
  cursor: pointer;
}

.palette-item.active {
  background: rgba(var(--v-theme-primary), 0.12);
}

.palette-item .label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.palette-item .info {
  overflow: hidden;
  font-size: 0.8125rem;
  opacity: 0.7;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.palette-item .keys {
  flex-shrink: 0;
  margin-inline-start: auto;
  white-space: nowrap;
}

.palette-item .sep {
  margin: 0 4px;
  font-size: 0.75rem;
  opacity: 0.6;
}

.palette-item kbd {
  display: inline-block;
  min-width: 1.5em;
  margin-inline-start: 2px;
  padding: 0 5px;
  border: 1px solid rgba(var(--v-border-color), 0.38);
  border-bottom-width: 2px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.75rem;
  text-align: center;
}

.palette-empty {
  padding: 16px 8px;
  opacity: 0.7;
}

.palette-error {
  padding: 16px 8px;
  color: rgb(var(--v-theme-error));
}
</style>
