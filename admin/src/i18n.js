import { createGettext } from "vue3-gettext";

const gettext = createGettext({
  defaultLanguage: "en",
  translations: {},
  silent: true,
});

import(`../i18n/LINGUAS?raw`).then(content => {
  const supported = content.default.split(' ')
  const locale = (navigator.languages || [navigator.language])
    .map(lang => lang?.toLowerCase()?.slice(0, 2))
    .find(lang => supported.includes(lang))
    || 'en'

  gettext.available = Object.fromEntries(supported.map(value => [value, value]))

  import(`../i18n/${locale}.json`).then(translations => {
    gettext.translations = translations.default || translations
    gettext.current = locale
  })
})

export default gettext
