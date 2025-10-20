/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import { useTheme } from 'vuetify'
  import { useGettext } from "vue3-gettext"
  import { useAuthStore, useLanguageStore, useMessageStore } from '../stores'

  export default {
    data: () => ({
      user: null,
      menu: {},
    }),

    setup() {
      const languages = useLanguageStore()
      const messages = useMessageStore()
      const auth = useAuthStore()
      const i18n = useGettext()
      const theme = useTheme()

      return { auth, i18n, languages, messages, theme }
    },

    created() {
      this.auth.user().then(user => {
        this.user = user
      }).catch(error => {
        this.messages.add(this.$gettext('Failed to load user') + ":\n" + error, 'error')
      })
    },

    methods: {
      logout() {
        this.auth.logout().finally(() => {
          this.user = null
          this.$router.push({ name: "login" })
        })
      },


      change(code) {
        import(`../../i18n/${code}.json`).then(translations => {
          this.i18n.translations = translations.default || translations
          this.$vuetify.locale.current = code
          this.i18n.current = code
        })
      }
    }
  }
</script>

<template>
  <v-btn
    @click="theme.toggle()"
    :title="$gettext('Toggle light/dark mode')"
    :icon="theme.global.current.value.dark ? 'mdi-white-balance-sunny' : 'mdi-weather-night'"
  />

  <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
    v-model="menu['lang']"
    transition="scale-transition"
    location="end center"
    max-width="300">

    <template #activator="{ props }">
      <v-btn
        v-bind="props"
        :title="$gettext('Switch language')"
        icon="mdi-web"
        variant="text"
      />
    </template>

    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('Switch language') }}</v-toolbar-title>
        <v-btn icon="mdi-close" @click="menu['lang'] = false" />
      </v-toolbar>

      <v-list @click="menu['lang'] = false">
        <v-list-item v-for="(_, code) in i18n.available" :key="code">
          <v-btn
            @click="change(code)"
            variant="text"
          >{{ languages.translate(code) }} ({{ code }})</v-btn>
        </v-list-item>
      </v-list>
    </v-card>
  </component>

  <v-menu v-if="user">
    <template #activator="{ props }">
      <v-btn v-bind="props"
        :title="$gettext('User menu')"
        icon="mdi-account-circle-outline"
        class="icon"
      />
    </template>
    <v-list>
      <v-list-item v-if="user?.name">
        {{ user.name }}
      </v-list-item>
      <v-list-item>
        <v-btn prepend-icon="mdi-logout"
          @click="logout()"
          variant="text"
          class="menu-item"
        >{{ $gettext('Logout') }}</v-btn>
      </v-list-item>
    </v-list>
  </v-menu>
</template>

<style scoped>
  .menu-item {
    width: 100%;
    padding: 0;
    text-align: start;
    text-transform: capitalize
  }
</style>
