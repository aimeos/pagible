import FileListItems from '../../js/components/FileListItems.vue'
import { useUserStore } from '../../js/stores'

const stubs = {
}

function mountList(props = {}, perms = {}) {
  return cy.mount(FileListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
        url: (path) => path,
        srcset: () => '',
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileListItems', () => {
  it('renders the component', () => {
    mountList({}, { 'file:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'file:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'file:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('renders sort menu button', () => {
    mountList({}, { 'file:view': true })
    cy.get('button[title="Sort by"]').should('exist')
  })

  it('renders grid/list toggle button', () => {
    mountList({}, { 'file:view': true })
    cy.get('button[title="Grid view"], button[title="List view"]').should('exist')
  })

  it('shows add button with file:add permission and not embed', () => {
    mountList({ embed: false }, { 'file:view': true, 'file:add': true })
    cy.get('button[title="Add files"]').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'file:view': true, 'file:add': true })
    cy.get('button[title="Add files"]').should('not.exist')
  })

  it('hides add button without file:add permission', () => {
    mountList({}, { 'file:view': true })
    cy.get('button[title="Add files"]').should('not.exist')
  })

  it('shows loading state initially', () => {
    mountList({}, { 'file:view': true })
    cy.contains('Loading').should('exist')
  })

  it('starts in list view by default', () => {
    mountList({}, { 'file:view': true })
    cy.get('button[title="Grid view"]').should('exist')
  })

  it('starts in grid view when grid prop is true', () => {
    mountList({ grid: true }, { 'file:view': true })
    cy.get('button[title="List view"]').should('exist')
  })
})
