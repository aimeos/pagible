/**
 * @license MIT, https://opensource.org/license/mit
 */

import {
  VAlert,
  VBtn,
  VCard,
  VCardActions,
  VCardText,
  VCardTitle,
  VCheckboxBtn,
  VChip,
  VContainer,
  VDialog,
  VProgressLinear,
  VSelect,
  VSpacer,
  VSwitch,
  VTable,
  VTextField
} from 'vuetify/components'

const components = {
  VAlert,
  VBtn,
  VCard,
  VCardActions,
  VCardText,
  VCardTitle,
  VCheckboxBtn,
  VChip,
  VContainer,
  VDialog,
  VProgressLinear,
  VSelect,
  VSpacer,
  VSwitch,
  VTable,
  VTextField
}

/**
 * Adds the lazy host-owned UI surface to an external plugin component.
 */
export function pluginUi(component) {
  return {
    ...component,
    components: { ...component.components, ...components }
  }
}
