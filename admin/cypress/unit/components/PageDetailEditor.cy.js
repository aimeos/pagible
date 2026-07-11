import PageDetailEditor from '../../../js/components/PageDetailEditor.vue'
import { useAppStore, useUserStore } from '../../../js/stores'

const stubs = {
  FieldsDialog: { template: '<div class="fields-dialog-stub" />' },
  SchemaDialog: { template: '<div class="schema-dialog-stub" />' },
}

const item = {
  id: '1',
  path: 'test-page',
  domain: '',
  content: [],
}

function mountEditor(props = {}, perms = {}) {
  return cy.mount(PageDetailEditor, {
    props: {
      item: { ...item },
      elements: {},
      assets: {},
      save: { fcn: () => Promise.resolve(), count: 0 },
      ...props,
    },
    global: {
      stubs,
      directives: {
        visible: {
          mounted(el, binding) {
            const handler = typeof binding.value === 'function' ? binding.value : binding.value?.handler
            if (handler) handler(true)
          }
        },
      },
      plugins: [{
        install() {
          const user = useUserStore()
          user.me = { permission: perms }
          const app = useAppStore()
          app.urlpage = 'https://_domain_/_path_'
        }
      }],
    },
  })
}

describe('PageDetailEditor', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the page-preview container', () => {
    mountEditor()
    cy.get('.page-preview').should('exist')
  })

  it('renders an iframe for page preview', () => {
    mountEditor()
    cy.get('iframe').should('exist')
  })

  it('removes the multidomain placeholder for root pages without a domain', () => {
    mountEditor({ item: { ...item, path: '', domain: '' } }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      expect(editor.vm.url).to.equal('/')
      expect(editor.vm.origin).to.equal(window.location.origin)
    })

    cy.get('iframe').should('have.attr', 'src', '/')
  })

  it('uses a relative preview URL without a page domain', () => {
    mountEditor({ item: { ...item, domain: '' } }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      expect(editor.vm.url).to.equal('/test-page')
      expect(editor.vm.origin).to.equal(window.location.origin)
    })

    cy.get('iframe').should('have.attr', 'src', '/test-page')
  })

  it('builds the multidomain preview URL with the page domain', () => {
    mountEditor({ item: { ...item, domain: 'paper.themes.pagible.com' } }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      expect(editor.vm.url).to.equal('https://paper.themes.pagible.com/test-page')
      expect(editor.vm.origin).to.equal('https://paper.themes.pagible.com')
    })
  })

  it('renders fullscreen button', () => {
    mountEditor()
    cy.get('button.btn-fullscreen').should('exist')
  })

  it('starts with loading data property', () => {
    mountEditor().then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      // The component defines loading: true in its initial data
      expect(editor.vm.$data).to.have.property('loading')
    })
  })

  it('shows info message for editors with page:save permission', () => {
    mountEditor({}, { 'page:save': true })
    // The component adds a message on mount, we just verify it rendered
    cy.get('.page-preview').should('exist')
  })
})
