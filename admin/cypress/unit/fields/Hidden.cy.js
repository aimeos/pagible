import Hidden from '../../../js/fields/Hidden.vue'

describe('Hidden', () => {
  it('renders nothing visible', () => {
    cy.mount(Hidden, { props: { config: { value: 'secret' } } })
    // The template is empty – no visible form element should exist
    cy.get('.v-input').should('not.exist')
  })

  it('emits update:modelValue with config.value on creation when they differ', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Hidden, {
      props: {
        modelValue: '',
        config: { value: 'injected' },
        'onUpdate:modelValue': onUpdate,
      },
    })
    cy.get('@update').should('have.been.calledWith', 'injected')
  })

  it('does not emit when modelValue already matches config.value', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Hidden, {
      props: {
        modelValue: 'same',
        config: { value: 'same' },
        'onUpdate:modelValue': onUpdate,
      },
    })
    cy.get('@update').should('not.have.been.called')
  })
})
