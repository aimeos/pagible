import ShortcutDialog from '../../../js/components/ShortcutDialog.vue'
import { commands, routes, shortcuts } from '../../../js/shortcuts'

describe('ShortcutDialog', () => {
  beforeEach(() => {
    cy.viewport(1000, 700)
    shortcuts.sheet = false
  })

  it('shows the shortcut groups when opened and closes again', () => {
    cy.mount(ShortcutDialog).then(() => (shortcuts.sheet = true))

    cy.contains('.v-dialog:visible .v-toolbar-title', 'Keyboard shortcuts').should('exist')
    cy.contains('.v-dialog:visible .shortcut', 'Save changes').find('kbd').should('contain', 'S')
    cy.contains('.v-dialog:visible h2', 'Page tree').should('exist')

    cy.get('.v-dialog:visible .v-toolbar .v-btn').click()
    cy.get('.v-dialog:visible').should('not.exist')
    cy.then(() => expect(shortcuts.sheet).to.equal(false))
  })

  it('lists every command and route of the catalog with its keys', () => {
    cy.mount(ShortcutDialog).then(() => (shortcuts.sheet = true))

    for (const entry of [...Object.values(commands), ...Object.values(routes)]) {
      cy.contains('.v-dialog:visible .shortcut dd', entry.label()).parent().find('kbd').should(($kbd) => {
        expect([...$kbd].map((el) => el.textContent)).to.deep.equal(entry.keys)
      })
    }
  })
})
