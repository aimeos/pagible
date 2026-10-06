/** @license MIT, https://opensource.org/license/mit */

<script>
import { VCombobox } from 'vuetify/components/VCombobox'
import Autocomplete from './Autocomplete.vue'

export default {
  extends: Autocomplete,

  computed: {
    returnObject() {
      return false
    },

    tag() {
      return VCombobox
    }
  }
}
</script>
