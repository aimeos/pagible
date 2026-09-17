/** @license MIT, https://opensource.org/license/mit */

<script>
import File from './File.vue'

export default {
  extends: File,
  setup: File.setup,

  computed: {
    kind() {
      return 'media'
    }
  }
}
</script>
