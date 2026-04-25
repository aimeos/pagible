import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vuetify({ styles: { configFile: 'js/styles/settings.scss' } })
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./js', import.meta.url))
    },
    dedupe: ['pinia', 'vue']
  },
  optimizeDeps: {
    include: [
      'pinia',
      'vue',
      'vue3-gettext',
      'dompurify',
      'vuetify/components/VAlert',
      'vuetify/components/VApp',
      'vuetify/components/VAppBar',
      'vuetify/components/VAutocomplete',
      'vuetify/components/VBtn',
      'vuetify/components/VCard',
      'vuetify/components/VCheckbox',
      'vuetify/components/VChip',
      'vuetify/components/VCombobox',
      'vuetify/components/VDataTable',
      'vuetify/components/VDatePicker',
      'vuetify/components/VDialog',
      'vuetify/components/VDivider',
      'vuetify/components/VExpansionPanel',
      'vuetify/components/VFileInput',
      'vuetify/components/VForm',
      'vuetify/components/VGrid',
      'vuetify/components/VIcon',
      'vuetify/components/VImg',
      'vuetify/components/VLayout',
      'vuetify/components/VList',
      'vuetify/components/VMain',
      'vuetify/components/VMenu',
      'vuetify/components/VNavigationDrawer',
      'vuetify/components/VNumberInput',
      'vuetify/components/VPagination',
      'vuetify/components/VProgressCircular',
      'vuetify/components/VProgressLinear',
      'vuetify/components/VRadio',
      'vuetify/components/VRadioGroup',
      'vuetify/components/VRangeSlider',
      'vuetify/components/VSelect',
      'vuetify/components/VSheet',
      'vuetify/components/VSlider',
      'vuetify/components/VSnackbarQueue',
      'vuetify/components/VSwitch',
      'vuetify/components/VTable',
      'vuetify/components/VTabs',
      'vuetify/components/VTextarea',
      'vuetify/components/VTextField',
      'vuetify/components/VTimeline',
      'vuetify/components/VToolbar',
      'vuetify/components/VWindow',
      'vuetify/labs/VColorInput',
      'vuetify/labs/VDateInput',
    ]
  },
  build: {
    manifest: true,
  },
  experimental: {
    renderBuiltUrl: () => {
      return { relative: true }
    },
  },
})
