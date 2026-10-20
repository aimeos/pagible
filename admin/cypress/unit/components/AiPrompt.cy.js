import { h } from 'vue'
import AiPrompt from '../../../js/components/AiPrompt.vue'

function mountPrompt(props = {}) {
  const onSubmit = cy.spy().as('submit')

  // cy.mount() doesn't pass slots, so the prompt is rendered by a host component
  return cy.mount({
    render: () => h(AiPrompt, { type: 'test', modelValue: 'Do it', onSubmit, ...props }, {
      default: () => h('li', 'Help text')
    })
  })
}

describe('AiPrompt', () => {
  it('emits submit on Enter', () => {
    mountPrompt()
    cy.get('.prompt textarea').first().type('{enter}', { force: true })
    cy.get('@submit').should('have.been.calledOnce')
  })

  it('does not emit submit on Shift+Enter', () => {
    mountPrompt()
    cy.get('.prompt textarea').first().type('{shift}{enter}', { force: true })
    cy.get('@submit').should('not.have.been.called')
  })

  it('does not emit submit on Enter confirming an input method word', () => {
    mountPrompt()
    cy.get('.prompt textarea').first()
      .trigger('keydown', { eventConstructor: 'KeyboardEvent', key: 'Enter', keyCode: 229, force: true })
    cy.get('@submit').should('not.have.been.called')
  })

  it('ignores submits while busy', () => {
    mountPrompt({ busy: true })
    cy.get('.prompt textarea').first().type('{enter}', { force: true })
    cy.get('@submit').should('not.have.been.called')
  })

  it('shows the send button with the passed title', () => {
    mountPrompt({ sendTitle: 'Refine' })
    cy.get('.prompt .v-input__append .v-btn').should('have.attr', 'title', 'Refine').click()
    cy.get('@submit').should('have.been.calledOnce')
  })

  it('shows the done button without text', () => {
    mountPrompt({ modelValue: '', done: true, sendTitle: 'Refine' })
    cy.get('.prompt .v-input__append .v-btn').should('have.attr', 'title', 'Refine')
  })

  it('uses the passed placeholder', () => {
    mountPrompt({ placeholder: 'Describe it' })
    cy.get('.prompt textarea').first().should('have.attr', 'placeholder', 'Describe it')
      .and('have.attr', 'aria-label', 'Describe it')
  })

  it('toggles the help', () => {
    mountPrompt()
    cy.get('#test-help').should('not.exist')
    cy.get('.prompt .v-input__prepend .v-btn').click()
    cy.contains('#test-help li', 'Help text').should('exist')
  })

  it('appends dictated text to the prompt', () => {
    const onUpdate = cy.spy().as('update')

    mountPrompt({ 'onUpdate:modelValue': onUpdate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(AiPrompt).vm
      cy.stub(vm.dictation, 'toggle').callsFake((done) => done('now'))

      vm.record()
      expect(onUpdate).to.have.been.calledWith('Do it now')
    })
  })
})
