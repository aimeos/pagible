import WebhookList from '../../../../webhooks/admin/src/views/WebhookList.vue'

describe('WebhookList', () => {
  const context = {
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
})
