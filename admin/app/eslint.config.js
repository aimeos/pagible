import pluginVue from 'eslint-plugin-vue'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'

export default [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{js,mjs,jsx,vue}']
  },
  {
    name: 'app/files-to-ignore',
    ignores: ['**/dist/**', '**/coverage/**', '**/node_modules/**']
  },
  ...pluginVue.configs['flat/essential'],
  {
    name: 'app/rules',
    rules: {
      'vue/no-mutating-props': 'off',
      'vue/multi-word-component-names': 'off'
    }
  },
  skipFormatting
]
