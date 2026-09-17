import WebhookList from '../../../../webhooks/admin/src/views/WebhookList.vue'
import BuiltWebhookList from '../../../../webhooks/admin/dist/WebhookList.js'
import { pluginUi } from '../../../js/plugin'
import '../../../js/assets/base.css'

describe('WebhookList', () => {
  const context = {
    $vuetify: { locale: { current: 'en' } },
    $pgettext(context, value) {
      return {
        'Access denied': 'Zugriff verweigert',
        'Delivery failed': 'Zustellung fehlgeschlagen',
        'Not a valid URL': 'Keine gültige URL',
        'Value has invalid format': 'Wert hat ein ungültiges Format'
      }[value] || value
    }
  }

  it('translates stored delivery failure reasons', () => {
    const errorText = WebhookList.methods.errorText.bind(context)

    expect(errorText({ last_error: { reason: 'destination_not_allowed' } })).to.equal('Zugriff verweigert')
    expect(errorText({ last_error: { reason: 'response_body_too_large' } })).to.equal('Zustellung fehlgeschlagen')
    expect(errorText({ last_error: { reason: 'invalid_url' } })).to.equal('Keine gültige URL')
    expect(errorText({ last_error: { reason: 'unexpected_reason', status: 503 } })).to.equal('Zustellung fehlgeschlagen (503)')
  })

  it('shows whether a delivery has succeeded', () => {
    const successText = WebhookList.methods.successText.bind(context)

    expect(successText({ last_success_at: null })).to.equal('None')
    expect(successText({ last_success_at: '2026-09-15T12:00:00Z' })).not.to.equal('None')
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
      saving: false,
      secret: '',
      secretDialog: false,
      selected: null,
      url: 'https://example.com/hook',
      $pgettext: (context, value) => value
    }
    state.change = WebhookList.methods.change.bind(state)
    state.provision = WebhookList.methods.provision.bind(state)
    state.put = WebhookList.methods.put.bind(state)

    await WebhookList.methods.save.call(state)

    expect(state.items).to.deep.equal([webhook])
    expect(state.items).not.to.equal(webhooks)
    expect(state.secret).to.equal('secret')
    expect(state.secretDialog).to.equal(true)
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

  it('uses the CMS list surface and filters webhooks', () => {
    cy.viewport(1280, 720)

    const component = pluginUi(WebhookList)
    const rotated = {
      id: 'first',
      endpoint: 'https://example.com/orders',
      events: ['page.published'],
      status: false,
      failures: 0,
      last_error: null,
      last_success_at: '2026-09-15T12:00:00Z'
    }
    const mutate = cy.stub().resolves({
      data: { rotateWebhook: { secret: 'secret', webhook: rotated } }
    })
    const query = cy.stub().resolves({
      data: {
        cmsWebhooks: [
          {
            id: 'first',
            endpoint: 'https://example.com/orders',
            events: ['page.published'],
            status: true,
            failures: 0,
            last_error: null,
            last_success_at: '2026-09-15T12:00:00Z'
          },
          {
            id: 'second',
            endpoint: 'https://example.com/archive',
            events: ['page.dropped'],
            status: false,
            failures: 2,
            last_error: { reason: 'invalid_url' },
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
          messages: { add: cy.stub() }
        }
      }
    })

    cy.get('.v-sheet.box.scroll').should('exist')
    cy.get('.header .search .v-text-field').should('exist')
    cy.get('.header .search .v-select').should('exist')
    cy.get('.btn-add').should('exist')
    cy.get('.btn-reload').should('exist')
    cy.get('.v-list.items > .v-list-item').should('have.length', 2)
    cy.get('.v-list.items > .v-list-item > .v-list-item__content').should('have.length', 2)
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
    cy.get('.v-dialog:visible .v-card-text').children().first().should('have.class', 'webhook-status')
    cy.get('.v-dialog:visible .webhook-status').then(($status) => {
      const control = $status.find('.v-switch')[0].getBoundingClientRect()
      const label = $status.find('.webhook-status-label')[0].getBoundingClientRect()

      expect(label.top).to.be.lessThan(control.bottom)
      expect(label.bottom).to.be.greaterThan(control.top)
    })
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.contains('.v-btn', 'Cancel').should('have.class', 'v-btn--variant-text')
      cy.contains('.v-btn', 'Save')
        .should('have.class', 'v-btn--variant-flat')
        .and('have.class', 'bg-primary')
    })
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Cancel').click()

    cy.get('[role="listitem"] button[title="Actions"]').first().click()
    cy.contains('.v-overlay--active .v-toolbar--density-compact', 'Actions').should('exist')
    cy.get('.v-overlay--active').contains('.v-btn', 'Replace').should('exist')
    cy.get('.v-overlay--active').contains('.v-btn', 'Rotate').should('exist')
    cy.get('.v-overlay--active').contains('.v-btn', 'Replace').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Replace webhook destination').should('exist')
    cy.get('.v-dialog:visible')
      .should('have.attr', 'aria-label', 'Replace webhook destination')
    cy.get('.v-dialog:visible button[aria-label="Close"]').should('exist')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.contains('.v-btn', 'Cancel').should('have.class', 'v-btn--variant-text')
      cy.contains('.v-btn', 'Replace')
        .should('have.class', 'v-btn--variant-flat')
        .and('have.class', 'bg-primary')
        .and('be.disabled')
    })
    cy.get('.v-dialog:visible .v-card-actions').contains('.v-btn', 'Cancel').click()

    cy.get('.btn-add').first().click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add webhook').should('exist')
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()

    cy.get('[role="listitem"] button[title="Actions"]').first().click()
    cy.get('.v-overlay--active').contains('.v-btn', 'Rotate').click()
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Webhook secret').should('exist')
    cy.get('.v-dialog:visible')
      .should('have.attr', 'aria-label', 'Webhook secret')
    cy.get('.v-dialog:visible .v-toolbar.v-toolbar--density-compact').should('exist')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.get('.v-spacer').should('exist')
      cy.contains('.v-btn', 'Done').should('have.class', 'v-btn--variant-text')
      cy.contains('.v-btn', 'Copy secret')
        .should('have.class', 'v-btn--variant-flat')
        .and('have.class', 'bg-primary')
    })
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()
    cy.wrap(mutate).should('have.been.calledOnce')

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
