import TextField from '../../../js/fields/Text.vue'

Cypress.on('uncaught:exception', (err) => {
  if (err.message.includes('ckeditor5/translations')) return false
})

const stubs = {
  Ckeditor: {
    template: '<div class="ck-editor-stub" />',
    props: ['modelValue', 'config', 'editor', 'disabled'],
  },
}

const directives = {
  'observe-visibility': (el, binding) => {
    if (typeof binding.value === 'function') {
      binding.value(true)
    }
  },
}

function mountText(props = {}) {
  return cy.mount(TextField, {
    props: { config: {}, ...props },
    global: { stubs, directives },
  })
}

describe('Text (CKEditor)', () => {
  it('renders a container', () => {
    mountText()
    cy.get('div').should('exist')
  })

  it('emits error:false when no min/max constraints', () => {
    const onError = cy.spy().as('error')
    mountText({ modelValue: 'some text', onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when value is shorter than config.min', () => {
    const onError = cy.spy().as('error')
    mountText({ modelValue: 'Hi', config: { min: 10 }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value meets config.min', () => {
    const onError = cy.spy().as('error')
    mountText({ modelValue: 'Hello world', config: { min: 5 }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when value exceeds config.max', () => {
    const onError = cy.spy().as('error')
    mountText({ modelValue: 'This is too long', config: { max: 5 }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value is within config.max', () => {
    const onError = cy.spy().as('error')
    mountText({ modelValue: 'Hi', config: { max: 10 }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('uses config.default for validation when modelValue is null', () => {
    const onError = cy.spy().as('error')
    mountText({ modelValue: null, config: { default: 'default text', min: 5 }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('accepts an empty string with min if not required', () => {
    const onError = cy.spy().as('error')
    mountText({ config: { min: 5, default: '' }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('rejects an empty string if required', () => {
    const onError = cy.spy().as('error')
    mountText({ config: { required: true, default: '' }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  function context(query) {
    const ctx = {
      plugins: [],
      toolbar: [],
      translations: undefined,
      destroyed: false,
      linkPages: [],
      $vuetify: { locale: { current: 'en' } },
      $gettext: (text) => text,
      $log: () => {},
      $apollo: { query }
    }
    ctx.pageLinks = TextField.methods.pageLinks.bind(ctx)
    ctx.loadPages = TextField.methods.loadPages.bind(ctx)
    return ctx
  }

  it('allows page links in the link plugin', () => {
    const config = TextField.computed.ckconfig.call(context())

    expect(config.link.allowedProtocols).to.include('page')
    expect(config.link.allowedProtocols).to.include('https?')
    expect(config.extraPlugins).to.have.length(1)
  })

  it('provides the pages as link list', () => {
    const pages = [{ id: 'abc-123', name: 'Home', path: '' }, { id: 'def-456', name: 'Blog', path: 'blog' }]
    const query = cy.stub().resolves({ data: { pages: { data: pages } } })
    const ctx = context(query)
    let provider = null

    const editor = {
      plugins: {
        has: () => true,
        get: () => ({ registerLinksListProvider: (p) => { provider = p } })
      }
    }

    const Plugin = TextField.computed.ckconfig.call(ctx).extraPlugins[0]
    new Plugin(editor).afterInit()

    cy.wrap(query).should('have.been.calledOnce')
    cy.wrap(ctx).its('linkPages').should('have.length', 2)
    cy.then(() => {
      const items = provider.getListItems()

      expect(items[1]).to.deep.include({ href: 'page:def-456', label: 'Blog (/blog)' })
      expect(provider.getItem('page:def-456')).to.deep.include({ href: 'page:def-456', label: 'Blog', tooltip: '/blog' })
      expect(provider.getItem('page:unknown')).to.deep.include({ href: 'page:unknown', label: 'page:unknown' })
      expect(provider.getItem('https://example.com')).to.equal(null)
      expect(provider.navigate({ href: 'page:def-456' })).to.equal(true)
      expect(provider.navigate({ href: 'https://example.com' })).to.equal(false)
    })
  })
})
