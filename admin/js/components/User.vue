/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { useTheme } from 'vuetify'
import { useGettext } from 'vue3-gettext'
import { useUserStore, useLanguageStore, useMessageStore } from '../stores'
import {
  mdiWhiteBalanceSunny,
  mdiWeatherNight,
  mdiWeb,
  mdiClose,
  mdiAccountCircleOutline,
  mdiLogout
} from '@mdi/js'

export default {
  data: () => ({
    me: null,
    menu: {}
  }),

  setup() {
    const languages = useLanguageStore()
    const messages = useMessageStore()
    const user = useUserStore()
    const i18n = useGettext()
    const theme = useTheme()

    return {
      user,
      i18n,
      languages,
      messages,
      theme,
      mdiWhiteBalanceSunny,
      mdiWeatherNight,
      mdiWeb,
      mdiClose,
      mdiAccountCircleOutline,
      mdiLogout
    }
  },

  created() {
    this.user
      .user()
      .then((user) => {
        this.me = user

        const storedTheme = this.user.getData('app', 'theme')
        if (storedTheme) {
          this.theme.change(storedTheme)
        }

        const storedLanguage = this.user.getData('app', 'language')
        if (storedLanguage && storedLanguage !== this.i18n.current) {
          this.change(storedLanguage)
        }
      })
      .catch((error) => {
        this.messages.add(this.$gettext('Failed to load user') + ':\n' + error, 'error')
      })
  },

  methods: {
    change(code) {
      Promise.all([
        import(`../../i18n/${code}.json`),
        import('../vuetify').then((v) => v.switchLocale(code))
      ]).then(([translations]) => {
        this.i18n.translations = translations.default || translations
        this.$vuetify.locale.current = code
        this.i18n.current = code
        this.user.saveData('app', 'language', code)
      })
    },

    toggleTheme() {
      this.theme.toggle()
      this.user.saveData('app', 'theme', this.theme.global.name.value)
    },

    logout() {
      this.user.logout().finally(() => {
        this.me = null
        this.$router.push({ name: 'login' })
      })
    }
  }
}
</script>

<template>
  <v-btn
    @click="toggleTheme()"
    :title="$gettext('Toggle light/dark mode')"
    :icon="theme.global.current.value.dark ? mdiWhiteBalanceSunny : mdiWeatherNight"
  />

  <component
    :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
    :aria-label="$gettext('Language')"
    v-model="menu['lang']"
    transition="scale-transition"
    location="bottom"
    max-width="300"
  >
    <template #activator="{ props }">
      <v-btn v-bind="props" :title="$gettext('Switch language')" :icon="mdiWeb" variant="text" />
    </template>

    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('Switch language') }}</v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="menu['lang'] = false" />
      </v-toolbar>

      <v-list @click="menu['lang'] = false" role="listbox">
        <v-list-item v-for="(_, code) in i18n.available" :key="code" role="option" @click="change(code)">
          {{ languages.translate(code) }} ({{ code }})
        </v-list-item>
      </v-list>
    </v-card>
  </component>

  <v-menu v-if="me">
    <template #activator="{ props }">
      <v-btn
        v-bind="props"
        :title="$gettext('User menu')"
        :icon="mdiAccountCircleOutline"
        class="icon"
      />
    </template>
    <v-list>
      <v-list-item v-if="me?.name">
        {{ me.name }}
      </v-list-item>
      <v-list-item>
        <v-btn :prepend-icon="mdiLogout" @click="logout()" variant="text" class="menu-item">{{
          $gettext('Logout')
        }}</v-btn>
      </v-list-item>
    </v-list>
  </v-menu>
</template>

<style scoped>
.menu-item {
  width: 100%;
  padding: 0;
  text-align: start;
  text-transform: capitalize;
}
</style>
