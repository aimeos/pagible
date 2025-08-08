<script>
  import { useGettext } from "vue3-gettext"
  import { useAuthStore, useLanguageStore, useMessageStore } from '../stores'

  export default {
    data: () => ({
      user: null
    }),

    setup() {
      const languages = useLanguageStore()
      const messages = useMessageStore()
      const auth = useAuthStore()
      const i18n = useGettext()

      return { auth, i18n, languages, messages }
    },

    created() {
      this.auth.user().then(user => {
        this.user = user
      }).catch(err => {
        this.messages.add(this.$gettext('Failed to load user'), 'error')
      })
    },

    methods: {
      logout() {
        this.auth.logout().then(() => {
          this.user = null
          this.$router.replace('/')
        }).catch(err => {
          this.messages.add(this.$gettext('Logout failed'), 'error')
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
  <v-menu>
    <template #activator="{ props }">
        <v-btn v-bind="props" :title="$gettext('Switch language')" icon="mdi-web" class="icon" />
    </template>
    <v-list>
      <v-list-item v-for="(_, code) in i18n.available" :key="code">
        <v-btn
          @click="change(code)"
          variant="text"
        >{{ languages.translate(code) }} ({{ code }})</v-btn>
      </v-list-item>
    </v-list>
  </v-menu>

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
