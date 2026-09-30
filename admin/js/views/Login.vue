/** @license MIT, https://opensource.org/license/mit */

<script>
import router from '../routes'
import { urllogin } from '../config'
import { useUserStore, useMessageStore } from '../stores'
import { browser, loginUrl } from '../utils'
import { mdiEyeOff, mdiEye, mdiAlertOctagon } from '@mdi/js'

// Stops redirecting to the single sign-on page if it didn't sign in the user within that time
const SSO_RETRY = 60000

export default {
  props: {
    // Login page of the application if users don't sign in with a password (cms.admin.login)
    urllogin: { type: String, default: urllogin }
  },

  data: () => ({
    creds: {
      email: '',
      password: ''
    },
    autofilled: false,
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

  watch: {
    'creds.email': function () { this.$refs.form?.validate() },
    'creds.password': function () { this.$refs.form?.validate() }
  },

  mounted() {
    this.$el.addEventListener('animationstart', this.detectAutofill)
  },

  beforeUnmount() {
    this.$el.removeEventListener('animationstart', this.detectAutofill)
  },

  created() {
    this.emailRules = [
      (v) => !!v || this.$gettext('Field is required'),
      (v) => !!v.match(/.+@.+/) || this.$gettext('Invalid e-mail address')
    ]
    this.passwordRules = [(v) => !!v || this.$gettext('Field is required')]

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
        if (this.urllogin) {
          return this.sso(true)
        }

        this.login = true
      })
  },

  methods: {
    detectAutofill(e) {
      if (e.animationName === 'autofill-detect') {
        this.autofilled = true
      }
    },

    cmslogin() {
      if (this.autofilled) {
        this.$el.querySelectorAll('input').forEach((input) => {
          if (input.matches('[autocomplete="username"]')) {
            this.creds.email = input.value
          } else if (input.matches('[autocomplete="current-password"]')) {
            this.creds.password = input.value
          }
        })
        this.autofilled = false
      }

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

    /**
     * Redirects to the login page of the application for single sign-on
     *
     * Without a click, it's only done when the admin panel is opened, not after the user
     * logged out, and not again if the last redirect didn't sign in the user to avoid loops.
     *
     * @param {Boolean} auto TRUE if not requested by the user
     */
    sso(auto = false) {
      const now = Date.now()
      let last = 0

      try {
        last = parseInt(sessionStorage.getItem('cms-sso')) || 0
      } catch {
        // storage not available, e.g. in private mode
      }

      if (auto && (window.history.state?.back || now - last < SSO_RETRY)) {
        if (!window.history.state?.back) {
          this.error = this.$gettext('Login failed')
        }

        this.login = true
        return
      }

      try {
        sessionStorage.setItem('cms-sso', String(now))
      } catch {
        // storage not available, e.g. in private mode
      }

      const back = new URL(router.resolve(this.user.intended() || '/').href, window.location.origin)
      browser.assign(loginUrl(this.urllogin, back.href))
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
  <v-form
    ref="form"
    class="login"
    :class="{ show: login }"
    v-model="form"
    @submit.prevent="urllogin ? sso() : cmslogin()"
  >
    <v-card :loading="loading" :elevation="2" :class="{ error: error }">
      <template v-slot:title><h1>PagibleAI CMS</h1></template>

      <v-card-text>
        <template v-if="!urllogin">
          <v-text-field
            v-model="creds.email"
            :label="$gettext('E-Mail')"
            :rules="emailRules"
            autocomplete="username"
            variant="underlined"
            validate-on="blur"
            autofocus
          />
          <v-text-field
            v-model="creds.password"
            :type="show ? `text` : `password`"
            :label="$gettext('Password')"
            :rules="passwordRules"
            :placeholder="autofilled ? '********' : undefined"
            :persistent-placeholder="autofilled"
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
        </template>
        <v-alert v-show="error" color="surface" border="start" border-color="error">
          <template v-slot:prepend>
            <v-icon color="error" :icon="mdiAlertOctagon" />
          </template>
          {{ $gettext('Error') + ': ' + error }}
        </v-alert>
      </v-card-text>

      <v-card-actions>
        <v-btn v-if="urllogin" type="submit" variant="tonal">
          {{ $gettext('Sign in') }}
        </v-btn>
        <v-btn v-else type="submit" variant="tonal" :disabled="form != true && !autofilled">
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
  background: rgb(var(--v-theme-background));
  height: 100vh;
  width: 100%;
}

.login .v-card {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  border-radius: 16px;
  box-shadow:
    0 24px 48px -16px rgba(var(--v-shadow-color), 0.6),
    0 0 64px -8px rgba(var(--v-theme-primary), 0.45);
  padding: 8px;
  width: 20rem;
  opacity: 0;
  transform: translateY(12px);
}

.login.show .v-card {
  opacity: 1;
  transform: none;
  transition: opacity 0.5s, transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
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

/* the button has no color, so it inherits the foreground color of the card */
.login .v-card-actions .v-btn--variant-tonal .v-btn__underlay {
  opacity: 0.16;
}

/* tonal buttons use the surface text color by default which is dark on the colored card */
.login .v-card.v-theme--light .v-card-actions .v-btn__content {
  color: rgb(var(--v-theme-on-primary-darken-1, var(--v-theme-on-primary)));
}

.login .v-card.v-theme--dark .v-card-actions .v-btn__content {
  color: rgb(var(--v-theme-on-primary));
}

/*
 * WCAG 2.2 AAA (7:1) for text on the colored card: the light card uses the darker primary
 * shade with its foreground color, labels and input text are fully opaque and the error
 * color of the fields is the foreground color of the card as no theme color contrasts enough
 */
.login .v-card.v-theme--light {
  background: rgb(var(--v-theme-primary-darken-1, var(--v-theme-primary)));
  color: rgb(var(--v-theme-on-primary-darken-1, var(--v-theme-on-primary)));
}

.login .v-card .v-label,
.login .v-card .v-field__input {
  opacity: 1;
}

.login .v-card.v-theme--light .v-input {
  --v-theme-error: var(--v-theme-on-primary-darken-1, var(--v-theme-on-primary));
}

.login .v-card.v-theme--dark .v-input {
  --v-theme-error: var(--v-theme-on-primary);
}

.login .error {
  animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
  transform: translate3d(0, 0, 0);
}

@keyframes autofill-detect {
  from { opacity: 0.99; }
  to { opacity: 1; }
}

.login input:-webkit-autofill {
  animation: autofill-detect 0.1s;
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
