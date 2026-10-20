import { h, ref } from 'vue'
import Html from '../../../js/fields/Html.vue'

describe('Html (textarea)', () => {
  it('renders a textarea', () => {
    cy.mount(Html, { props: { config: {} } })
    cy.get('.v-textarea').should('exist')
  })

  it('displays the modelValue', () => {
    cy.mount(Html, { props: { modelValue: '<p>Hello</p>', config: {} } })
    cy.get('textarea').first().should('have.value', '<p>Hello</p>')
  })

  it('uses config.default when no modelValue is supplied', () => {
    cy.mount(Html, { props: { config: { default: '<p>Default</p>' } } })
    cy.get('textarea').first().should('have.value', '<p>Default</p>')
  })

  it('emits error:false when value is empty and not required', () => {
    const onError = cy.spy().as('error')
    cy.mount(Html, {
      props: { modelValue: '', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when value is empty and required', () => {
    const onError = cy.spy().as('error')
    cy.mount(Html, {
      props: { modelValue: '', config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:true when value exceeds config.max', () => {
    const onError = cy.spy().as('error')
    cy.mount(Html, {
      props: { modelValue: '<p>Content</p>', config: { max: 5 }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value is non-empty', () => {
    const onError = cy.spy().as('error')
    cy.mount(Html, {
      props: { modelValue: '<p>Content</p>', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('shows placeholder from config', () => {
    cy.mount(Html, { props: { config: { placeholder: 'Enter HTML…' } } })
    cy.get('textarea').first().should('have.attr', 'placeholder', 'Enter HTML…')
  })

  it('emits update:modelValue as the user types', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Html, {
      props: { config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('textarea').first().type('<b>bold</b>', { parseSpecialCharSequences: false })
    cy.get('@update').should('have.been.called')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Html, { props: { config: {}, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })

  it('emits an empty string on clear so the default is not shown again', () => {
    const value = ref('Changed')
    const onUpdate = cy.spy().as('update')
    const Host = {
      render: () => h(Html, {
        modelValue: value.value,
        config: { default: 'Default' },
        'onUpdate:modelValue': (v) => { onUpdate(v); value.value = v },
      })
    }

    cy.mount(Host)
    cy.get('.v-field__clearable .v-icon').click({ force: true })
    cy.get('@update').should('have.been.calledWith', '')
    cy.get('textarea').first().should('have.value', '')
  })

  it('has no clear button when readonly', () => {
    cy.mount(Html, { props: { modelValue: 'Text', config: {}, readonly: true } })
    cy.get('.v-field__clearable').should('not.exist')
  })
})
