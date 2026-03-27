import PageListItems from '../../src/components/PageListItems.vue'
import { useUserStore } from '../../src/stores'

const stubs = {
  Draggable: {
    template: '<div class="draggable-stub" />',
    props: ['modelValue'],
    data() { return { statsFlat: [] } },
    methods: { getSiblings() { return [] }, add() {}, remove() {}, move() {}, addMulti() {} },
  },
}

function mountList(props = {}, perms = {}) {
  return cy.mount(PageListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
      },
      mocks: {
        $apollo: {
          query: () => Promise.resolve({
            data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
          }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
        },
      },
      plugins: [{
        install() {
          const user = useUserStore()
          user.me = { permission: perms }
        }
      }],
    },
  })
}

describe('PageListItems', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountList({}, { 'page:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'page:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'page:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'page:view': true })
    cy.get('button[title="Reload page tree"]').should('exist')
  })

  it('shows add button with page:add permission and not embed', () => {
    mountList({ embed: false }, { 'page:view': true, 'page:add': true })
    cy.get('button[title="Add page"]').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'page:view': true, 'page:add': true })
    cy.get('button[title="Add page"]').should('not.exist')
  })

  it('hides add button without page:add permission', () => {
    mountList({}, { 'page:view': true })
    cy.get('button[title="Add page"]').should('not.exist')
  })

  it('shows no entries message when not loading and items are empty', () => {
    mountList({}, { 'page:view': true })
    cy.contains('No entries found').should('exist')
  })

  it('hides sort dropdown in tree view (default)', () => {
    mountList({ filter: { view: 'tree' } }, { 'page:view': true })
    cy.get('button[title="Sort by"]').should('not.exist')
  })

  it('shows sort dropdown in list view', () => {
    mountList({ filter: { view: 'list' } }, { 'page:view': true })
    cy.get('button[title="Sort by"]').should('exist')
  })
})
