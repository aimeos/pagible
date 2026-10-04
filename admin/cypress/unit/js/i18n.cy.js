import gettext, { load, match, mergeCatalog, pluginLabel, ready } from '../../../js/i18n'
import { plugins } from '../../../js/config'

describe('plugin translations', () => {
  afterEach(() => {
    plugins.i18n = {}
  })

  it('adds the registered context and preserves core messages', () => {
    const translations = {
      de: {
        Save: 'Speichern',
        Done: ['Fertig', 'Fertig'],
        Close: { '': 'Schließen', dialog: 'Dialog schließen' }
      }
    }
    const catalog = {
      de: {
        Save: { webhooks: 'Webhook speichern', ignored: 'Ignored' },
        Done: { webhooks: ['Webhook fertig', 'Webhooks fertig'] },
        Close: { webhooks: 'Webhook schließen' },
        Add: { ignored: 'Ignored' }
      }
    }

    expect(mergeCatalog(translations, catalog, 'de', 'webhooks')).to.deep.equal({
      de: {
        Save: { '': 'Speichern', webhooks: 'Webhook speichern', ignored: 'Ignored' },
        Done: { '': ['Fertig', 'Fertig'], webhooks: ['Webhook fertig', 'Webhooks fertig'] },
        Close: { '': 'Schließen', dialog: 'Dialog schließen', webhooks: 'Webhook schließen' },
        Add: { ignored: 'Ignored' }
      }
    })
    expect(translations.de.Save).to.equal('Speichern')
  })

  it('adds missing contexts without overriding existing ones', () => {
    const translations = {
      de: {
        Price: { fn: 'Preis' }
      }
    }
    const catalog = {
      de: {
        Price: { fn: 'Kaufpreis', op: 'Preis' },
        property: { st: 'Immobilie' }
      }
    }

    expect(mergeCatalog(translations, catalog, 'de', 'estate')).to.deep.equal({
      de: {
        Price: { fn: 'Preis', op: 'Preis' },
        property: { st: 'Immobilie' }
      }
    })
  })

  it('translates labels in their package context', () => {
    const translator = {
      $gettext: (value) => `default:${value}`,
      $pgettext: (context, value) => `${context}:${value}`
    }

    expect(pluginLabel({ label: 'Pages' }, translator)).to.equal('default:Pages')
    expect(pluginLabel({ label: 'Webhooks', i18n: 'webhooks' }, translator)).to.equal('webhooks:Webhooks')
  })

  it('reloads package catalogs when the language changes', () => {
    cy.intercept('GET', '/vendor/cms/webhooks/i18n/de.json', {
      de: {
        Save: { webhooks: 'Webhook speichern' }
      }
    }).as('catalog')

    cy.then(() => {
      plugins.i18n = { webhooks: '/vendor/cms/webhooks/i18n/{locale}.json' }

      return ready.then(() => load('de')).then(() => {
        expect(gettext.$gettext('Save')).to.equal('Speichern')
        expect(gettext.$pgettext('webhooks', 'Save')).to.equal('Webhook speichern')
      })
    })

    cy.wait('@catalog')
  })

  it('caches catalogs and applies only the latest language request', () => {
    let requests = 0

    cy.intercept('GET', '/vendor/cms/cache/i18n/de.json', (request) => {
      requests++
      request.reply({ de: { Save: { cache: 'Zwischenspeichern' } } })
    })

    cy.then(() => {
      plugins.i18n = { cache: '/vendor/cms/cache/i18n/{locale}.json' }

      return ready.then(() => Promise.all([load('de'), load('de')])).then(([first, second]) => {
        expect(first).to.equal(false)
        expect(second).to.equal(true)
        expect(requests).to.equal(1)
        expect(gettext.$pgettext('cache', 'Save')).to.equal('Zwischenspeichern')
      })
    })
  })
})

describe('browser locale', () => {
  const supported = ['de', 'en', 'pt', 'pt-BR', 'zh', 'zh-TW']

  it('matches regional and base locales', () => {
    expect(match('pt-BR', supported)).to.equal('pt-BR')
    expect(match('pt-br', supported)).to.equal('pt-BR')
    expect(match('pt-PT', supported)).to.equal('pt')
    expect(match('de-AT', supported)).to.equal('de')
    expect(match('zh-CN', supported)).to.equal('zh')
    expect(match('zh-Hant-HK', supported)).to.equal('zh-TW')
    expect(match('zh-HK', supported)).to.equal('zh-TW')
    expect(match('fr', supported)).to.be.undefined
    expect(match(undefined, supported)).to.be.undefined
  })
})
