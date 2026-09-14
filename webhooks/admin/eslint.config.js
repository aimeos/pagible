import pluginVue from 'eslint-plugin-vue'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'

export default [
  {
    files: ['**/*.{js,mjs,jsx,vue}'],
  },
  {
    ignores: ['**/dist/**', '**/node_modules/**'],
  },
  ...pluginVue.configs['flat/essential'],
  {
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
  skipFormatting,
]
