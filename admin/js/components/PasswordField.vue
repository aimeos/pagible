/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiEye, mdiEyeOff } from '@mdi/js'

/**
 * Password input with a button to show the typed password, attributes are passed to the text field
 */
export default {
  data: () => ({
    show: false
  }),

  setup() {
    return { mdiEye, mdiEyeOff }
  },

  methods: {
    focus() {
      this.$refs.field?.focus()
    },

    // masks the password again
    hide() {
      this.show = false
    }
  }
}
</script>

<template>
  <v-text-field
    ref="field"
    :type="show ? 'text' : 'password'"
    :label="$gettext('Password')"
    autocomplete="current-password"
    variant="underlined"
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
