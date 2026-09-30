import ShortcutDialog from '../../../js/components/ShortcutDialog.vue'
import { useUserStore } from '../../../js/stores'
import { commands, routes, shortcuts } from '../../../js/shortcuts'

describe('ShortcutDialog', () => {
  const all = Object.fromEntries(
    [...Object.values(commands).flatMap((cmd) => cmd.permission || []), ...Object.keys(routes), 'page:chat']
      .map((perm) => [perm, true])
  )

  const mount = (perms = all) =>
    cy.mount(ShortcutDialog, {
      global: { plugins: [{ install: () => (useUserStore().me = { permission: perms }) }] }
    }).then(() => (shortcuts.sheet = true))

  beforeEach(() => {
    cy.viewport(1000, 700)
    shortcuts.sheet = false
  })

  it('shows the shortcut groups when opened and closes again', () => {
    mount()

    cy.contains('.v-dialog:visible .v-toolbar-title', 'Keyboard shortcuts').should('exist')
    cy.contains('.v-dialog:visible .shortcut', 'Save changes').find('kbd').should('contain', 'S')
    cy.contains('.v-dialog:visible h2', 'Page tree').should('exist')

    cy.get('.v-dialog:visible .v-toolbar .v-btn').click()
    cy.get('.v-dialog:visible').should('not.exist')
    cy.then(() => expect(shortcuts.sheet).to.equal(false))
  })

  it('lists every command and route of the catalog with its keys', () => {
    mount()

    for (const entry of [...Object.values(commands), ...Object.values(routes)]) {
      cy.contains('.v-dialog:visible .shortcut dd', entry.label()).parent().find('kbd').should(($kbd) => {
        expect([...$kbd].map((el) => el.textContent)).to.deep.equal(entry.keys)
      })
    }
  })

  it('shows only the commands and routes the user has access to', () => {
    mount({ 'page:view': true })

    cy.contains('.v-dialog:visible .shortcut dd', commands.back.label()).should('exist')
    cy.contains('.v-dialog:visible .shortcut dd', routes['page:view'].label()).should('exist')
    cy.contains('.v-dialog:visible h2', 'Page tree').should('exist')

    for (const name of ['save', 'publish', 'create', 'drop', 'copy', 'cut', 'paste']) {
      cy.contains('.v-dialog:visible .shortcut dd', commands[name].label()).should('not.exist')
    }
    cy.contains('.v-dialog:visible .shortcut dd', routes['file:view'].label()).should('not.exist')
    cy.contains('.v-dialog:visible .shortcut dd', 'Add subpage to focused page').should('not.exist')
    cy.contains('.v-dialog:visible h2', 'Page content').should('not.exist')
    cy.contains('.v-dialog:visible h2', 'AI chat').should('not.exist')
  })
})
