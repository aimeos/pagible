/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import router from '../routes'
import { useUserStore, useMessageStore } from '../stores'
import { mdiEyeOff, mdiEye, mdiAlertOctagon } from '@mdi/js'

export default {
  data: () => ({
    creds: {
      email: '',
      password: ''
    },
    form: null,
    error: null,
    loading: false,
    login: false,
    show: false
  }),

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return { user, messages, mdiEyeOff, mdiEye, mdiAlertOctagon }
  },

  created() {
    const config = window.__APP_CONFIG__ || {}

    // For pre-filled demo login
    this.creds.email = config.email ?? ''
    this.creds.password = config.password ?? ''

    this.user
      .isAuthenticated()
      .then((result) => {
        if (!result) {
          throw result
        }

        router.replace(this.next())
      })
      .catch((err) => {
        this.login = true
      })
  },

  methods: {
    cmslogin() {
      if (!this.creds.email || !this.creds.password) {
        return false
      }

      this.error = null
      this.loading = true

      this.user
        .login(this.creds.email, this.creds.password)
        .then((user) => {
          if (Object.values(user.permission || {}).some((perm) => perm === true)) {
            router.replace(this.next())
          } else {
            this.error = this.$gettext('Not a CMS editor')
          }
        })
        .catch((error) => {
          this.error = error.message
        })
        .finally(() => {
          this.loading = false
        })
    },

    next() {
      const url =
        this.user.intended() ||
        router.getRoutes().find((route) => {
          return this.user.can(route.name)
        })?.path

      if (!url) {
        this.messages.add(this.$gettext('Access denied'), 'error')
      }

      return url || '/'
    },

    toggleShow() {
      this.show = !this.show
    }
  }
}
</script>

<template>
  <v-form class="login" :class="{ show: login }" v-model="form" @submit.prevent="cmslogin()">
    <v-card :loading="loading" :elevation="2" :class="{ error: error }">
      <template v-slot:title><h1>PagibleAI CMS</h1></template>

      <v-card-text>
        <v-text-field
          v-model="creds.email"
          :label="$gettext('E-Mail')"
          :rules="[
            (v) => !!v || $gettext('Field is required'),
            (v) => !!v.match(/.+@.+/) || $gettext('Invalid e-mail address')
          ]"
          autocomplete="username"
          variant="underlined"
          validate-on="blur"
          autofocus
        />
        <v-text-field
          v-model="creds.password"
          :type="show ? `text` : `password`"
          :label="$gettext('Password')"
          :rules="[(v) => !!v || $gettext('Field is required')]"
          autocomplete="current-password"
          variant="underlined"
        >
          <template v-slot:append-inner>
            <v-btn
              @click="toggleShow"
              :aria-label="show ? $gettext('Hide password') : $gettext('Show password')"
              :icon="show ? mdiEyeOff : mdiEye"
              density="compact"
              variant="text"
            />
          </template>
        </v-text-field>
        <v-alert v-show="error" color="error" :icon="mdiAlertOctagon">
          {{ $gettext('Error') + ': ' + error }}
        </v-alert>
      </v-card-text>

      <v-card-actions>
        <v-btn type="submit" variant="outlined" :disabled="form != true">
          {{ $gettext('Login') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-form>
</template>

<style>
.login {
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgb(var(--v-theme-background));
  height: 100vh;
  width: 100%;
}

.login .v-card {
  background-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  border-radius: 8px;
  padding: 8px;
  width: 20rem;
  opacity: 0;
}

.login.show .v-card {
  opacity: 1;
  transition: opacity 0.5s;
}

.login .v-card-title {
  text-align: center;
}

.login .v-card-title h1 {
  font-size: 125%;
  margin-top: 0;
}

.login .v-card-actions {
  justify-content: center;
}

.login .v-theme--light,
.login .v-field--error,
.login .v-field--error:not(.v-field--disabled) .v-field__clearable > .v-icon {
  --v-theme-error: 255, 167, 38;
}

.login .error {
  animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
  transform: translate3d(0, 0, 0);
}

@keyframes shake {
  10%,
  90% {
    transform: translate3d(-1px, 0, 0);
  }

  20%,
  80% {
    transform: translate3d(2px, 0, 0);
  }

  30%,
  50%,
  70% {
    transform: translate3d(-4px, 0, 0);
  }

  40%,
  60% {
    transform: translate3d(4px, 0, 0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .login .error {
    animation: none;
  }
}
</style>
