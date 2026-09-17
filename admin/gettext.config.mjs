import { readFileSync } from 'node:fs'

const locales = readFileSync(new URL('./i18n/LINGUAS', import.meta.url), 'utf8')
  .trim()
  .split(/\s+/)

export default {
  input: {
    path: './js',
    include: ['**/*.js', '**/*.ts', '**/*.vue']
  },
  output: {
    locales,
    path: './i18n',
    jsonPath: './',
    splitJson: true,
    fuzzyMatching: false
  }
}
