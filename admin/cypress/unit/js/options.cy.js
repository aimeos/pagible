import { createPinia, setActivePinia } from 'pinia'
import gettext from '../../../js/i18n'
import { languageFilter } from '../../../js/options'
import { useLanguageStore } from '../../../js/stores'

describe('languageFilter()', () => {
  let current, translations

  beforeEach(() => {
    setActivePinia(createPinia())
    useLanguageStore().available = ['en', 'de']
    current = gettext.current
    translations = gettext.translations
  })

  afterEach(() => {
    gettext.translations = translations
    gettext.current = current
  })

  it('lists all languages after the "All" entry', () => {
    const list = languageFilter('all', 'lang')

    expect(list.map((entry) => entry.value.lang)).to.deep.equal([null, 'en', 'de'])
    expect(list[1].icon).to.equal('lang')
    expect(languageFilter('all', 'lang')).to.equal(list)
  })

  it('translates the "All" entry again after the admin language changed', () => {
    gettext.current = 'en'
    expect(languageFilter('all', 'lang')[0].title).to.equal('All')

    gettext.translations = { ...translations, de: { ...translations?.de, All: 'Alle' } }
    gettext.current = 'de'
    expect(languageFilter('all', 'lang')[0].title).to.equal('Alle')
  })
})
