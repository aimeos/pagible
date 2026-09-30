import '../../../js/assets/base.css'
import { h } from 'vue'
import SchemaDialog from '../../../js/components/SchemaDialog.vue'

const stubs = {
  SchemaItems: {
    props: ['type'],
    render() { return h('div', { class: 'schema-items-stub', 'data-type': this.type }) }
  },
  ElementListItems: {
    render() { return h('div', { class: 'element-list-stub' }, 'shared') }
  },
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
    cy.viewport(800, 600)
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
    cy.get('.schema-items-stub').should('have.attr', 'data-type', 'content')
  })

  it('forwards a custom schema type', () => {
    mountDialog({ type: 'sidebar', elements: false })
    cy.get('.schema-items-stub').should('have.attr', 'data-type', 'sidebar')
  })

  it('shows "New elements" and "Shared elements" tabs when elements prop is true', () => {
    mountDialog({ elements: true })
    cy.contains('.v-tab', 'New elements').should('exist')
    cy.contains('.v-tab', 'Shared elements').should('exist')
  })

  it('renders the ElementListItems stub in the shared elements tab', () => {
    mountDialog()
    cy.contains('.v-tab', 'Shared elements').click()
    cy.get('.element-list-stub').should('be.visible')
  })

  it('uses the primary tint for the shared elements header', () => {
    mountDialog({ elements: true })
    cy.get('.v-dialog .v-tabs')
      .then(($tabs) => {
        $tabs[0].style.setProperty('transition', 'none')
        $tabs[0].style.setProperty('--v-theme-primary', '29, 78, 216')
        $tabs[0].style.setProperty('--v-theme-on-surface', '15, 23, 42')
      })
      .should('have.css', 'background-color', 'rgba(29, 78, 216, 0.16)')
      .and('have.css', 'color', 'rgb(15, 23, 42)')
  })

  it('hides the tabs and ElementListItems when elements prop is false', () => {
    mountDialog({ elements: false })
    cy.get('.v-dialog .v-tab').should('not.exist')
    cy.get('.element-list-stub').should('not.exist')
  })
})
