import SchemaDialog from '../../../js/components/SchemaDialog.vue'

const stubs = {
  SchemaItems: { template: '<div class="schema-items-stub" />' },
  ElementListItems: { template: '<div class="element-list-stub" />' },
}

function mountDialog(props = {}) {
  return cy.mount(SchemaDialog, {
    props: {
      modelValue: true,
      ...props,
    },
    global: { stubs },
  })
}

describe('SchemaDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "Content elements" as the title', () => {
    mountDialog()
    cy.contains('Content elements').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[aria-label="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(SchemaDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
      global: { stubs },
    })
    cy.get('button[aria-label="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('renders the SchemaItems stub', () => {
    mountDialog()
    cy.get('.schema-items-stub').should('exist')
  })

  it('renders the ElementListItems stub by default', () => {
    mountDialog()
    cy.get('.element-list-stub').should('exist')
  })

  it('shows "Shared elements" tab when elements prop is true', () => {
    mountDialog({ elements: true })
    cy.contains('Shared elements').should('exist')
  })

  it('hides ElementListItems when elements prop is false', () => {
    mountDialog({ elements: false })
    cy.get('.element-list-stub').should('not.exist')
  })
})
