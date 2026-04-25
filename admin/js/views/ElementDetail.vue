/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import ChangesDialog from '../components/ChangesDialog.vue'
import DetailAppBar from '../components/DetailAppBar.vue'
import HistoryDialog from '../components/HistoryDialog.vue'
import ElementDetailRefs from '../components/ElementDetailRefs.vue'
import ElementDetailItem from '../components/ElementDetailItem.vue'
import { useDirtyStore, useUserStore, useMessageStore, useViewStack } from '../stores'
import { applyResult, hasUnresolved } from '../merge'
import { publishItem } from '../publish'
import { write, translate } from '../ai'
import { subscribe } from '../echo'

export default {
  components: {
    AsideMeta,
    ChangesDialog,
    DetailAppBar,
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
    publishTime: null,
    publishing: false,
    saving: false,
    vchanged: false,
    vhistory: false,
    tab: 'element'
  }),

  setup() {
    const dirtyStore = useDirtyStore()
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      dirtyStore,
      user,
      messages,
      viewStack
    }
  },

  created() {
    this.dirtyStore.register(() => this.save(true))

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
    this.dirtyStore.unregister()
    this.echoCleanup?.()
  },

  computed: {
    hasConflict() {
      return hasUnresolved(this.changed)
    }
  },

  methods: {
    apply(changes) {
      Object.assign(this.item, changes)
      this.dirty = true
      this.vhistory = false
    },

    errorUpdated(event) {
      this.error = event
    },

    itemUpdated() {
      this.$emit('update:item', this.item)
      this.dirty = true
    },

    publish(at = null) {
      publishItem(this, 'element', {
        success: this.$gettext('Element published successfully'),
        scheduled: (d) => this.$gettext('Element scheduled for publishing at %{date}', { date: d.toLocaleDateString() }),
        error: this.$gettext('Error publishing element')
      }, at)
    },

    published() {
      const at = new Date(this.publishAt)

      if (this.publishTime) {
        const [hours, minutes] = this.publishTime.split(':').map(Number)
        at.setHours(hours, minutes, 0, 0)
      }

      this.publish(at)
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
  },

  watch: {
    dirty(value) {
      this.dirtyStore.set(value)
    }
  }
}
</script>

<template>
  <DetailAppBar
    type="element"
    :label="$gettext('Element')"
    :name="item.name"
    :dirty="dirty"
    :error="error"
    :conflict="hasConflict"
    :changed="changed"
    :published="item.published"
    :has-latest="!!item.latest"
    :saving="saving"
    :publishing="publishing"
    v-model:publish-at="publishAt"
    v-model:publish-time="publishTime"
    @save="save()"
    @publish="publish()"
    @schedule="published"
    @history="vhistory = true"
    @changes="vchanged = true"
  />

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
      @apply="apply"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="{ data: item }"
      @resolve="dirty = true"
    />
  </Teleport>
</template>
