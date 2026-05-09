/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

export default {
  mounted(el, binding) {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (typeof binding.value === 'function') {
          binding.value(entry.isIntersecting)
        }
      },
      { threshold: 0 }
    )
    observer.observe(el)
    el._observer = observer
  },

  unmounted(el) {
    el._observer?.disconnect()
  }
}
