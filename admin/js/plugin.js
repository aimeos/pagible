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

/**
 * Adds the lazy host-owned UI surface to an external plugin component.
 */
export function pluginUi(component) {
  return {
    ...component,
    components: { ...component.components, ...pluginComponents }
  }
}
