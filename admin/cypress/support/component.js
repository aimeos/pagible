/**
 * Cypress component-test support file.
 *
 * Sets up cy.mount() with Vuetify, Pinia, vue3-gettext, the v-safe-svg
 * directive and sensible stubs/provides so individual test files stay lean.
 *
 * Every component is wrapped in <v-app> (via render function) to provide the
 * Vuetify layout injection that v-navigation-drawer, v-dialog, etc. require.
 *
 * Because the component under test is rendered dynamically via h(), VTU cannot
 * traverse into it to apply stubs on locally-registered children. We work
 * around this by temporarily patching the component's `components` option
 * in-place (and restoring it on the next mount).
 */

import { mount } from 'cypress/vue'
import { h } from 'vue'
import { createVuetify } from 'vuetify'
import { VApp } from 'vuetify/components'
import * as components from 'vuetify/components'
import * as labsComponents from 'vuetify/labs/components'
import * as directives from 'vuetify/directives'
import { createPinia, setActivePinia } from 'pinia'
import { createGettext } from 'vue3-gettext'
import DOMPurify from 'dompurify'

import 'vuetify/styles'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Convert a template-based stub to a render-function stub.
 * The Vue runtime build (used by Vite) doesn't include the template compiler,
 * so stubs patched onto locally-registered components need render functions.
 */
function compileStub(stub) {
  if (typeof stub !== 'object' || !stub.template || stub.render) return stub

  const tpl = stub.template.trim()
  const { template: _, ...rest } = stub // preserve props, data, methods, emits, etc.

  // Self-closing tag: <div class="foo" />
  const selfClose = tpl.match(/^<(\w+)(?:\s+class="([^"]*)")?\s*\/>$/)
  if (selfClose) {
    const [, tag, cls] = selfClose
    return { ...rest, render() { return h(tag, cls ? { class: cls } : {}) } }
  }

  // Tag with <slot />: <a><slot /></a>
  const withSlot = tpl.match(/^<(\w+)(?:\s+class="([^"]*)")?><slot\s*\/><\/\1>$/)
  if (withSlot) {
    const [, tag, cls] = withSlot
    return { ...rest, render() { return h(tag, cls ? { class: cls } : {}, this.$slots.default?.()) } }
  }

  return stub // fallback – may fail if compiler is absent
}

// Stores restore functions from the previous mount to undo in-place patches.
let _restorePrevious = null

// ---------------------------------------------------------------------------
// cy.mount
// ---------------------------------------------------------------------------

Cypress.Commands.add('mount', (Component, options = {}) => {
  // Restore any in-place component patches from the previous mount.
  if (_restorePrevious) {
    _restorePrevious()
    _restorePrevious = null
  }

  const vuetify = createVuetify({
    components: { ...components, ...labsComponents },
    directives,
    icons: { defaultSet: 'mdi' },
    defaults: {
      VDialog: { scrollStrategy: 'none' },
      VMenu: { scrollStrategy: 'none' },
      VNavigationDrawer: { disableResizeWatcher: true },
    },
  })

  const pinia = createPinia()
  setActivePinia(pinia)

  const gettext = createGettext({
    defaultLanguage: 'en',
    translations: {},
    silent: true,
  })

  const { props = {}, ...restOptions } = options
  const testStubs = restOptions.global?.stubs || {}

  // Patch locally-registered child components with stubs in-place.
  // VTU can't reach into dynamically-rendered children (via h()),
  // so we mutate the component's `components` option directly.
  // Originals are saved and restored at the start of the next mount.
  //
  // IMPORTANT: stubs that are applied in-place are filtered OUT of the
  // stubs passed to VTU, because VTU's global stub mechanism would
  // otherwise override our in-place patch with a template-based stub
  // that can't compile at runtime (no template compiler in Vue runtime).
  const localStubNames = new Set()
  if (Component.components) {
    const originals = {}
    for (const [name, stub] of Object.entries(testStubs)) {
      if (name in Component.components) {
        originals[name] = Component.components[name]
        Component.components[name] = compileStub(stub)
        localStubNames.add(name)
      }
    }
    if (Object.keys(originals).length > 0) {
      _restorePrevious = () => {
        for (const [name, orig] of Object.entries(originals)) {
          Component.components[name] = orig
        }
      }
    }
  }

  // Build VTU stubs: only non-locally-patched stubs go to VTU.
  const vtuStubs = {}
  for (const [name, stub] of Object.entries(testStubs)) {
    if (!localStubNames.has(name)) {
      vtuStubs[name] = stub
    }
  }

  const Wrapper = {
    name: 'CypressWrapper',
    render() {
      return h(VApp, null, {
        default: () => h(Component, props),
      })
    },
  }

  return mount(Wrapper, {
    ...restOptions,
    global: {
      plugins: [vuetify, pinia, gettext, ...(restOptions.global?.plugins ?? [])],
      directives: {
        'safe-svg': (el, binding) => {
          el.innerHTML = DOMPurify.sanitize(binding.value ?? '', {
            USE_PROFILES: { svg: true, svgFilters: true },
          })
        },
        ...restOptions.global?.directives,
      },
      mocks: {
        $log: () => {},
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
        },
        ...restOptions.global?.mocks,
      },
      provide: {
        debounce: (fn) => fn,
        write: () => Promise.resolve(''),
        translate: () => Promise.resolve(''),
        transcribe: () => Promise.resolve(''),
        txlocales: [],
        url: (path) => path,
        srcset: () => '',
        openView: () => {},
        ...restOptions.global?.provide,
      },
      stubs: {
        RouterLink: { render() { return h('a', {}, this.$slots.default?.()) } },
        RouterView: { render() { return h('div') } },
        ...vtuStubs,
      },
    },
  })
})
