import TextField from '../../js/fields/Text.vue'

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

  it('validates empty string against min', () => {
    const onError = cy.spy().as('error')
    mountText({ config: { min: 1, default: '' }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })
})
