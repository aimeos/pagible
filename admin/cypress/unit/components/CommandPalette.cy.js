import CommandPalette from '../../../js/components/CommandPalette.vue'
import { useUserStore } from '../../../js/stores'
import { keydown, register, shortcuts } from '../../../js/shortcuts'

describe('CommandPalette', () => {
  const pages = [
    { id: 'p1', lang: 'en', name: 'Home', title: 'Home', path: '', latest: { id: 'v1', data: '{"name":"Home","path":"home"}' } },
    { id: 'p2', lang: 'en', name: 'Blog', title: 'Blog', path: 'blog', latest: null }
  ]

  // returns the pages whose name contains the search term like the server
  const search = () => cy.stub().callsFake(({ variables }) => Promise.resolve({
    data: {
      pages: variables.page ? { data: pages.filter((page) => page.name.toLowerCase().includes(variables.term)) } : null
    }
  }))

  function mount(perms = {}, query = search()) {
    const push = cy.stub().as('push')

    cy.mount(CommandPalette, {
      global: {
        mocks: { $apollo: { query }, $router: { push } },
        plugins: [{
          install() {
            useUserStore().me = { permission: perms }
          }
        }]
      }
    })

    return { push, query }
  }

  const open = () => cy.window().then(() => {
    keydown(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, cancelable: true }))
  })

  beforeEach(() => {
    shortcuts.palette = false
  })

  it('opens with Ctrl+K and lists the permitted panels', () => {
    mount({ 'page:view': true, 'file:view': true })
    open()

    cy.get('.command-palette .palette-input input').should('have.focus')
    cy.contains('.palette-item', 'Pages').should('exist')
    cy.contains('.palette-item', 'Media').should('exist')
    cy.contains('.palette-item', 'Users').should('not.exist')
    cy.contains('.palette-item', 'Show keyboard shortcuts').should('exist')
  })

  it('filters the commands and runs the selected one with Enter', () => {
    const { push } = mount({ 'page:view': true, 'file:view': true })
    open()

    cy.get('.command-palette .palette-input input').type('med')
    cy.get('.palette-item').should('have.length', 1).and('contain', 'Media').and('have.class', 'active')
    cy.get('.command-palette .palette-input input').type('{enter}')

    cy.get('.command-palette.v-overlay--active').should('not.exist')
    cy.get('@push').should('have.been.calledOnceWith', { name: 'file:view' })
  })

  it('offers the actions of the topmost view and triggers them after closing', () => {
    const save = cy.stub()
    const off = register({ save, back: cy.stub() })

    mount({ 'page:view': true, 'page:save': true })
    open()

    cy.contains('.palette-item', 'Back to list view').should('exist')
    cy.contains('.palette-item', 'Open publish menu').should('not.exist')
    cy.contains('.palette-item', 'Save changes').click()

    cy.get('.command-palette.v-overlay--active').should('not.exist')
    cy.wrap(save).should('have.been.calledOnce').then(() => off())
  })

  it('hides the actions the user has no permission for', () => {
    const off = register({ save: cy.stub(), back: cy.stub() })

    mount({ 'page:view': true })
    open()

    cy.contains('.palette-item', 'Back to list view').should('exist')
    cy.contains('.palette-item', 'Save changes').should('not.exist').then(() => off())
  })

  it('updates the actions when the topmost view changes while open', () => {
    mount({ 'page:view': true })
    open()

    cy.contains('.palette-item', 'Search in list').should('not.exist')
    cy.then(() => register({ search: cy.stub() })).then((off) => {
      cy.contains('.palette-item', 'Search in list').should('exist')
      cy.then(off)
    })
    cy.contains('.palette-item', 'Search in list').should('not.exist')
    cy.contains('.palette-item', 'Open commands').should('not.exist')
    cy.contains('.palette-item', 'Confirm dialog').should('not.exist')
  })

  it('searches the permitted content types and opens a result', () => {
    const { query } = mount({ 'page:view': true })
    open()

    cy.get('.command-palette .palette-input input').type('blo')
    cy.contains('.palette-item', 'Blog (en)').should('contain', '/blog')
    cy.contains('.palette-item', 'Home (en)').should('not.exist')
    cy.then(() => {
      expect(query).to.have.been.calledOnce
      expect(query.firstCall.args[0].variables).to.deep.equal({
        term: 'blo', limit: 5, page: true, file: false, element: false
      })
    })

    cy.get('.command-palette .palette-input input').type('{enter}')
    cy.get('.command-palette.v-overlay--active').should('not.exist')
    cy.get('@push').should('have.been.calledOnceWith', { name: 'page:detail', params: { id: 'p2' } })
  })

  it('reads the name and path of the latest version', () => {
    mount({ 'page:view': true })
    open()

    cy.get('.command-palette .palette-input input').type('home')
    cy.contains('.palette-item', 'Home (en)').should('contain', '/home')
  })

  it('shows an error if the search fails', () => {
    mount({ 'page:view': true }, cy.stub().rejects(new Error('offline')))
    open()

    cy.get('.command-palette .palette-input input').type('blo')
    cy.get('.palette-error').should('be.visible')
    cy.get('.palette-empty').should('not.exist')

    cy.get('.command-palette .palette-input input').clear()
    cy.get('.palette-error').should('not.exist')
  })

  it('does not search for a single character', () => {
    const { query } = mount({ 'page:view': true })
    open()

    cy.get('.command-palette .palette-input input').type('q')
    cy.wait(400)
    cy.then(() => expect(query).not.to.have.been.called)
    cy.get('.palette-empty').should('exist')
  })

  it('wraps around with the arrow keys and closes with Ctrl+K', () => {
    mount({ 'page:view': true })
    open()

    cy.get('.palette-item').first().should('have.class', 'active')
    cy.get('.command-palette .palette-input input').type('{uparrow}')
    cy.get('.palette-item').last().should('have.class', 'active')
    cy.get('.command-palette .palette-input input').should('have.attr', 'aria-activedescendant')

    open()
    cy.get('.command-palette.v-overlay--active').should('not.exist')
  })
})
