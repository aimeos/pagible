<script>
  import { diffJson } from 'diff'

  export default {
    props: {
      'modelValue': {type: Boolean, required: true},
      'current': {type: Object, required: true},
      'load': {type: Function, required: true},
    },

    emit: ['update:modelValue', 'use', 'revert'],

    data: () => ({
      list: [],
      latest: null,
      loading: false,
      show: false,
    }),

    computed: {
      versions() {
        return this.list.filter(v => {
          return this.isModified(v, this.current) || v.published || v.publish_at
        })
      }
    },

    methods: {
      diff(old, str) {
        if(old && str) {
          return diffJson(old, str)
        } else if(str) {
          return [str]
        }
        return []
      },


      isModified(v1, v2) {
        if(!v1 || !v2) {
          return false
        }

        return diffJson(v1.data || {}, v2.data || {}).length !== 1
      },


      reset() {
        this.list = []
        this.latest = null
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          if(val && !this.latest) {
            this.loading = true

            this.load().then(versions => {
              this.latest = versions.shift()
              this.list = versions
            }).finally(() => {
              this.loading = false
            })
          }
        }
      }
    }
  }
</script>

<template>
  <v-dialog :modelValue="modelValue" max-width="1200" scrollable>
    <v-card prepend-icon="mdi-history">
      <template v-slot:append>
        <v-btn
          :title="$gettext('Close')"
          @click="$emit('update:modelValue', false)"
          icon="mdi-close"
          variant="flat"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('History') }}
      </template>

      <v-divider></v-divider>

      <v-card-text>
        <v-timeline side="end" align="start">
          <v-timeline-item v-if="loading" dot-color="grey-lighten-1" size="small" width="100%">
            <div class="loading">
              {{ $gettext('Loading') }}
              <svg class="spinner" width="32" height="32" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <circle class="spin1" cx="4" cy="12" r="3"/>
                <circle class="spin1 spin2" cx="12" cy="12" r="3"/>
                <circle class="spin1 spin3" cx="20" cy="12" r="3"/>
              </svg>
            </div>
          </v-timeline-item>

          <v-timeline-item v-if="!loading && !(latest && isModified(latest, current) || versions.length)"
            dot-color="grey-lighten-1"
            width="100%"
            size="small">
            {{ $gettext('No changes') }}
          </v-timeline-item>

          <v-timeline-item v-if="!loading && latest && isModified(latest, current)"
            dot-color="blue"
            width="100%"
            size="small">

            <v-card class="elevation-2" @click="show = !show">
              <v-card-title>{{ $gettext('Current') }}</v-card-title>
              <v-card-text class="diff" :class="{show: show}">
                <span v-for="part of diff(current.data, latest?.data)" :class="{added: part.added, removed: part.removed}">
                  {{ part.value || part }}
                </span>
              </v-card-text>
              <v-card-actions>
                <v-btn variant="outlined" @click.stop="$emit('revert', latest)">
                  {{ $gettext('Revert') }}
                </v-btn>
              </v-card-actions>
            </v-card>

          </v-timeline-item>

          <v-timeline-item v-for="(version, idx) in versions" :key="idx"
            :dot-color="version.published ? 'success' : 'grey-lighten-1'"
            :class="{publish: version.publish_at}"
            width="100%"
            size="small">

            <v-card class="elevation-2">
              <div @click="version._show = !version._show">
                <v-card-title>
                  {{ (new Date(version.publish_at || version.created_at)).toLocaleString($vuetify.locale.current) }}
                </v-card-title>
                <v-card-subtitle>
                  {{ version.editor }}
                </v-card-subtitle>
                <v-card-text class="diff" :class="{show: version._show}">
                  <span v-for="part of diff(version.data, current.data)" :class="{added: part.removed, removed: part.added}">
                    {{ part.value || part }}
                  </span>
                </v-card-text>
              </div>
              <v-card-actions>
                <v-btn variant="outlined" @click.stop="$emit('use', version)">
                  {{ $gettext('Use version') }}
                </v-btn>
              </v-card-actions>
            </v-card>

          </v-timeline-item>
        </v-timeline>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
  .v-timeline--vertical {
      grid-template-columns: 0 min-content auto;
  }

  .v-timeline-item__opposite {
    display: none;
  }

  .v-timeline-item.publish .v-card-title {
    color: rgb(var(--v-theme-success));
  }

  .v-timeline-item .v-card-text.diff div.divider {
    margin: 1em 0 0.5em 0;
    font-weight: bold;
    font-size: 110%;
    display: none;
  }

  .v-timeline-item .v-card-text.diff.show div.divider {
    display: block;
  }

  .v-timeline-item .v-card-text.diff span {
    white-space: break-spaces;
    display: none;
  }

  .v-timeline-item .v-card-text.diff.show span {
    display: inline;
  }

  .v-timeline-item .v-card-text.diff span.added {
    background-color: #00ff0030;
    display: inline;
  }

  .v-timeline-item .v-card-text.diff span.removed {
    background-color: #ff000030;
    display: inline;
  }
</style>
