/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import ChangesDialog from '../components/ChangesDialog.vue'
import HistoryDialog from '../components/HistoryDialog.vue'
import ElementDetailRefs from '../components/ElementDetailRefs.vue'
import ElementDetailItem from '../components/ElementDetailItem.vue'
import { useUserStore, useDrawerStore, useMessageStore, useViewStack } from '../stores'
import { applyResult, hasUnresolved } from '../merge'
import { write, translate } from '../ai'
import { subscribe } from '../echo'
import {
  mdiKeyboardBackspace,
  mdiHistory,
  mdiDatabaseArrowDown,
  mdiSwapHorizontal,
  mdiChevronRight,
  mdiChevronLeft
} from '@mdi/js'

export default {
  components: {
    AsideMeta,
    ChangesDialog,
    HistoryDialog,
    ElementDetailRefs,
    ElementDetailItem
  },

  props: {
    item: { type: Object, required: true }
  },

  provide() {
    return {
      write: this.writeText,
      translate: this.translateText
    }
  },

  data: () => ({
    assets: {},
    changed: null,
    dirty: false,
    echoCleanup: null,
    error: false,
    latestId: null,
    publishAt: null,
    publishing: false,
    pubmenu: false,
    saving: false,
    vchanged: false,
    vhistory: false,
    tab: 'element'
  }),

  setup() {
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const drawer = useDrawerStore()
    const user = useUserStore()

    return {
      user,
      drawer,
      messages,
      viewStack,
      mdiKeyboardBackspace,
      mdiHistory,
      mdiDatabaseArrowDown,
      mdiSwapHorizontal,
      mdiChevronRight,
      mdiChevronLeft
    }
  },

  created() {
    if (!this.item?.id || !this.user.can('element:view')) {
      return
    }

    this.$apollo
      .query({
        query: gql`
          query ($id: ID!) {
            element(id: $id) {
              id
              files {
                id
                mime
                name
                path
                previews
                updated_at
                editor
              }
              latest {
                id
                published
                data
                editor
                created_at
                files {
                  id
                  mime
                  name
                  path
                  previews
                  updated_at
                  editor
                }
              }
            }
          }
        `,
        variables: {
          id: this.item.id
        }
      })
      .then((result) => {
        if (result.errors || !result.data.element) {
          throw result
        }

        const files = []
        const element = result.data.element

        this.reset()
        this.latestId = element.latest?.id
        this.assets = {}

        for (const entry of element.latest?.files || element.files || []) {
          this.assets[entry.id] = { ...entry, previews: JSON.parse(entry.previews || '{}') }
          files.push(entry.id)
        }

        this.item.files = files

        subscribe('element', this.item.id, (event) => {
          if (!this.dirty && this.user.can('element:view') && event.editor !== this.user.me?.email) {
            this.latestId = event.versionId
            Object.assign(this.item, event.data)
          }
        }).then((cleanup) => {
          this.echoCleanup = cleanup
        })
      })
      .catch((error) => {
        this.messages.add(this.$gettext('Error fetching element') + ':\n' + error, 'error')
        this.$log(`ElementDetail::watch(item): Error fetching element`, error)
      })
  },

  beforeUnmount() {
    this.echoCleanup?.()
  },

  computed: {
    hasConflict() {
      return hasUnresolved(this.changed)
    }
  },

  methods: {
    errorUpdated(event) {
      this.error = event
    },

    itemUpdated() {
      this.$emit('update:item', this.item)
      this.dirty = true
    },

    publish(at = null) {
      if (!this.user.can('element:publish')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      this.publishing = true

      this.save(true).then((valid) => {
        if (!valid || this.changed) {
          return
        }

        this.$apollo
          .mutate({
            mutation: gql`
              mutation ($id: [ID!]!, $at: DateTime) {
                pubElement(id: $id, at: $at) {
                  id
                }
              }
            `,
            variables: {
              id: [this.item.id],
              at: at?.toISOString()?.substring(0, 19)?.replace('T', ' ')
            }
          })
          .then((response) => {
            if (response.errors) {
              throw response.errors
            }

            if (!at) {
              this.item.published = true
              this.messages.add(this.$gettext('Element published successfully'), 'success')
            } else {
              this.item.publish_at = at
              this.messages.add(
                this.$gettext('Element scheduled for publishing at %{date}', {
                  date: at.toLocaleDateString()
                }),
                'info'
              )
            }

            this.viewStack.closeView()
          })
          .catch((error) => {
            this.messages.add(this.$gettext('Error publishing element') + ':\n' + error, 'error')
            this.$log(`ElementDetail::publish(): Error publishing element`, at, error)
          })
          .finally(() => {
            this.publishing = false
          })
      })
    },

    published() {
      this.publish(this.publishAt)
      this.pubmenu = false
    },

    reset() {
      this.dirty = false
      this.changed = null
      this.error = false
    },

    revertVersion(event) {
      this.use(event)
      this.reset()
    },

    save(quiet = false) {
      if (!this.user.can('element:save')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve(false)
      }

      if (this.error) {
        this.messages.add(
          this.$gettext('There are invalid fields, please resolve the errors first'),
          'error'
        )
        return Promise.resolve(false)
      }

      if (!this.dirty) {
        return Promise.resolve(true)
      }

      if (!this.item.name) {
        this.item.name = this.title(this.item.data)
      }

      this.saving = true

      return this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: ID!, $input: ElementInput!, $files: [ID!], $latestId: ID) {
              saveElement(id: $id, input: $input, files: $files, latestId: $latestId) {
                id
                latest { id }
                changed
              }
            }
          `,
          variables: {
            id: this.item.id,
            input: {
              type: this.item.type,
              name: this.item.name,
              lang: this.item.lang,
              data: JSON.stringify(this.item.data || {})
            },
            files: this.item.files.filter((id, idx, self) => {
              return self.indexOf(id) === idx
            }),
            latestId: this.latestId
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          const el = result.data?.saveElement
          const changed = el?.changed ? JSON.parse(el.changed) : null

          if (changed?.latest?.id || el?.latest?.id) {
            this.latestId = changed?.latest?.id ?? el.latest.id
          }

          applyResult(this, changed, this.$gettext('Element saved successfully'), quiet)

          return true
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error saving element') + ':\n' + error, 'error')
          this.$log(`ElementDetail::save(): Error saving element`, error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    title(data) {
      return (
        (
          data?.title ||
          data?.text ||
          Object.values(data || {})
            .map((v) => (v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null))
            .filter((v) => !!v)
            .join(' - ')
        ).substring(0, 100) || ''
      )
    },

    writeText(prompt, context = [], files = []) {
      if (!Array.isArray(context)) {
        context = [context]
      }

      context.push('element data as JSON: ' + JSON.stringify(this.item.data))
      context.push('required output language: ' + (this.item.lang || 'en'))

      return write(prompt, context, files)
    },

    use(version) {
      Object.assign(this.item, version.data)
      this.vhistory = false
      this.dirty = true
    },

    translateText(texts, to, from = null) {
      return translate(texts, to, from || this.item.lang)
    },

    versions(id) {
      if (!this.user.can('element:view')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve([])
      }

      if (!id) {
        return Promise.resolve([])
      }

      return this.$apollo
        .query({
          query: gql`
            query ($id: ID!) {
              element(id: $id) {
                id
                versions {
                  id
                  published
                  publish_at
                  data
                  editor
                  created_at
                  files {
                    id
                  }
                }
              }
            }
          `,
          variables: {
            id: id
          }
        })
        .then((result) => {
          if (result.errors || !result.data.element) {
            throw result
          }

          return (result.data.element.versions || []).map((v) => {
            return {
              ...v,
              data: JSON.parse(v.data || '{}'),
              files: v.files.map((file) => file.id)
            }
          })
        })
        .catch((error) => {
          this.messages.add(
            this.$gettext('Error fetching element versions') + ':\n' + error,
            'error'
          )
          this.$log(`ElementDetail::versions(): Error fetching element versions`, id, error)
        })
    }
  }
}
</script>

<template>
  <v-app-bar :elevation="0" density="compact" role="sectionheader" :aria-label="$gettext('Menu')">
    <template v-slot:prepend>
      <v-btn
        @click="viewStack.closeView()"
        :title="$gettext('Back to list view')"
        :icon="mdiKeyboardBackspace"
      />
    </template>

    <v-app-bar-title>
      <h1 class="app-title">{{ $gettext('Element') }}: {{ item.name }}</h1>
    </v-app-bar-title>

    <template v-slot:append>
      <v-btn
        @click="vhistory = true"
        :class="{ hidden: item.published && !dirty && !item.latest }"
        :title="$gettext('View history')"
        :icon="mdiHistory"
        class="no-rtl"
      />

      <v-btn v-if="changed"
        @click="vchanged = true"
        :class="{ error: hasConflict }"
        :title="$gettext('View merge changes')"
        :icon="mdiSwapHorizontal"
        class="menu-changed"
      />

      <v-btn
        @click="save()"
        :loading="saving"
        :title="$gettext('Save')"
        :class="{ error: error }"
        class="menu-save"
        :disabled="!dirty || error || !user.can('element:save')"
        :variant="!dirty || error || !user.can('element:save') ? 'plain' : 'flat'"
        :color="hasConflict ? 'warning' : (!dirty || error || !user.can('element:save') ? '' : 'blue-darken-1')"
        :icon="mdiDatabaseArrowDown"
      />

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            icon
            :loading="publishing"
            :title="$gettext('Schedule publishing')"
            :class="{ error: error }"
            class="menu-publish"
            :disabled="(item.published && !dirty) || error || !user.can('element:publish')"
            :variant="
              (item.published && !dirty) || error || !user.can('element:publish')
                ? 'plain'
                : 'flat'
            "
            :color="
              (item.published && !dirty) || error || !user.can('element:publish')
                ? ''
                : 'blue-darken-2'
            "
          >
            <v-icon>
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                <path d="M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z" />
                <path
                  d="M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"
                />
              </svg>
            </v-icon>
          </v-btn>
        </template>
        <div class="menu-content">
          <v-date-picker v-model="publishAt" hide-header show-adjacent-months />
          <v-btn
            @click="published"
            :disabled="!publishAt || error"
            :color="publishAt ? 'primary' : ''"
            variant="text"
            >{{ $gettext('Publish') }}</v-btn
          >
        </div>
      </v-menu>

      <v-btn
        icon
        @click="publish()"
        :loading="publishing"
        :title="$gettext('Publish')"
        :class="{ error: error }"
        class="menu-publish"
        :disabled="(item.published && !dirty) || error || !user.can('element:publish')"
        :variant="
          (item.published && !dirty) || error || !user.can('element:publish') ? 'plain' : 'flat'
        "
        :color="
          (item.published && !dirty) || error || !user.can('element:publish')
            ? ''
            : 'blue-darken-2'
        "
      >
        <v-icon>
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
            <path d="M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z" />
          </svg>
        </v-icon>
      </v-btn>

      <v-btn
        @click="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? mdiChevronRight : mdiChevronLeft"
      />
    </template>
  </v-app-bar>

  <v-main class="element-details" :aria-label="$gettext('Element')">
    <v-form @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab value="element" :class="{ changed: dirty, error: error }">{{
          $gettext('Element')
        }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="element">
          <ElementDetailItem
            @update:item="itemUpdated"
            @error="errorUpdated"
            :assets="assets"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <ElementDetailRefs :item="item" />
        </v-window-item>
      </v-window>
    </v-form>
  </v-main>

  <AsideMeta :item="item" />

  <Teleport to="body">
    <HistoryDialog
      v-model="vhistory"
      :readonly="!user.can('element:save')"
      :current="{
        data: {
          lang: item.lang,
          type: item.type,
          name: item.name,
          data: item.data
        },
        files: item.files
      }"
      :load="() => versions(item.id)"
      @revert="revertVersion"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="{ data: item }"
      @resolve="dirty = true"
    />
  </Teleport>
</template>

<style scoped>
.v-toolbar-title {
  margin-inline-start: 0;
}
</style>
