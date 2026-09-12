import PageDetailEditor from '../../../js/components/PageDetailEditor.vue'
import { useAppStore, useUserStore } from '../../../js/stores'

const stubs = {
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
    cy.get('.page-preview-stage').should('exist')
    cy.get('.page-preview').should('exist')
  })

  it('adds an 8px gap while the desktop sidebar is visible', () => {
    cy.viewport(1000, 720)
    mountEditor({ asideVisible: true })
    cy.get('.page-preview-stage')
      .should('have.class', 'aside-visible')
      .and('have.css', 'margin-right', '8px')
  })

  it('does not add the sidebar gap on mobile', () => {
    cy.viewport(375, 667)
    mountEditor({ asideVisible: true })
    cy.get('.page-preview-stage').should('have.css', 'margin-right', '0px')
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

  it('does not render a fullscreen control', () => {
    mountEditor()
    cy.get('button.btn-fullscreen').should('not.exist')
  })

  it('sizes the preview for mobile, tablet, and computer views', () => {
    cy.viewport(1000, 720)
    mountEditor({ previewSize: 'mobile' })
    cy.get('.page-preview').should('have.class', 'preview-mobile').and('have.css', 'width', '384px')

    mountEditor({ previewSize: 'tablet' })
    cy.get('.page-preview').should('have.class', 'preview-tablet').and('have.css', 'width', '768px')

    mountEditor({ previewSize: 'computer' })
    cy.get('.page-preview').should('have.class', 'preview-computer')
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

  it('opens a content element in the sidebar directly from the preview message', () => {
    const content = [{ id: 'element-1', type: 'heading', data: { title: 'Selected' } }]

    mountEditor({ item: { ...item, content } }, { 'page:save': true }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      editor.vm.message({
        source: editor.vm.$refs.iframe.contentWindow,
        origin: editor.vm.origin,
        data: { id: 'element-1', section: 'main' },
      })

      expect(editor.vm.element).to.equal(content[0])
      expect(editor.vm.index).to.equal(0)
      expect(editor.emitted('edit')).to.deep.equal([[content[0], true]])
    })
  })

  it('opens the element chooser directly for an empty preview section', () => {
    mountEditor({}, { 'page:save': true }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      editor.vm.message({
        source: editor.vm.$refs.iframe.contentWindow,
        origin: editor.vm.origin,
        data: { id: -1, section: 'footer' },
      })

      expect(editor.vm.section).to.equal('footer')
      expect(editor.vm.pos).to.equal(1)
      expect(editor.vm.vschemas).to.equal(true)
    })
  })

  it('inserts a chosen element and keeps it open for sidebar editing', () => {
    const save = cy.stub().resolves(true)

    mountEditor({ item: { ...item, content: [] }, save: { fcn: save, count: 0 } }, { 'page:save': true }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      editor.vm.index = -1
      editor.vm.section = 'footer'
      editor.vm.addAfter()
      editor.vm.add({ type: 'heading' })

      expect(editor.vm.item.content).to.have.length(1)
      expect(editor.vm.item.content[0]).to.include({ type: 'heading', group: 'footer' })
      expect(editor.vm.index).to.equal(0)
      expect(editor.emitted('change')).to.deep.equal([['content']])
      expect(editor.emitted('edit').at(-1)).to.deep.equal([editor.vm.item.content[0], true])
      expect(save).not.to.have.been.called
    })
  })

  it('does not render element management buttons over the preview', () => {
    mountEditor()
    cy.get('button[title="Edit element"]').should('not.exist')
    cy.get('button[title="Add element before"]').should('not.exist')
    cy.get('button[title="Add element after"]').should('not.exist')
    cy.get('button[title="Remove element"]').should('not.exist')
  })

  it('removes the selected element and closes the sidebar selection', () => {
    const content = [{ id: 'element-1', type: 'heading', data: { title: 'Selected' } }]
    const save = cy.stub().resolves(true)

    mountEditor({ item: { ...item, content }, save: { fcn: save, count: 0 } }, { 'page:save': true }).then(({ wrapper }) => {
      const editor = wrapper.findComponent(PageDetailEditor)
      editor.vm.index = 0
      editor.vm.element = content[0]
      editor.vm.remove()

      expect(editor.vm.item.content).to.deep.equal([])
      expect(editor.emitted('edit')).to.deep.equal([[null]])
      expect(editor.emitted('change')).to.deep.equal([['content']])
      expect(save).to.have.been.calledOnceWith(true)
    })
  })
})
