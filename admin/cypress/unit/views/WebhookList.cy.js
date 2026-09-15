import WebhookList from '../../../../webhooks/admin/src/views/WebhookList.vue'
import BuiltWebhookList from '../../../../webhooks/admin/dist/WebhookList.js'
import { pluginUi } from '../../../js/plugin'

describe('WebhookList', () => {
  const context = {
    $vuetify: { locale: { current: 'en' } },
    $gettext(value) {
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

  it('selects and deletes several webhooks in one mutation', async () => {
    const mutate = cy.stub().resolves({ data: { dropWebhook: 2 } })
    const state = {
      apollo: { mutate },
      checked: new Set(),
      items: [{ id: 'first' }, { id: 'second' }],
      saving: false,
      $gettext: (value) => value,
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

  it('mounts the production bundle with host UI components', () => {
    const component = pluginUi(BuiltWebhookList)
    const query = cy.stub().resolves({
      data: { cmsWebhooks: [], cmsWebhookEvents: ['page.published'] }
    })

    expect(component.components).to.include.keys('VAlert', 'VBtn', 'VDialog', 'VTable')

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
