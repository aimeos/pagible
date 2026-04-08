import Url from '../../js/fields/Url.vue'

describe('Url', () => {
  it('renders a text field with ltr class', () => {
    cy.mount(Url, { props: { config: {} } })
    cy.get('.v-text-field.ltr').should('exist')
  })

  it('displays the modelValue', () => {
    cy.mount(Url, { props: { modelValue: 'https://example.com', config: {} } })
    cy.get('input').should('have.value', 'https://example.com')
  })

  it('uses config.default when no modelValue is supplied', () => {
    cy.mount(Url, { props: { config: { default: 'https://default.com' } } })
    cy.get('input').should('have.value', 'https://default.com')
  })

  it('shows placeholder from config', () => {
    cy.mount(Url, { props: { config: { placeholder: 'https://…' } } })
    cy.get('input').should('have.attr', 'placeholder', 'https://…')
  })

  it('emits error:false for a valid URL', () => {
    const onError = cy.spy().as('error')
    cy.mount(Url, {
      props: { modelValue: 'https://example.com', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true for an invalid URL', () => {
    const onError = cy.spy().as('error')
    cy.mount(Url, {
      props: { modelValue: 'not a url!!', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false for an empty value when not required', () => {
    const onError = cy.spy().as('error')
    cy.mount(Url, {
      props: { modelValue: '', config: { required: false }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when required and value is empty', () => {
    const onError = cy.spy().as('error')
    cy.mount(Url, {
      props: { modelValue: '', config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('accepts relative paths as valid', () => {
    const onError = cy.spy().as('error')
    cy.mount(Url, {
      props: { modelValue: '/some/path', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('rejects a URL with a disallowed schema', () => {
    const onError = cy.spy().as('error')
    cy.mount(Url, {
      props: {
        modelValue: 'ftp://example.com',
        config: { allowed: ['http', 'https'] },
        onError,
      },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits update:modelValue as the user types', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Url, {
      props: { config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('input').type('https://new.com')
    cy.get('@update').should('have.been.called')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Url, { props: { config: {}, readonly: true } })
    cy.get('input').should('have.attr', 'readonly')
  })
})
