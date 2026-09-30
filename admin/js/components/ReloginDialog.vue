/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiAlertOctagon, mdiEye, mdiEyeOff, mdiOpenInNew } from '@mdi/js'
import { urllogin } from '../config'
import router from '../routes'
import { useUserStore } from '../stores'
import { loginUrl } from '../utils'
import CmsDialog from './Dialog.vue'

export default {
  components: {
    CmsDialog
  },

  data: () => ({
    error: null,
    loading: false,
    password: '',
    show: false,
    urllogin: urllogin
  }),

  setup() {
    const user = useUserStore()
    return { user, mdiAlertOctagon, mdiEye, mdiEyeOff, mdiOpenInNew }
  },

  mounted() {
    document.addEventListener('focusin', this.focusin)
  },

  unmounted() {
    document.removeEventListener('focusin', this.focusin)
  },

  watch: {
    'user.expired'(expired) {
      if (!expired) {
        this.error = null
        this.password = ''
        this.show = false
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
        !this.user.expired ||
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
      await this.user.expire()
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

      this.user
        .resume()
        .then((resumed) => {
          if (!resumed && this.user.expired) {
            this.error = this.$gettext('You are not signed in yet')
          }
        })
        .catch((error) => {
          this.error = error?.message || this.$gettext('Login failed')
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

      this.user
        .relogin(this.password)
        .catch((error) => {
          this.error = error?.message || this.$gettext('Login failed')
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
    :model-value="user.expired"
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
        <v-text-field
          ref="password"
          v-model="password"
          :type="show ? 'text' : 'password'"
          :label="$gettext('Password')"
          autocomplete="current-password"
          variant="underlined"
          autofocus
        >
          <template #append-inner>
            <v-btn
              @click="show = !show"
              :aria-label="show ? $gettext('Hide password') : $gettext('Show password')"
              :icon="show ? mdiEyeOff : mdiEye"
              density="compact"
              variant="text"
            />
          </template>
        </v-text-field>
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
