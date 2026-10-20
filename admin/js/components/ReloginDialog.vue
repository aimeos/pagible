/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiAlertOctagon, mdiOpenInNew } from '@mdi/js'
import { urllogin } from '../config'
import { loginError } from '../graphql'
import router from '../routes'
import { useSessionStore, useUserStore } from '../stores'
import { loginUrl } from '../urls'
import CmsDialog from './Dialog.vue'
import PasswordField from './PasswordField.vue'

export default {
  components: {
    CmsDialog,
    PasswordField
  },

  data: () => ({
    error: null,
    loading: false,
    password: '',
    urllogin: urllogin
  }),

  setup() {
    const session = useSessionStore()
    const user = useUserStore()
    return { session, user, mdiAlertOctagon, mdiOpenInNew }
  },

  mounted() {
    document.addEventListener('focusin', this.focusin)
  },

  unmounted() {
    document.removeEventListener('focusin', this.focusin)
  },

  watch: {
    'session.expired'(expired) {
      if (!expired) {
        this.error = null
        this.password = ''
        this.$refs.password?.hide()
      }
    }
  },

  methods: {
    /**
     * Moves the focus to the login field whenever it enters the dialog from outside
     *
     * Views below can grab the focus while the dialog opens (e.g. autofocus of the chat input).
     * The focus trap of the dialog then moves it to the first button, which is "Logout", so the
     * typed password would get lost and pressing Enter would log out the user.
     */
    focusin(event) {
      const content = this.$refs.form?.closest('.v-overlay__content')

      if (
        !this.session.expired ||
        !content?.contains(event.target) ||
        content.contains(event.relatedTarget)
      ) {
        return
      }

      const field = this.urllogin ? this.$refs.signin : this.$refs.password

      if (field?.$el && !field.$el.contains(event.target)) {
        this.urllogin ? field.$el.focus() : field.focus()
      }
    },

    async logout() {
      await this.session.expire()
      router.push({ name: 'login' })
    },

    open() {
      this.error = null
      window.open(loginUrl(this.urllogin), '_blank', 'noopener')
    },

    resume() {
      if (this.loading) {
        return
      }

      this.error = null
      this.loading = true

      this.session
        .resume()
        .then((resumed) => {
          if (!resumed && this.session.expired) {
            this.error = this.$gettext('You are not signed in yet')
          }
        })
        .catch((error) => {
          this.error = loginError(error)
        })
        .finally(() => {
          this.loading = false
        })
    },

    submit() {
      if (this.urllogin) {
        return this.resume()
      }

      if (!this.password || this.loading) {
        return
      }

      this.error = null
      this.loading = true

      this.session
        .relogin(this.password)
        .catch((error) => {
          this.error = loginError(error)
        })
        .finally(() => {
          this.loading = false
        })
    }
  }
}
</script>

<template>
  <CmsDialog
    :model-value="session.expired"
    :title="$gettext('Session expired')"
    :close-label="$gettext('Logout')"
    :card-loading="loading"
    :max-width="440"
    toolbar-color="warning"
    content-class="relogin-body"
    role="alertdialog"
    aria-describedby="relogin-description"
    persistent
    @update:model-value="logout()"
  >
    <form id="relogin-form" ref="form" @submit.prevent="submit()">
      <p v-if="urllogin" id="relogin-description" class="relogin-text">
        {{
          $gettext(
            'Please sign in again in the new tab and return here to continue. Your unsaved changes are kept.'
          )
        }}
      </p>
      <p v-else id="relogin-description" class="relogin-text">
        {{ $gettext('Please sign in again to continue. Your unsaved changes are kept.') }}
      </p>

      <v-btn
        v-if="urllogin"
        ref="signin"
        @click="open()"
        :append-icon="mdiOpenInNew"
        variant="tonal"
        color="primary"
        class="relogin-open"
        block
      >
        {{ $gettext('Sign in') }}
      </v-btn>

      <template v-else>
        <input :value="user.me?.email" type="hidden" autocomplete="username" />

        <v-text-field
          :model-value="user.me?.email"
          :label="$gettext('E-Mail')"
          variant="underlined"
          readonly
        />
        <PasswordField ref="password" v-model="password" autofocus />
      </template>

      <v-alert v-if="error" color="error" :icon="mdiAlertOctagon" class="relogin-error">
        {{ error }}
      </v-alert>
    </form>

    <template #actions>
      <v-btn @click="logout()" variant="text">
        {{ $gettext('Logout') }}
      </v-btn>
      <v-btn
        type="submit"
        form="relogin-form"
        :disabled="!urllogin && !password"
        :loading="loading"
        variant="tonal"
        color="primary"
      >
        {{ urllogin ? $gettext('Continue') : $gettext('Login') }}
      </v-btn>
    </template>
  </CmsDialog>
</template>

<style scoped>
.relogin-text {
  margin: 0 0 16px;
  line-height: 1.5;
}

.relogin-open {
  margin-bottom: 8px;
}

.relogin-error {
  margin-top: 8px;
}
</style>
