import gettext, { load, match, mergeCatalog, mergeFallback, pluginLabel, ready, typeLabel } from '../../../js/i18n'
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

  it('merges the base theme catalog before the other themes', () => {
    cy.intercept('GET', '/vendor/cms/aura/i18n/de.json', {
      de: { title: { fn: 'Bezeichnung' }, profile: { st: 'Profil' } }
    })
    cy.intercept('GET', '/vendor/cms/theme/i18n/de.json', {
      de: { title: { fn: 'Titel' } }
    })

    cy.then(() => {
      plugins.i18n = {
        aura: '/vendor/cms/aura/i18n/{locale}.json',
        cms: '/vendor/cms/theme/i18n/{locale}.json'
      }

      return ready.then(() => load('de')).then(() => {
        expect(gettext.$pgettext('fn', 'title')).to.equal('Titel')
        expect(gettext.$pgettext('st', 'profile')).to.equal('Profil')
      })
    })
  })

  it('translates prefixed types by their unprefixed name as fallback', () => {
    const translator = {
      $pgettext: (context, msgid) => ({ 'st:profile': 'Profil', 'st:aura::card': 'Aura-Karte' })[`${context}:${msgid}`] || msgid
    }

    expect(typeLabel('aura::card', translator)).to.equal('Aura-Karte')
    expect(typeLabel('aura::profile', translator)).to.equal('Profil')
    expect(typeLabel('aura::other', translator)).to.equal('aura other')
    expect(typeLabel('profile', translator)).to.equal('Profil')
    expect(typeLabel('heading', translator)).to.equal('heading')
    expect(typeLabel(undefined, translator)).to.equal('')
  })

  it('adds missing messages of the fallback language', () => {
    const messages = {
      Save: 'Salvar',
      Close: { '': 'Fechar' },
      title: { fn: 'Título' }
    }
    const fallback = {
      Save: 'Guardar',
      Close: { '': 'Fechar janela', dialog: 'Fechar diálogo' },
      title: { fn: 'Titulo', fh: 'Título da página' },
      Done: ['Concluído', 'Concluídos']
    }

    expect(mergeFallback(messages, fallback)).to.deep.equal({
      Save: 'Salvar',
      Close: { '': 'Fechar', dialog: 'Fechar diálogo' },
      title: { fn: 'Título', fh: 'Título da página' },
      Done: ['Concluído', 'Concluídos']
    })
    expect(messages.title).to.deep.equal({ fn: 'Título' })
  })

  it('uses the base language catalogs for missing regional messages', () => {
    cy.intercept('GET', '/vendor/cms/fallback/i18n/pt-BR.json', {
      'pt-BR': { title: { fn: 'Título' } }
    })
    cy.intercept('GET', '/vendor/cms/fallback/i18n/pt.json', {
      pt: { title: { fn: 'Titulo PT' }, gallery: { fn: 'Galeria' } }
    })

    cy.then(() => {
      plugins.i18n = { fallback: '/vendor/cms/fallback/i18n/{locale}.json' }

      return ready.then(() => load('pt-BR')).then(() => {
        expect(gettext.current).to.equal('pt-BR')
        expect(gettext.$pgettext('fn', 'title')).to.equal('Título')
        expect(gettext.$pgettext('fn', 'gallery')).to.equal('Galeria')
      })
    })
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
