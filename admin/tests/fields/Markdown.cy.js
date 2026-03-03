import MarkdownField from '../../src/fields/Markdown.vue'

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

function mountMarkdown(props = {}) {
  return cy.mount(MarkdownField, {
    props: { config: {}, ...props },
    global: { stubs, directives },
  })
}

describe('Markdown (CKEditor)', () => {
  it('renders a container', () => {
    mountMarkdown()
    cy.get('div').should('exist')
  })

  it('emits error:false when no min/max constraints', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ modelValue: '# Heading', onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when value is shorter than config.min', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ modelValue: 'Hi', config: { min: 10 }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value meets config.min', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ modelValue: 'Hello world', config: { min: 5 }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when value exceeds config.max', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ modelValue: 'This is too long', config: { max: 5 }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value is within config.max', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ modelValue: 'Hi', config: { max: 10 }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('uses config.default for validation when modelValue is null', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ modelValue: null, config: { default: 'default text', min: 5 }, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('validates empty string against min', () => {
    const onError = cy.spy().as('error')
    mountMarkdown({ config: { min: 1, default: '' }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })
})
