import WebhookList from '../../../../webhooks/admin/src/views/WebhookList.vue'
import BuiltWebhookList from '../../../../webhooks/admin/dist/WebhookList.js'
import { pluginUi } from '../../../js/plugin'
import '../../../js/assets/base.css'

describe('WebhookList', () => {
  const context = {
    $vuetify: { locale: { current: 'en' } },
    $pgettext(context, value, params = {}) {
      const text = {
        'Access denied': 'Zugriff verweigert',
        'Blocked by server configuration': 'Durch Serverkonfiguration blockiert',
        'Can\'t be decrypted, replace the URL': 'Nicht entschlüsselbar, URL ersetzen',
        'Connection failed': 'Verbindung fehlgeschlagen',
        'Delivery failed': 'Zustellung fehlgeschlagen',
        'Host not found': 'Host nicht gefunden',
        'Not a valid URL': 'Keine gültige URL',
        'Queue unavailable': 'Warteschlange nicht verfügbar',
        'Redirects aren\'t followed': 'Weiterleitungen werden nicht befolgt',
        'Request timed out': 'Zeitüberschreitung der Anfrage',
        'Response too large': 'Antwort zu groß',
        'Secure connection failed': 'Sichere Verbindung fehlgeschlagen',
        'Test event delivered': 'Testereignis zugestellt',
        'Test event failed': 'Testereignis fehlgeschlagen'
      }[value] || value

      return text.replace(/%\{(\w+)\}/g, (match, name) => params[name] ?? match)
    }
  }
  context.dateText = WebhookList.methods.dateText.bind(context)
  context.reasonText = WebhookList.methods.reasonText.bind(context)

  it('translates stored delivery failure reasons', () => {
    const errorText = WebhookList.methods.errorText.bind(context)

    expect(errorText({ last_error: { reason: 'destination_not_allowed' } })).to.equal('Zugriff verweigert')
    expect(errorText({ last_error: { reason: 'response_headers_too_large' } })).to.equal('Antwort zu groß')
    expect(errorText({ last_error: { reason: 'timeout' } })).to.equal('Zeitüberschreitung der Anfrage')
    expect(errorText({ last_error: { reason: 'connection_failed' } })).to.equal('Verbindung fehlgeschlagen')
    expect(errorText({ last_error: { reason: 'resolution_failed' } })).to.equal('Host nicht gefunden')
    expect(errorText({ last_error: { reason: 'tls_error' } })).to.equal('Sichere Verbindung fehlgeschlagen')
    expect(errorText({ last_error: { reason: 'http_error', status: 301 } })).to.equal('Weiterleitungen werden nicht befolgt (301)')
    expect(errorText({ last_error: { reason: 'http_error', status: 410 } })).to.equal('Zustellung fehlgeschlagen (410)')
    expect(errorText({ last_error: { reason: 'invalid_url' } })).to.equal('Keine gültige URL')
    expect(errorText({ last_error: { reason: 'invalid_policy' } })).to.equal('Durch Serverkonfiguration blockiert')
    expect(errorText({ last_error: { reason: 'queue_failed' } })).to.equal('Warteschlange nicht verfügbar')
    expect(errorText({ last_error: { reason: 'invalid_encryption' } })).to.equal('Nicht entschlüsselbar, URL ersetzen')
    expect(errorText({ last_error: { reason: 'unexpected_reason', status: 503 } })).to.equal('Zustellung fehlgeschlagen (503)')
  })

  it('shows when the last delivery failure happened', () => {
    const errorText = WebhookList.methods.errorText.bind(context)
    const at = '2026-09-15T12:00:00.000000Z'

    expect(errorText({ last_error: null })).to.equal('None')
    expect(errorText({ last_error: { reason: 'http_error', status: 503, at } }))
      .to.equal(`Zustellung fehlgeschlagen (503) · ${new Date(at).toLocaleString('en')}`)
  })

  it('reports the result of a test event', async () => {
    const mutate = cy.stub()
    const paused = '2026-09-15T12:05:00.000000Z'
    const state = {
      ...context,
      apollo: { mutate },
      items: Object.freeze([
        Object.freeze({ id: 'first', paused_until: paused }),
        Object.freeze({ id: 'second', paused_until: paused })
      ]),
      messages: { add: cy.stub() },
      saving: false
    }
    state.change = WebhookList.methods.change.bind(state)

    mutate.resolves({ data: { pingWebhook: { success: false, status: null, reason: 'destination_not_allowed' } } })
    await WebhookList.methods.ping.call(state, { id: 'first' })

    expect(state.messages.add).to.have.been.calledWith('Testereignis fehlgeschlagen: Zugriff verweigert', 'error')
    expect(state.items[0].paused_until).to.equal(paused)

    // a successful test event resumes the paused deliveries
    mutate.resolves({ data: { pingWebhook: { success: true, status: 204, reason: null } } })
    await WebhookList.methods.ping.call(state, { id: 'first' })

    expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: 'first' })
    expect(state.messages.add).to.have.been.calledWith('Testereignis zugestellt (204)', 'success')
    expect(state.items).to.deep.equal([{ id: 'first', paused_until: null }, { id: 'second', paused_until: paused }])

    mutate.rejects(new Error('offline'))
    await WebhookList.methods.ping.call(state, { id: 'first' })

    expect(state.messages.add).to.have.been.calledWith('Testereignis fehlgeschlagen:\nError: offline', 'error')
    expect(state.saving).to.equal(false)
  })

  it('warns if the server configuration stops all deliveries', () => {
    const serverText = (server) => WebhookList.computed.serverText.call({ ...context, server })

    expect(serverText({ enabled: true, blocked: null })).to.equal('')
    expect(serverText({ enabled: true, blocked: 'invalid_policy' }))
      .to.equal('All deliveries are blocked by the server configuration')
    expect(serverText({ enabled: false, blocked: 'invalid_queue' }))
      .to.equal('Webhooks are disabled by the server configuration, no events are sent')
  })

  it('warns if no queue worker processes the deliveries', () => {
    const serverText = (server) => WebhookList.computed.serverText.call({ ...context, server })
    const since = '2026-09-15T12:00:00.000000Z'

    expect(serverText({ enabled: true, blocked: null, stalled_since: null })).to.equal('')
    expect(serverText({ enabled: true, blocked: null, stalled_since: since }))
      .to.equal(`No queued delivery was processed since ${new Date(since).toLocaleString('en')}, check the queue worker`)
    // configuration problems which block all deliveries are shown first
    expect(serverText({ enabled: true, blocked: 'invalid_policy', stalled_since: since }))
      .to.equal('All deliveries are blocked by the server configuration')
  })

  it('shows the server status and paused destinations', () => {
    const paused = '2026-09-15T12:05:00.000000Z'
    const query = cy.stub().resolves({
      data: {
        cmsWebhooks: [{
          id: 'first',
          name: '',
          endpoint: 'https://example.com/orders',
          events: ['page.published'],
          status: true,
          last_error: { reason: 'timeout', at: '2026-09-15T12:00:00.000000Z' },
          last_success_at: null,
          paused_until: paused
        }],
        cmsWebhookEvents: ['page.published'],
        cmsWebhookServer: { enabled: true, blocked: 'invalid_policy' }
      }
    })

    cy.mount(pluginUi(WebhookList), {
      global: {
        provide: {
          apollo: { query },
          messages: { add: cy.stub() }
        }
      }
    })

    cy.get('.webhook-server').should('contain', 'All deliveries are blocked by the server configuration')
    cy.get('[role="listitem"] .webhook-paused')
      .should('contain', `Paused until: ${new Date(paused).toLocaleString('en')}`)
    cy.get('[role="listitem"]').should('contain', 'Request timed out')
  })

  it('shows whether a delivery has succeeded', () => {
    const successText = WebhookList.methods.successText.bind(context)

    expect(successText({ last_success_at: null })).to.equal('None')
    expect(successText({ last_success_at: '2026-09-15T12:00:00.000000Z' })).not.to.equal('None')
  })

  it('updates immutable query result snapshots without mutating them', async () => {
    const original = Object.freeze({ id: 'first', status: false })
    const updated = Object.freeze({ id: 'first', status: true })
    const webhooks = Object.freeze([original])
    const names = Object.freeze(['page.published'])
    const state = {
      apollo: { query: cy.stub().resolves({ data: { cmsWebhooks: webhooks, cmsWebhookEvents: names } }) },
      checked: new Set(['old']),
      items: [],
      loading: false,
      messages: { add: cy.stub() },
      names: [],
      $pgettext: (context, value) => value
    }

    await WebhookList.methods.load.call(state)

    expect(state.items).to.equal(webhooks)
    expect(state.names).to.equal(names)
    expect(state.server).to.deep.equal({ enabled: true, blocked: null, stalled_since: null })

    WebhookList.methods.put.call(state, updated)

    expect(state.items).to.deep.equal([updated])
    expect(state.items).not.to.equal(webhooks)
    expect(state.checked.size).to.equal(0)
    expect(webhooks).to.deep.equal([original])
  })

  it('adds a webhook to an immutable query result snapshot', async () => {
    const webhooks = Object.freeze([])
    const webhook = Object.freeze({ id: 'new' })
    const mutate = cy.stub().resolves({ data: { addWebhook: { secret: 'secret', webhook } } })
    const state = {
      apollo: { mutate },
      checked: new Set(),
      dialog: true,
      events: ['page.published'],
      items: webhooks,
      messages: { add: cy.stub() },
      name: ' Orders ',
      saving: false,
      secret: '',
      secretDialog: false,
      selected: null,
      status: true,
      url: 'https://example.com/hook',
      $pgettext: (context, value) => value
    }
    state.change = WebhookList.methods.change.bind(state)
    state.put = WebhookList.methods.put.bind(state)
    state.validUrl = WebhookList.methods.validUrl.bind(state)

    await WebhookList.methods.save.call(state)

    expect(mutate.firstCall.args[0].variables.input).to.deep.equal({
      url: 'https://example.com/hook',
      name: 'Orders',
      events: ['page.published'],
      status: true
    })
    expect(state.items).to.deep.equal([webhook])
    expect(state.items).not.to.equal(webhooks)
    expect(state.secret).to.equal('secret')
    expect(state.dialog).to.equal(true)
    expect(state.secretDialog).to.equal(false)
  })

  it('saves the name of an existing webhook', async () => {
    const webhook = Object.freeze({ id: 'first', name: 'Orders' })
    const mutate = cy.stub().resolves({ data: { saveWebhook: webhook } })
    const state = {
      apollo: { mutate },
      checked: new Set(),
      dialog: true,
      events: ['page.published'],
      items: [],
      messages: { add: cy.stub() },
      name: ' Orders ',
      saving: false,
      selected: { id: 'first' },
      status: false,
      $pgettext: (context, value) => value
    }
    state.change = WebhookList.methods.change.bind(state)
    state.put = WebhookList.methods.put.bind(state)
    state.validUrl = WebhookList.methods.validUrl.bind(state)

    await WebhookList.methods.save.call(state)

    expect(mutate.firstCall.args[0].variables).to.deep.equal({
      id: 'first',
      input: { name: 'Orders', events: ['page.published'], status: false }
    })
    expect(state.items).to.deep.equal([webhook])
    expect(state.dialog).to.equal(false)
  })

  it('replaces the destination only with a valid URL', async () => {
    const mutate = cy.stub().resolves({ data: { replaceWebhook: { secret: 'secret', webhook: { id: 'first' } } } })
    const state = {
      apollo: { mutate },
      provision: cy.stub(),
      replaceDialog: true,
      selected: { id: 'first' },
      url: 'ftp://example.com/hook',
      $pgettext: (context, value) => value
    }
    state.change = WebhookList.methods.change.bind(state)
    state.validUrl = WebhookList.methods.validUrl.bind(state)

    await WebhookList.methods.replace.call(state)
    expect(mutate).not.to.have.been.called

    state.url = ' https://example.com/new '
    await WebhookList.methods.replace.call(state)

    expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: 'first', url: 'https://example.com/new' })
    expect(state.replaceDialog).to.equal(false)
    expect(state.provision).to.have.been.calledOnce
  })

  it('accepts only https endpoint URLs', () => {
    const validUrl = WebhookList.methods.validUrl

    expect(validUrl('https://example.com/hook')).to.equal(true)
    expect(validUrl(' https://example.com/hook?x=1 ')).to.equal(true)
    expect(validUrl('http://example.com/hook')).to.equal(false)
    expect(validUrl('HTTPS://example.com')).to.equal(true)
    expect(validUrl('')).to.equal(false)
    expect(validUrl(null)).to.equal(false)
    expect(validUrl('example.com/hook')).to.equal(false)
    expect(validUrl('ftp://example.com/hook')).to.equal(false)
    expect(validUrl('https:example.com')).to.equal(false)
    expect(validUrl('https://')).to.equal(false)
    expect(validUrl('https://exa mple.com')).to.equal(false)
    expect(validUrl('https://example.com/a b')).to.equal(false)
  })

  it('adds an active webhook and shows its secret in the add dialog', () => {
    // the add dialog has more fields than fit into a lower viewport
    cy.viewport(1280, 900)

    const webhook = {
      id: 'new',
      name: 'Orders',
      endpoint: 'https://example.com/…',
      events: ['page.published'],
      status: true,
      last_error: null,
      last_success_at: null
    }
    const mutate = cy.stub().resolves({ data: { addWebhook: { secret: 'one-time-secret', webhook } } })
    const query = cy.stub().resolves({ data: { cmsWebhooks: [], cmsWebhookEvents: ['page.published'] } })

    cy.mount(pluginUi(WebhookList), {
      global: {
        provide: {
          apollo: { mutate, query },
          messages: { add: cy.stub() }
        }
      }
    })

    cy.get('.btn-add').first().click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add webhook').should('exist')
    cy.get('.v-dialog:visible .dialog-body').children().first().should('have.class', 'webhook-status')
    cy.get('.v-dialog:visible .webhook-status input').should('not.be.checked')

    cy.get('.v-dialog:visible .v-select').click()
    cy.get('.v-overlay-container .v-list-item').contains('page.published').click()
    cy.get('body').type('{esc}')

    cy.get('.v-dialog:visible .v-text-field input').first().type('ftp://example.com/hook').blur()
    cy.contains('.v-dialog:visible .v-messages', 'Not a valid URL').should('exist')
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Save').should('be.disabled')

    cy.get('.v-dialog:visible .v-text-field input').first().clear().type('https://example.com/hook')
    cy.contains('.v-dialog:visible .v-messages', 'Not a valid URL').should('not.exist')
    cy.get('.v-dialog:visible .webhook-name input').type('Orders')
    cy.get('.v-dialog:visible .webhook-status input').check()
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Save').should('not.be.disabled').click()

    cy.wrap(mutate).should('have.been.calledOnce').then(() => {
      expect(mutate.firstCall.args[0].variables.input).to.deep.equal({
        url: 'https://example.com/hook',
        name: 'Orders',
        events: ['page.published'],
        status: true
      })
    })
    cy.get('.v-dialog:visible').should('have.length', 1)
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add webhook').should('exist')
    cy.get('.v-dialog:visible .webhook-secret input').should('have.value', 'one-time-secret')
    cy.get('.v-dialog:visible .webhook-status').should('not.exist')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.contains('.v-btn', 'Done').should('have.class', 'v-btn--variant-outlined')
      cy.contains('.v-btn', 'Copy secret').should('have.class', 'v-btn--variant-tonal')
    })
    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.get('[role="listitem"] .item-title').should('have.text', 'Orders')
    cy.get('[role="listitem"] .item-endpoint').should('contain', 'https://example.com/…')

    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Done').click()
    cy.get('.v-dialog:visible').should('not.exist')

    cy.get('.btn-add').first().click()
    cy.get('.v-dialog:visible .webhook-secret').should('not.exist')
    cy.get('.v-dialog:visible .webhook-status').should('exist')
    cy.get('.v-dialog:visible .webhook-name input').should('have.value', '')
  })

  it('shows a new secret', () => {
    const webhook = { id: 'first' }
    const state = { put: cy.stub(), secret: '', secretDialog: false }

    WebhookList.methods.provision.call(state, { secret: 'secret', webhook })

    expect(state.put).to.have.been.calledWith(webhook)
    expect(state.secret).to.equal('secret')
    expect(state.secretDialog).to.equal(true)
  })

  it('asks before rotating', async () => {
    const confirm = cy.stub(window, 'confirm').returns(false)
    const item = { id: 'first', name: 'Shop', endpoint: 'https://example.com/…' }
    const state = {
      $pgettext: (context, value) => value,
      apollo: { mutate: cy.stub() },
      change: cy.stub().resolves(),
      label: WebhookList.methods.label,
      saving: false
    }

    // the webhook is named because endpoints on the same host look the same
    await WebhookList.methods.rotate.call(state, item)
    expect(confirm.lastCall.args[0]).to.equal('Rotate the secret of this webhook? Receivers must be updated with the new secret.\n\nShop · https://example.com/…')

    await WebhookList.methods.rotate.call(state, { ...item, name: '' })
    expect(confirm.lastCall.args[0]).to.equal('Rotate the secret of this webhook? Receivers must be updated with the new secret.\n\nhttps://example.com/…')
    expect(state.change).not.to.have.been.called

    confirm.returns(true)
    await WebhookList.methods.rotate.call(state, item)
    expect(state.change).to.have.been.calledOnce
  })

  it('clears the one-time secret when its dialog closes', () => {
    const state = { secret: 'secret', secretDialog: true }

    WebhookList.methods.closeSecret.call(state)

    expect(state.secret).to.equal('')
    expect(state.secretDialog).to.equal(false)
  })

  it('restores all results when the search field is cleared', () => {
    const items = [{
      id: 'first',
      name: '',
      endpoint: 'https://example.com/first',
      events: ['page.published'],
      status: true
    }]

    const filtered = WebhookList.computed.filtered.call({
      items,
      statusFilter: null,
      term: null
    })

    expect(filtered).to.deep.equal(items)
  })

  it('finds webhooks by their name', () => {
    const items = [
      { id: 'first', name: 'Shop', endpoint: 'https://example.com/…', events: ['page.published'], status: true },
      { id: 'second', name: '', endpoint: 'https://example.com/…', events: ['page.published'], status: true }
    ]

    const filtered = WebhookList.computed.filtered.call({
      items,
      statusFilter: null,
      term: ' SHOP '
    })

    expect(filtered).to.deep.equal([items[0]])
  })

  it('removes an updated webhook from the bulk selection', () => {
    const state = {
      checked: new Set(['first', 'second']),
      items: [{ id: 'first' }, { id: 'second' }]
    }

    WebhookList.methods.put.call(state, { id: 'first' })

    expect([...state.checked]).to.deep.equal(['second'])
  })

  it('selects and deletes several webhooks in one mutation', async () => {
    const mutate = cy.stub().resolves({ data: { dropWebhook: 2 } })
    const state = {
      apollo: { mutate },
      checked: new Set(),
      items: [{ id: 'first' }, { id: 'second' }],
      filtered: [{ id: 'first' }, { id: 'second' }],
      saving: false,
      $pgettext: (context, value) => value,
      messages: { add: cy.stub() }
    }
    state.change = WebhookList.methods.change.bind(state)

    WebhookList.methods.toggle.call(state)
    expect([...state.checked]).to.deep.equal(['first', 'second'])

    cy.stub(window, 'confirm').returns(true)
    await WebhookList.methods.remove.call(state)

    expect(mutate).to.have.been.calledOnce
    expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['first', 'second'] })
    expect(state.items).to.deep.equal([])
    expect(state.checked.size).to.equal(0)
  })

  it('deletes more webhooks than allowed in one mutation in batches', async () => {
    const items = Array.from({ length: 150 }, (value, index) => ({ id: `id${index}` }))
    const mutate = cy.stub()
    mutate.onFirstCall().resolves({ data: { dropWebhook: 100 } })
    mutate.onSecondCall().rejects(new Error('failed'))
    const state = {
      apollo: { mutate },
      checked: new Set(items.map((item) => item.id)),
      items,
      saving: false,
      $pgettext: (context, value) => value,
      messages: { add: cy.stub() }
    }
    state.change = WebhookList.methods.change.bind(state)

    cy.stub(window, 'confirm').returns(true)
    await WebhookList.methods.remove.call(state)

    expect(mutate).to.have.been.calledTwice
    expect(mutate.firstCall.args[0].variables.id).to.have.length(100)
    expect(mutate.secondCall.args[0].variables.id).to.deep.equal(items.slice(100).map((item) => item.id))
    // webhooks deleted before the failure are removed from the list and the selection
    expect(state.items).to.deep.equal(items.slice(100))
    expect([...state.checked]).to.deep.equal(items.slice(100).map((item) => item.id))
    expect(state.messages.add).to.have.been.calledWith('Error deleting webhook:\nError: failed', 'error')
  })

  it('names the webhook when asking before deleting it', async () => {
    const confirm = cy.stub(window, 'confirm').returns(false)
    const state = {
      change: cy.stub().resolves(),
      label: WebhookList.methods.label,
      saving: false,
      $pgettext: (context, value) => value
    }

    await WebhookList.methods.remove.call(state, { id: 'first', name: 'Shop', endpoint: 'https://example.com/…' })

    expect(confirm.lastCall.args[0]).to.equal('Delete this webhook?\n\nShop · https://example.com/…')
    expect(state.change).not.to.have.been.called
  })

  it('keeps the position of changed webhooks and shows new ones first', () => {
    const items = [{ id: 'first', name: '' }, { id: 'second', name: '' }]
    const state = { checked: new Set(['second']), items }

    WebhookList.methods.put.call(state, { id: 'second', name: 'Shop' })
    expect(state.items).to.deep.equal([{ id: 'first', name: '' }, { id: 'second', name: 'Shop' }])
    expect(state.checked.size).to.equal(0)

    WebhookList.methods.put.call(state, { id: 'new', name: '' })
    expect(state.items.map((item) => item.id)).to.deep.equal(['new', 'first', 'second'])
  })

  it('uses the CMS list surface and filters webhooks', () => {
    // the edit dialog shows the current destination and warnings, which don't fit into a lower viewport
    cy.viewport(1280, 900)

    const component = pluginUi(WebhookList)
    const rotated = {
      id: 'first',
      name: 'Shop',
      endpoint: 'https://example.com/orders',
      events: ['page.published'],
      status: true,
      last_error: null,
      last_success_at: '2026-09-15T12:00:00.000000Z'
    }
    const mutate = cy.stub().callsFake(({ mutation }) => Promise.resolve({
      data: mutation.definitions[0].name.value === 'PingWebhook'
        ? { pingWebhook: { success: true, status: 204, reason: null } }
        : { rotateWebhook: { secret: 'secret', webhook: rotated } }
    }))
    const messages = { add: cy.stub() }
    const query = cy.stub().resolves({
      data: {
        cmsWebhooks: [
          {
            id: 'first',
            name: 'Shop',
            endpoint: 'https://example.com/orders',
            events: ['page.published'],
            status: true,
            last_error: null,
            last_success_at: '2026-09-15T12:00:00.000000Z'
          },
          {
            id: 'second',
            name: '',
            endpoint: 'https://example.com/archive',
            events: ['page.dropped'],
            status: false,
            last_error: { reason: 'invalid_encryption' },
            last_success_at: null
          }
        ],
        cmsWebhookEvents: ['page.published', 'page.dropped']
      }
    })

    cy.mount(component, {
      global: {
        provide: {
          apollo: { mutate, query },
          messages
        }
      }
    })

    cy.get('.v-sheet.box.scroll').should('exist')
    cy.get('.webhook-server').should('not.exist')
    cy.get('.webhook-paused').should('not.exist')
    cy.get('.header .search .v-text-field').should('exist')
    cy.get('.header .search .v-select').should('exist')
    cy.get('.btn-add').should('exist')
    cy.get('.btn-reload').should('exist')
    cy.get('.v-list.items > .v-list-item').should('have.length', 2)
    cy.get('.v-list.items > .v-list-item > .v-list-item__content').should('have.length', 2)
    // the name is shown instead of the endpoint, which follows it
    cy.get('[role="listitem"] .item-title').first().should('have.text', 'Shop')
    cy.get('[role="listitem"] .item-endpoint').first().should('contain', 'https://example.com/orders')
    cy.get('[role="listitem"] .item-title').last().should('have.text', 'https://example.com/archive')
    cy.get('[role="listitem"]').last().find('.item-endpoint').should('not.exist')
    cy.get('[role="listitem"] .item-content').first().then(($content) => {
      const content = $content[0].getBoundingClientRect()
      const head = $content.find('.item-head')[0].getBoundingClientRect()
      const aux = $content.find('.item-aux')[0].getBoundingClientRect()

      expect(aux.left).to.be.at.least(head.right)
      expect(aux.right).to.be.closeTo(content.right, 1)
    })

    cy.get('.header .search .v-select').click()
    cy.get('.v-overlay-container .v-list-item').contains('Active').click()
    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.contains('https://example.com/orders').should('exist')

    cy.get('.header .search .v-select').click()
    cy.get('.v-overlay-container .v-list-item').contains('All').click()

    cy.contains('[role="listitem"] .item-content', 'https://example.com/orders').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Edit webhook').should('exist')
    cy.get('.v-dialog:visible .dialog-body')
      .should('have.css', 'padding-top', '24px')
      .and('have.css', 'padding-right', '16px')
    cy.get('.v-dialog:visible .dialog-body').children().first().should('have.class', 'webhook-status')
    cy.get('.v-dialog:visible .webhook-status').then(($status) => {
      const control = $status.find('.v-switch')[0].getBoundingClientRect()
      const label = $status.find('.webhook-status-label')[0].getBoundingClientRect()

      expect(label.top).to.be.lessThan(control.bottom)
      expect(label.bottom).to.be.greaterThan(control.top)
    })
    cy.get('.v-dialog:visible .webhook-status input').should('not.be.disabled')
    cy.get('.v-dialog:visible .webhook-current').should('contain', 'Current destination: https://example.com/orders')
    cy.get('.v-dialog:visible .webhook-undecryptable').should('not.exist')
    cy.get('.v-dialog:visible .webhook-name input').should('have.value', 'Shop')
    cy.get('.v-dialog:visible .dialog-actions')
      .should('have.css', 'padding-top', '16px')
      .and('have.css', 'padding-right', '16px')
      .within(() => {
        cy.contains('.v-btn', 'Cancel').should('have.class', 'v-btn--variant-outlined')
        cy.contains('.v-btn', 'Save')
          .should('have.class', 'v-btn--variant-tonal')
          .and('have.class', 'text-primary')
          .and('have.class', 'v-btn--active')
      })
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Cancel').click()

    cy.get('[role="listitem"] button[aria-haspopup]').first().click()
    cy.contains('.v-overlay--active .v-toolbar--density-compact', 'Actions').should('exist')
    cy.get('.v-overlay--active').contains('.v-btn', 'Replace').should('exist')
    cy.get('.v-overlay--active').contains('.v-btn', 'Rotate').should('exist')
    cy.get('.v-overlay--active .btn-ping').should('contain', 'Send test event')
    cy.get('.v-overlay--active').contains('.v-btn', 'Replace').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Replace webhook destination').should('exist')
    cy.get('.v-dialog:visible')
      .should('have.attr', 'aria-label', 'Replace webhook destination')
    cy.get('.v-dialog:visible button[aria-label="Close"]').should('exist')
    cy.get('.v-dialog:visible .webhook-current')
      .should('contain', 'Current destination: Shop · https://example.com/orders')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.contains('.v-btn', 'Cancel').should('have.class', 'v-btn--variant-outlined')
      cy.contains('.v-btn', 'Replace')
        .should('have.class', 'v-btn--variant-tonal')
        .and('have.class', 'text-primary')
        .and('have.class', 'v-btn--active')
        .and('be.disabled')
    })
    cy.get('.v-dialog:visible .v-text-field input').type('ftp://example.com/hook').blur()
    cy.contains('.v-dialog:visible .v-messages', 'Not a valid URL').should('exist')
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Replace').should('be.disabled')
    cy.get('.v-dialog:visible .v-text-field input').clear().type('https://example.com/new')
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Replace').should('not.be.disabled')
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Cancel').click()

    // a new secret can't be stored if the URL can't be decrypted
    cy.get('[role="listitem"] button[aria-haspopup]').last().click()
    cy.get('.v-overlay--active .btn-rotate').should('be.disabled')
    cy.get('.v-overlay--active .btn-ping').should('not.be.disabled')
    cy.get('.v-overlay--active button[aria-label="Close"]').click()
    cy.get('.v-overlay--active').should('not.exist')

    // deliveries would fail until the URL is replaced
    cy.contains('[role="listitem"] .item-content', 'https://example.com/archive').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Edit webhook').should('exist')
    cy.get('.v-dialog:visible .webhook-status input').should('be.disabled')
    cy.get('.v-dialog:visible .webhook-undecryptable').should('contain', 'Can\'t be decrypted, replace the URL')
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Cancel').click()
    cy.get('.v-dialog:visible').should('not.exist')

    cy.get('.btn-add').first().click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add webhook').should('exist')
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()

    cy.stub(window, 'confirm').returns(true)
    cy.get('[role="listitem"] button[aria-haspopup]').first().click()
    cy.get('.v-overlay--active .btn-rotate').should('not.be.disabled').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Webhook secret').should('exist')
    cy.get('.v-dialog:visible')
      .should('have.attr', 'aria-label', 'Webhook secret')
    cy.get('.v-dialog:visible .v-toolbar.v-toolbar--density-compact').should('exist')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.get('.v-spacer').should('exist')
      cy.contains('.v-btn', 'Done').should('have.class', 'v-btn--variant-outlined')
      cy.contains('.v-btn', 'Copy secret')
        .should('have.class', 'v-btn--variant-tonal')
        .and('have.class', 'text-primary')
        .and('have.class', 'v-btn--active')
    })
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()
    cy.wrap(mutate).should('have.been.calledOnce')

    cy.get('[role="listitem"] button[aria-haspopup]').first().click()
    cy.get('.v-overlay--active .btn-ping').click()
    cy.wrap(mutate).should('have.been.calledTwice').then(() => {
      expect(mutate.secondCall.args[0].variables).to.deep.equal({ id: 'first' })
    })
    cy.wrap(messages.add).should('have.been.calledWith', 'Test event delivered (204)', 'success')

    cy.get('.search input').first().type('orders')
    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.contains('https://example.com/orders').should('exist')
    cy.contains('https://example.com/archive').should('not.exist')

    cy.viewport(320, 720)
    cy.get('[role="listitem"] .item-content').should('have.css', 'flex-wrap', 'wrap')
  })

  it('mounts the production bundle with host UI components', () => {
    const component = pluginUi(BuiltWebhookList)
    const query = cy.stub().resolves({
      data: { cmsWebhooks: [], cmsWebhookEvents: ['page.published'] }
    })

    cy.mount(component, {
      global: {
        provide: {
          apollo: { query },
          messages: { add: cy.stub() }
        }
      }
    })

    cy.contains('No webhooks configured.').should('exist')
    cy.wrap(query).should('have.been.calledOnce')
  })
})
