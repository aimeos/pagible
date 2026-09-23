import ConfirmDialog from '../../../js/components/ConfirmDialog.vue'
import { useConfirmStore } from '../../../js/stores'

describe('ConfirmDialog', () => {
  const ask = () => useConfirmStore().purge([{ name: 'Home', info: '/home' }], 'Subpages too')

  beforeEach(() => {
    cy.viewport(1000, 700)
  })

  it('lists the items in the standard warning dialog and resolves true on confirm', () => {
    let result

    cy.mount(ConfirmDialog).then(() => {
      result = ask()
    })

    // scrollable keeps long lists within the viewport
    cy.get('.v-dialog:visible')
      .should('have.attr', 'role', 'alertdialog')
      .and('have.class', 'v-dialog--scrollable')
    cy.get('.v-dialog:visible .v-toolbar').should('have.class', 'bg-warning')
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Purge').should('exist')
    cy.contains('.v-dialog:visible', 'permanently deleted').should('exist')
    cy.contains('.v-dialog:visible .v-list-item', 'Home').should('contain', '/home')
    cy.contains('.v-dialog:visible', 'Subpages too').should('exist')

    cy.get('.v-dialog:visible .btn-confirm').click()
    cy.then(() => result).should('equal', true)
    cy.get('.v-dialog:visible').should('not.exist')
  })

  it('resolves false on cancel', () => {
    let result

    cy.mount(ConfirmDialog).then(() => {
      result = ask()
    })

    cy.contains('.v-dialog:visible .v-btn', 'Cancel').click()
    cy.then(() => result).should('equal', false)
  })
})
