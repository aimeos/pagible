/** @license MIT, https://opensource.org/license/mit */

<script>
import { useTheme } from 'vuetify'
import { useGettext } from 'vue3-gettext'
import ActionMenu from './ActionMenu.vue'
import { load as loadTranslations, ready } from '../i18n'
import { useUserStore, useLanguageStore, useMessageStore } from '../stores'
import {
  mdiWhiteBalanceSunny,
  mdiWeatherNight,
  mdiWeb,
  mdiAccountCircleOutline,
  mdiLogout
} from '@mdi/js'

export default {
  components: { ActionMenu },

  data: () => ({ me: null }),

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
      if (!this.i18n.available[code]) {
        return
      }

      return ready.then(() => Promise.all([
        loadTranslations(code),
        import('../vuetify').then((v) => v.switchLocale(code))
      ])).then(([applied]) => {
        if (!applied) return

        this.$vuetify.locale.current = code
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
    class="btn-darkmode"
  />

  <span class="btn-language">
    <ActionMenu
      :title="$gettext('Switch language')"
      :list-props="{ role: 'listbox' }"
      location="bottom"
    >
      <template #activator="{ props }">
        <v-btn v-bind="props" :title="$gettext('Switch language')" :icon="mdiWeb" variant="text" />
      </template>

      <v-list-item v-for="(_, code) in i18n.available" :key="code" role="option" @click="change(code)">
        {{ languages.translate(code) }} ({{ code }})
      </v-list-item>
    </ActionMenu>
  </span>

  <ActionMenu v-if="me" :title="$gettext('User menu')" :header="false" location="bottom">
    <template #activator="{ props, label }">
      <v-btn
        v-bind="props"
        :title="label"
        :icon="mdiAccountCircleOutline"
        class="icon"
      />
    </template>
    <v-list-item v-if="me?.name">
      {{ me.name }}
    </v-list-item>
    <v-list-item>
      <v-btn :prepend-icon="mdiLogout" @click="logout()" variant="text" class="menu-item">{{
        $gettext('Logout')
      }}</v-btn>
    </v-list-item>
  </ActionMenu>
</template>

<style scoped>
.menu-item {
  width: 100%;
  padding: 0;
  text-align: start;
  text-transform: capitalize;
}
</style>
