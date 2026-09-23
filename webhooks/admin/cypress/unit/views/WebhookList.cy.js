import { reactive } from 'vue'
import WebhookList from '../../../src/views/WebhookList.vue'
import BuiltWebhookList from '../../../dist/WebhookList.js'
import { pluginUi } from '@/plugin'
import '@/assets/base.css'

// component state like "this" in the view, methods are bound unless a test replaces them
function vm(data = {}) {
  const state = { $pgettext: (context, value) => value, messages: { add: cy.stub() }, saving: false, ...data }

  for (const name in WebhookList.methods) {
    state[name] ??= WebhookList.methods[name].bind(state)
  }

  return state
}

// returns the messages stub to check the notifications of the view
function mount(component, apollo, pluginAside = null) {
  const messages = { add: cy.stub() }

  cy.mount(pluginUi(component), { global: { provide: { apollo, messages, pluginAside } } })

  return messages
}

// webhook as returned by the server
function fixture(data = {}) {
  return {
    id: 'first',
    name: '',
    endpoint: 'https://example.com/',
    events: ['page.published'],
    status: true,
    last_error: null,
    last_success_at: null,
    ...data
  }
}

describe('WebhookList', () => {
  // translates into German to check the texts of the view are passed through $pgettext
  const german = {
    $vuetify: { locale: { current: 'en' } },
    $pgettext(context, value, params = {}) {
      const text = {
        'Access denied': 'Zugriff verweigert',
        'Blocked by server configuration': 'Durch Serverkonfiguration blockiert',
        'Connection failed': 'Verbindung fehlgeschlagen',
        'Delivery failed': 'Zustellung fehlgeschlagen',
        'Host not found': 'Host nicht gefunden',
        'Not a valid URL': 'Keine gültige URL',
        'Queue unavailable': 'Warteschlange nicht verfügbar',
        'Redirects aren\'t followed': 'Weiterleitungen werden nicht befolgt',
        'Request timed out': 'Zeitüberschreitung der Anfrage',
        'Response too large': 'Antwort zu groß',
        'Secret can\'t be decrypted, rotate it': 'Geheimnis nicht entschlüsselbar, erneuern',
        'Secure connection failed': 'Sichere Verbindung fehlgeschlagen',
        'Test event delivered': 'Testereignis zugestellt',
        'Test event failed': 'Testereignis fehlgeschlagen'
      }[value] || value

      return text.replace(/%\{(\w+)\}/g, (match, name) => params[name] ?? match)
    }
  }

  it('translates stored delivery failure reasons', () => {
    const { errorText } = vm(german)

    expect(errorText({ last_error: { reason: 'destination_not_allowed' } })).to.equal('Zugriff verweigert')
    expect(errorText({ last_error: { reason: 'response_headers_too_large' } })).to.equal('Antwort zu groß')
    expect(errorText({ last_error: { reason: 'timeout' } })).to.equal('Zeitüberschreitung der Anfrage')
    expect(errorText({ last_error: { reason: 'connection_failed' } })).to.equal('Verbindung fehlgeschlagen')
    expect(errorText({ last_error: { reason: 'resolution_failed' } })).to.equal('Host nicht gefunden')
    expect(errorText({ last_error: { reason: 'http_error', status: 301 } })).to.equal('Weiterleitungen werden nicht befolgt (301)')
    expect(errorText({ last_error: { reason: 'http_error', status: 410 } })).to.equal('Zustellung fehlgeschlagen (410)')
    expect(errorText({ last_error: { reason: 'invalid_url' } })).to.equal('Keine gültige URL')
    expect(errorText({ last_error: { reason: 'invalid_policy' } })).to.equal('Durch Serverkonfiguration blockiert')
    expect(errorText({ last_error: { reason: 'queue_failed' } })).to.equal('Warteschlange nicht verfügbar')
    expect(errorText({ last_error: { reason: 'invalid_encryption' } })).to.equal('Geheimnis nicht entschlüsselbar, erneuern')
    expect(errorText({ last_error: { reason: 'unexpected_reason', status: 503 } })).to.equal('Zustellung fehlgeschlagen (503)')
  })

  it('shows when the last delivery failure happened', () => {
    const { errorText } = vm(german)
    const at = '2026-09-15T12:00:00.000000Z'

    expect(errorText({ last_error: null })).to.equal('None')
    expect(errorText({ last_error: { reason: 'http_error', status: 503, at } }))
      .to.equal(`Zustellung fehlgeschlagen (503) · ${new Date(at).toLocaleString('en')}`)
  })

  it('reports the result of a test event', async () => {
    const mutate = cy.stub()
    const paused = '2026-09-15T12:05:00.000000Z'
    const state = vm({
      ...german,
      apollo: { mutate },
      items: Object.freeze([
        Object.freeze({ id: 'first', paused_until: paused }),
        Object.freeze({ id: 'second', paused_until: paused })
      ])
    })

    mutate.resolves({ data: { pingWebhook: { success: false, status: null, reason: 'destination_not_allowed' } } })
    await state.ping({ id: 'first' })

    expect(state.messages.add).to.have.been.calledWith('Testereignis fehlgeschlagen: Zugriff verweigert', 'error')
    expect(state.items[0].paused_until).to.equal(paused)

    // a successful test event resumes the paused deliveries
    mutate.resolves({ data: { pingWebhook: { success: true, status: 204, reason: null } } })
    await state.ping({ id: 'first' })

    expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: 'first' })
    expect(state.messages.add).to.have.been.calledWith('Testereignis zugestellt (204)', 'success')
    expect(state.items).to.deep.equal([{ id: 'first', paused_until: null }, { id: 'second', paused_until: paused }])

    mutate.rejects(new Error('offline'))
    await state.ping({ id: 'first' })

    expect(state.messages.add).to.have.been.calledWith('Testereignis fehlgeschlagen:\nError: offline', 'error')
    expect(state.saving).to.equal(false)
  })

  it('warns if the server configuration stops all deliveries', () => {
    const serverText = (server) => WebhookList.computed.serverText.call(vm({ ...german, server }))

    expect(serverText({ enabled: true, blocked: null })).to.equal('')
    expect(serverText({ enabled: true, blocked: 'invalid_policy' }))
      .to.equal('All deliveries are blocked by the server configuration')
    expect(serverText({ enabled: false, blocked: 'invalid_queue' }))
      .to.equal('Webhooks are disabled by the server configuration, no events are sent')
  })

  it('shows the server status and paused destinations', () => {
    const paused = '2026-09-15T12:05:00.000000Z'
    const query = cy.stub().resolves({
      data: {
        cmsWebhooks: [fixture({
          endpoint: 'https://example.com/orders/',
          last_error: { reason: 'timeout', at: '2026-09-15T12:00:00.000000Z' },
          paused_until: paused
        })],
        cmsWebhookEvents: ['page.published'],
        cmsWebhookServer: { enabled: true, blocked: 'invalid_policy' }
      }
    })

    mount(WebhookList, { query })

    cy.get('.webhook-server').should('contain', 'All deliveries are blocked by the server configuration')
    cy.get('[role="listitem"] .webhook-paused')
      .should('contain', `Paused until: ${new Date(paused).toLocaleString('en')}`)
    cy.get('[role="listitem"]').should('contain', 'Request timed out')
  })

  it('shows whether a delivery has succeeded', () => {
    const { successText } = vm(german)

    expect(successText({ last_success_at: null })).to.equal('None')
    expect(successText({ last_success_at: '2026-09-15T12:00:00.000000Z' })).not.to.equal('None')
  })

  it('updates immutable query result snapshots without mutating them', async () => {
    const original = Object.freeze({ id: 'first', status: false })
    const updated = Object.freeze({ id: 'first', status: true })
    const webhooks = Object.freeze([original])
    const names = Object.freeze(['page.published'])
    const state = vm({
      apollo: { query: cy.stub().resolves({ data: { cmsWebhooks: webhooks, cmsWebhookEvents: names, cmsWebhookServer: { enabled: true, blocked: null } } }) },
      checked: new Set(['old']),
      items: [],
      loading: false,
      names: []
    })

    await state.load()

    expect(state.items).to.equal(webhooks)
    expect(state.names).to.equal(names)
    expect(state.server).to.deep.equal({ enabled: true, blocked: null })

    state.put(updated)

    expect(state.items).to.deep.equal([updated])
    expect(state.items).not.to.equal(webhooks)
    expect(state.checked.size).to.equal(0)
    expect(webhooks).to.deep.equal([original])
  })

  it('adds a webhook to an immutable query result snapshot', async () => {
    const webhooks = Object.freeze([])
    const webhook = Object.freeze({ id: 'new' })
    const mutate = cy.stub().resolves({ data: { addWebhook: { secret: 'secret', webhook } } })
    const state = vm({
      apollo: { mutate },
      checked: new Set(),
      dialog: true,
      events: ['page.published'],
      items: webhooks,
      name: ' Orders ',
      secret: '',
      selected: null,
      status: true,
      url: 'https://example.com/hook'
    })

    await state.save()

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
  })

  it('saves the name of an existing webhook', async () => {
    const webhook = Object.freeze({ id: 'first', name: 'Orders' })
    const mutate = cy.stub().resolves({ data: { saveWebhook: webhook } })
    const state = vm({
      apollo: { mutate },
      checked: new Set(),
      dialog: true,
      events: ['page.published'],
      items: [],
      name: ' Orders ',
      selected: { id: 'first' },
      status: false
    })

    await state.save()

    expect(mutate.firstCall.args[0].variables).to.deep.equal({
      id: 'first',
      input: { name: 'Orders', events: ['page.published'], status: false }
    })
    expect(state.items).to.deep.equal([webhook])
    expect(state.dialog).to.equal(false)
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
    // component tests don't center dialogs, so their top edge is at half the viewport height
    cy.viewport(1280, 1200)

    const webhook = fixture({ id: 'new', name: 'Orders' })
    const mutate = cy.stub().resolves({ data: { addWebhook: { secret: 'one-time-secret', webhook } } })
    const query = cy.stub().resolves({
      data: { cmsWebhooks: [], cmsWebhookEvents: ['page.published'], cmsWebhookServer: { enabled: true, blocked: null } }
    })

    mount(WebhookList, { mutate, query })

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
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Webhook secret').should('exist')
    cy.get('.v-dialog:visible .webhook-secret input').should('have.value', 'one-time-secret')
    cy.get('.v-dialog:visible .webhook-status').should('not.exist')
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Copy secret').should('exist')
    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.get('[role="listitem"] .item-title').should('have.text', 'Orders')
    cy.get('[role="listitem"] .item-endpoint').should('contain', 'https://example.com/')

    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Done').click()
    cy.get('.v-dialog:visible').should('not.exist')

    cy.get('.btn-add').first().click()
    cy.get('.v-dialog:visible .webhook-secret').should('not.exist')
    cy.get('.v-dialog:visible .webhook-status').should('exist')
    cy.get('.v-dialog:visible .webhook-name input').should('have.value', '')
  })

  it('asks before rotating', async () => {
    const confirm = cy.stub(window, 'confirm').returns(false)
    const item = { id: 'first', name: 'Shop', endpoint: 'https://example.com/' }
    const state = vm({ apollo: { mutate: cy.stub() }, change: cy.stub().resolves() })

    // the webhook is named because endpoints without the last path segment look the same
    await state.rotate(item)
    expect(confirm.lastCall.args[0]).to.equal('Rotate the secret of this webhook? Receivers must be updated with the new secret.\n\nShop · https://example.com/')

    await state.rotate({ ...item, name: '' })
    expect(confirm.lastCall.args[0]).to.equal('Rotate the secret of this webhook? Receivers must be updated with the new secret.\n\nhttps://example.com/')
    expect(state.change).not.to.have.been.called

    confirm.returns(true)
    await state.rotate(item)
    expect(state.change).to.have.been.calledOnce
  })

  it('restores all results when the search field is cleared', () => {
    const items = [fixture({ endpoint: 'https://example.com/first' })]

    const filtered = WebhookList.computed.filtered.call({
      items,
      filter: { status: null },
      term: null
    })

    expect(filtered).to.deep.equal(items)
  })

  it('filters webhooks by status', () => {
    const items = [fixture(), fixture({ id: 'second', status: false })]
    const filtered = (status) => WebhookList.computed.filtered.call({ items, filter: { status }, term: '' })

    expect(filtered(null)).to.deep.equal(items)
    expect(filtered(true)).to.deep.equal([items[0]])
    expect(filtered(false)).to.deep.equal([items[1]])
  })

  it('finds webhooks by their name', () => {
    const items = [fixture({ name: 'Shop' }), fixture({ id: 'second' })]

    const filtered = WebhookList.computed.filtered.call({
      items,
      filter: { status: null },
      term: ' SHOP '
    })

    expect(filtered).to.deep.equal([items[0]])
  })

  it('removes an updated webhook from the bulk selection', () => {
    const state = vm({
      checked: new Set(['first', 'second']),
      items: [{ id: 'first' }, { id: 'second' }]
    })

    state.put({ id: 'first' })

    expect([...state.checked]).to.deep.equal(['second'])
  })

  it('selects and purges several webhooks in one mutation', async () => {
    const mutate = cy.stub().resolves({ data: { purgeWebhook: 2 } })
    const state = vm({
      apollo: { mutate },
      checked: new Set(),
      items: [{ id: 'first' }, { id: 'second' }],
      filtered: [{ id: 'first' }, { id: 'second' }]
    })

    state.toggle()
    expect([...state.checked]).to.deep.equal(['first', 'second'])

    cy.stub(window, 'confirm').returns(true)
    await state.purge()

    expect(mutate).to.have.been.calledOnce
    expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['first', 'second'] })
    expect(state.items).to.deep.equal([])
    expect(state.checked.size).to.equal(0)
  })

  it('purges more webhooks than allowed in one mutation in batches', async () => {
    const items = Array.from({ length: 150 }, (value, index) => ({ id: `id${index}` }))
    const mutate = cy.stub()
    mutate.onFirstCall().resolves({ data: { purgeWebhook: 100 } })
    mutate.onSecondCall().rejects(new Error('failed'))
    const state = vm({
      apollo: { mutate },
      checked: new Set(items.map((item) => item.id)),
      items
    })

    cy.stub(window, 'confirm').returns(true)
    await state.purge()

    expect(mutate).to.have.been.calledTwice
    expect(mutate.firstCall.args[0].variables.id).to.have.length(100)
    expect(mutate.secondCall.args[0].variables.id).to.deep.equal(items.slice(100).map((item) => item.id))
    // webhooks purged before the failure are removed from the list and the selection
    expect(state.items).to.deep.equal(items.slice(100))
    expect([...state.checked]).to.deep.equal(items.slice(100).map((item) => item.id))
    expect(state.messages.add).to.have.been.calledWith('Error purging webhook:\nError: failed', 'error')
  })

  it('names the webhook when asking before purging it', async () => {
    const confirm = cy.stub(window, 'confirm').returns(false)
    const state = vm({ change: cy.stub().resolves() })

    await state.purge({ id: 'first', name: 'Shop', endpoint: 'https://example.com/' })

    expect(confirm.lastCall.args[0]).to.equal('Purge this webhook?\n\nShop · https://example.com/')
    expect(state.change).not.to.have.been.called
  })

  it('keeps the position of changed webhooks and shows new ones first', () => {
    const items = [{ id: 'first', name: '' }, { id: 'second', name: '' }]
    const state = vm({ checked: new Set(['second']), items })

    state.put({ id: 'second', name: 'Shop' })
    expect(state.items).to.deep.equal([{ id: 'first', name: '' }, { id: 'second', name: 'Shop' }])
    expect(state.checked.size).to.equal(0)

    state.put({ id: 'new', name: '' })
    expect(state.items.map((item) => item.id)).to.deep.equal(['new', 'first', 'second'])
  })

  // mounts the view with a named webhook and one whose secret can't be decrypted
  function mountList(pluginAside = null) {
    // dialogs show the current destination and warnings, which don't fit into a lower viewport
    // component tests don't center dialogs, so their top edge is at half the viewport height
    cy.viewport(1280, 1200)

    const shop = fixture({
      name: 'Shop',
      endpoint: 'https://example.com/orders/',
      last_success_at: '2026-09-15T12:00:00.000000Z'
    })
    const mutate = cy.stub().callsFake(({ mutation }) => Promise.resolve({
      data: mutation.definitions[0].name.value === 'PingWebhook'
        ? { pingWebhook: { success: true, status: 204, reason: null } }
        : { rotateWebhook: { secret: 'secret', webhook: shop } }
    }))
    const query = cy.stub().resolves({
      data: {
        cmsWebhooks: [
          shop,
          fixture({
            id: 'second',
            endpoint: 'https://example.com/archive/',
            events: ['page.dropped'],
            status: false,
            last_error: { reason: 'invalid_encryption' }
          })
        ],
        cmsWebhookEvents: ['page.published', 'page.dropped'],
        cmsWebhookServer: { enabled: true, blocked: null }
      }
    })

    return { mutate, messages: mount(WebhookList, { mutate, query }, pluginAside) }
  }

  it('uses the CMS list surface and shows the name before the endpoint', () => {
    mountList()

    cy.get('.v-sheet.box.scroll').should('exist')
    cy.get('.header .search .v-text-field').should('exist')
    cy.get('.btn-add').should('exist')
    cy.get('.btn-reload').should('exist')
    cy.get('.webhook-server').should('not.exist')
    cy.get('.webhook-paused').should('not.exist')
    cy.get('.v-list.items > .v-list-item').should('have.length', 2)
    // the name is shown instead of the endpoint, which follows it
    cy.get('[role="listitem"] .item-title').first().should('have.text', 'Shop')
    cy.get('[role="listitem"] .item-endpoint').first().should('contain', 'https://example.com/orders/')
    cy.get('[role="listitem"] .item-title').last().should('have.text', 'https://example.com/archive/')
    cy.get('[role="listitem"]').last().find('.item-endpoint').should('not.exist')
  })

  it('filters by the status of the admin filter sidebar', () => {
    // filter as returned by the plugin panel of the admin
    const filter = reactive({ status: false })
    const pluginAside = cy.stub().returns(filter)
    mountList(pluginAside)

    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.contains('https://example.com/archive/').should('exist')
    cy.then(() => {
      const [content, defaults] = pluginAside.firstCall.args

      expect(defaults).to.deep.equal({ status: null })
      expect(content()[0].items.map((item) => item.value)).to.deep.equal([
        { status: null },
        { status: true },
        { status: false }
      ])
      filter.status = true
    })
    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.contains('https://example.com/orders/').should('exist')
  })

  it('filters webhooks by search term', () => {
    mountList()

    cy.get('.search input').first().type('orders')
    cy.get('.v-list.items > .v-list-item').should('have.length', 1)
    cy.contains('https://example.com/orders/').should('exist')
    cy.contains('https://example.com/archive/').should('not.exist')
  })

  it('shows the current destination when editing a webhook', () => {
    mountList()

    cy.contains('[role="listitem"] .item-content', 'https://example.com/orders/').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Edit webhook').should('exist')
    cy.get('.v-dialog:visible .webhook-status input').should('not.be.disabled')
    cy.get('.v-dialog:visible .webhook-current').should('have.text', 'https://example.com/orders/')
    cy.get('.v-dialog:visible .webhook-undecryptable').should('not.exist')
    cy.get('.v-dialog:visible .webhook-name input').should('have.value', 'Shop')

    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Cancel').click()
    cy.get('.v-dialog:visible').should('not.exist')
  })

  it('asks to rotate a secret which can\'t be decrypted', () => {
    mountList()

    // a new secret replaces the one which can't be decrypted
    cy.get('[role="listitem"] button[aria-haspopup]').last().click()
    cy.get('.v-overlay--active .btn-rotate').should('not.be.disabled')
    cy.get('.v-overlay--active button[aria-label="Close"]').click()
    cy.get('.v-overlay--active').should('not.exist')

    // deliveries would fail until the secret is rotated
    cy.contains('[role="listitem"] .item-content', 'https://example.com/archive/').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Edit webhook').should('exist')
    cy.get('.v-dialog:visible .webhook-undecryptable').should('contain', 'Secret can\'t be decrypted, rotate it')
  })

  it('shows the new secret after rotating it', () => {
    const { mutate } = mountList()

    cy.stub(window, 'confirm').returns(true)
    cy.get('[role="listitem"] button[aria-haspopup]').first().click()
    cy.get('.v-overlay--active .btn-rotate').should('not.be.disabled').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Webhook secret').should('exist')
    cy.get('.v-dialog:visible').should('have.attr', 'aria-label', 'Webhook secret')
    cy.get('.v-dialog:visible .webhook-secret input').should('have.value', 'secret')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.contains('.v-btn', 'Done').should('exist')
      cy.contains('.v-btn', 'Copy secret').should('exist')
    })
    cy.wrap(mutate).should('have.been.calledOnce')

    cy.get('.v-dialog:visible button[aria-label="Close"]').click()
    cy.get('.v-dialog:visible').should('not.exist')
  })

  it('sends a test event from the edit dialog', () => {
    const { mutate, messages } = mountList()

    // new webhooks have no saved destination to test
    cy.get('.btn-add').first().click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add webhook').should('exist')
    cy.get('.v-dialog:visible .v-card-actions .btn-test').should('not.exist')
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()
    cy.get('.v-dialog:visible').should('not.exist')

    cy.contains('[role="listitem"] .item-content', 'https://example.com/orders/').click()
    // the test button is at the start of the footer, before the spacer of the dialog
    cy.get('.v-dialog:visible .v-card-actions').then(([footer]) => {
      const shown = [...footer.children]
        .sort((a, b) => a.getBoundingClientRect().left - b.getBoundingClientRect().left)
        .map((item) => item.textContent.trim() || 'spacer')

      expect(shown).to.deep.equal(['Test', 'spacer', 'Cancel', 'Save'])
    })
    cy.get('.v-dialog:visible .v-card-actions .btn-test').should('contain', 'Test').click()
    cy.wrap(mutate).should('have.been.calledOnce').then(() => {
      expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: 'first' })
    })
    cy.wrap(messages.add).should('have.been.calledWith', 'Test event delivered (204)', 'success')
    cy.get('.v-dialog:visible').should('exist')
  })

  it('mounts the production bundle with host UI components', () => {
    const query = cy.stub().resolves({
      data: { cmsWebhooks: [], cmsWebhookEvents: ['page.published'], cmsWebhookServer: { enabled: true, blocked: null } }
    })

    mount(BuiltWebhookList, { query })

    cy.contains('No webhooks configured.').should('exist')
    cy.wrap(query).should('have.been.calledOnce')
  })
})
