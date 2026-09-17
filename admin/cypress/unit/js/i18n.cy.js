import gettext, { load, mergeCatalog, pluginLabel, ready } from '../../../js/i18n'
import { plugins } from '../../../js/config'

describe('plugin translations', () => {
  afterEach(() => {
    plugins.i18n = {}
  })

  it('adds only the registered context and preserves core messages', () => {
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
        Save: { '': 'Speichern', webhooks: 'Webhook speichern' },
        Done: { '': ['Fertig', 'Fertig'], webhooks: ['Webhook fertig', 'Webhooks fertig'] },
        Close: { '': 'Schließen', dialog: 'Dialog schließen', webhooks: 'Webhook schließen' }
      }
    })
    expect(translations.de.Save).to.equal('Speichern')
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
