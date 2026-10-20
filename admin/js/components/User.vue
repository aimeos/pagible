/** @license MIT, https://opensource.org/license/mit */

<script>
import { useTheme } from 'vuetify'
import { useGettext } from 'vue3-gettext'
import ActionMenu from './ActionMenu.vue'
import { switchLanguage } from '../locale'
import { useDirtyStore, useLanguageStore, useMessageStore, useSessionStore, useSettingStore, useUserStore } from '../stores'
import {
  mdiWhiteBalanceSunny,
  mdiWeatherNight,
  mdiWeb,
  mdiAccountCircleOutline,
  mdiLogout
} from '@mdi/js'

export default {
  components: { ActionMenu },

  setup() {
    const dirty = useDirtyStore()
    const languages = useLanguageStore()
    const messages = useMessageStore()
    const settings = useSettingStore()
    const session = useSessionStore()
    const user = useUserStore()
    const i18n = useGettext()
    const theme = useTheme()

    return {
      dirty,
      session,
      settings,
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
    // loads the signed-in user into the user store if not available yet
    this.session.user().catch((error) => {
      this.messages.error(this.$gettext('Failed to load user'), error)
    })
  },

  computed: {
    // the user store is cleared on logout and updated on relogin
    me() {
      return this.user.me
    }
  },

  methods: {
    change(code) {
      return switchLanguage(code, this.$vuetify)
        .then((applied) => {
          applied && this.settings.saveData('app', 'language', code)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error switching language'), error)
        })
    },

    toggleTheme() {
      this.theme.toggle()
      this.settings.saveData('app', 'theme', this.theme.global.name.value)
    },

    async logout() {
      // logging out resets the unsaved changes before the login route guard could ask for them
      if (this.dirty.dirty && !(await this.dirty.confirm())) {
        return
      }

      return this.session
        .logout()
        .catch((error) => {
          this.messages.error(this.$gettext('Logout failed'), error)
        })
        .finally(() => {
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

      <v-list-item v-for="(_, code) in i18n.available" :key="code" role="option" :aria-selected="code === i18n.current" @click="change(code)">
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
}
</style>
