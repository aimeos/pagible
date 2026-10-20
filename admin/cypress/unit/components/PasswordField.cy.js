import PasswordField from '../../../js/components/PasswordField.vue'

describe('PasswordField', () => {
  it('toggles the visibility of the password', () => {
    cy.mount(PasswordField, { props: { modelValue: 'secret' } })

    cy.get('input').should('have.attr', 'type', 'password')
    cy.get('.v-field__append-inner .v-btn').click()
    cy.get('input').should('have.attr', 'type', 'text')
  })

  it('hides the password again', () => {
    cy.mount(PasswordField, { props: { modelValue: 'secret' } }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PasswordField).vm
      vm.show = true
      return vm.$nextTick().then(() => vm.hide())
    })

    cy.get('input').should('have.attr', 'type', 'password')
  })

  it('emits the entered password', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(PasswordField, { props: { modelValue: '', 'onUpdate:modelValue': onUpdate } })

    cy.get('input').type('x')
    cy.get('@update').should('have.been.calledWith', 'x')
  })
})
