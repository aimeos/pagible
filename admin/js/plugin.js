/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineAsyncComponent } from 'vue'
import {
  VAlert,
  VAutocomplete,
  VAvatar,
  VBtn,
  VCard,
  VCardActions,
  VCardText,
  VCardTitle,
  VCheckbox,
  VCheckboxBtn,
  VChip,
  VCol,
  VCombobox,
  VContainer,
  VDialog,
  VDivider,
  VFileInput,
  VForm,
  VIcon,
  VImg,
  VList,
  VListGroup,
  VListItem,
  VListItemTitle,
  VListSubheader,
  VMenu,
  VPagination,
  VProgressCircular,
  VProgressLinear,
  VRadio,
  VRadioGroup,
  VRow,
  VSelect,
  VSheet,
  VSpacer,
  VSwitch,
  VTab,
  VTable,
  VTabs,
  VTabsWindow,
  VTabsWindowItem,
  VTextField,
  VTextarea,
  VToolbar,
  VToolbarTitle,
  VWindow,
  VWindowItem
} from 'vuetify/components'

/**
 * Stable host-owned Vuetify surface for external admin panels.
 *
 * Keep this to common form, layout, list, feedback and navigation primitives.
 * Host-shell components, Labs components and heavy specialized widgets stay
 * private until an extension has a concrete need for them.
 */
export const pluginComponents = Object.freeze({
  VAlert,
  VAutocomplete,
  VAvatar,
  VBtn,
  VCard,
  VCardActions,
  VCardText,
  VCardTitle,
  VCheckbox,
  VCheckboxBtn,
  VChip,
  VCol,
  VCombobox,
  VContainer,
  VDialog,
  VDivider,
  VFileInput,
  VForm,
  VIcon,
  VImg,
  VList,
  VListGroup,
  VListItem,
  VListItemTitle,
  VListSubheader,
  VMenu,
  VPagination,
  VProgressCircular,
  VProgressLinear,
  VRadio,
  VRadioGroup,
  VRow,
  VSelect,
  VSheet,
  VSpacer,
  VSwitch,
  VTab,
  VTable,
  VTabs,
  VTabsWindow,
  VTabsWindowItem,
  VTextField,
  VTextarea,
  VToolbar,
  VToolbarTitle,
  VWindow,
  VWindowItem,
  CmsActionMenu: defineAsyncComponent(() => import('./components/ActionMenu.vue')),
  CmsDialog: defineAsyncComponent(() => import('./components/Dialog.vue')),
  CmsFilePicker: defineAsyncComponent(() => import('./components/FileDialog.vue')),
  CmsLoadingSpinner: defineAsyncComponent(() => import('./components/LoadingSpinner.vue'))
})

// plugin components with the host UI surface, reused for components shared by several parents
const wrapped = new WeakMap()

/**
 * Adds the lazy host-owned UI surface to an external plugin component and its child components.
 */
export function pluginUi(component) {
  if (wrapped.has(component)) {
    return wrapped.get(component)
  }

  const result = { ...component }
  const components = { ...component.components }

  // registered before the children are wrapped to stop at recursive components
  wrapped.set(component, result)

  for (const [name, child] of Object.entries(components)) {
    // async and functional components can't get local components
    if (child && typeof child === 'object' && !child.__asyncLoader) {
      components[name] = pluginUi(child)
    }
  }

  result.components = { ...components, ...pluginComponents }
  return result
}
