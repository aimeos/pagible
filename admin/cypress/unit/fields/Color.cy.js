import { h, ref } from 'vue'
import Color from '../../../js/fields/Color.vue'

describe('Color', () => {
  it('renders a color input', () => {
    cy.mount(Color, { props: { config: {} } })
    cy.get('.v-color-input').should('exist')
  })

  it('emits error:false for a valid 6-digit hex color', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FF5733', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:false for a valid 8-digit hex color (with alpha)', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FF573380', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true for an invalid hex string', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: 'not-a-color', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false for a valid 3-digit hex color', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FFF', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true for a truncated hex value', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FF', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false for an empty value when not required', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '', config: { required: false }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when required and no value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '', config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('uses config.default when no modelValue is supplied', () => {
    cy.mount(Color, { props: { config: { default: '#123456' } } })
    cy.get('input').should('have.value', '#123456')
  })

  it('emits an empty string on clear so the default is not shown again', () => {
    const value = ref('#FF5733')
    const onUpdate = cy.spy().as('update')
    const Host = {
      render: () => h(Color, {
        modelValue: value.value,
        config: { default: '#123456' },
        'onUpdate:modelValue': (v) => { onUpdate(v); value.value = v },
      })
    }

    cy.mount(Host)
    cy.get('.v-field__clearable .v-icon').click({ force: true })
    cy.get('@update').should('have.been.calledWith', '')
    cy.get('input').first().should('have.value', '')
  })
})
