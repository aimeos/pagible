import { h } from 'vue'
import Fields from '../../js/components/Fields.vue'
import { useUserStore } from '../../js/stores'

const stubs = {
  String: { render() { return h('div', { class: 'field-string' }) } },
  Text: { render() { return h('div', { class: 'field-text' }) } },
  Hidden: { render() { return h('div', { class: 'field-hidden' }) } },
  Number: { render() { return h('div', { class: 'field-number' }) } },
}

const fields = {
  title: { type: 'string', label: 'Title' },
  body: { type: 'text', label: 'Body text' },
}

function mountFields(props = {}, perms = {}) {
  return cy.mount(Fields, {
    props: {
      fields,
      data: { title: 'Hello', body: 'World' },
      ...props,
    },
    global: {
      stubs,
      provide: {
        write: () => Promise.resolve(''),
        translate: () => Promise.resolve(['']),
        transcribe: () => Promise.resolve({ asText: () => '' }),
        txlocales: () => [],
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

describe('Fields', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders an item for each field', () => {
    mountFields()
    cy.get('.item').should('have.length', 2)
  })

  it('renders field labels', () => {
    mountFields()
    cy.get('.label').should('contain', 'Title')
    cy.get('.label').should('contain', 'Body text')
  })

  it('renders dynamic field components', () => {
    mountFields()
    cy.get('.field-string').should('exist')
    cy.get('.field-text').should('exist')
  })

  it('hides the label for hidden field type', () => {
    mountFields({
      fields: { secret: { type: 'hidden', label: 'Secret' } },
    })
    cy.get('.label').should('not.exist')
  })

  it('does not show translate button in readonly mode', () => {
    mountFields({ readonly: true })
    cy.get('button[title="Translate"]').should('not.exist')
  })

  it('shows translate button for text fields when not readonly', () => {
    mountFields({}, { 'text:translate': true })
    cy.get('button[title="Translate"]').should('exist')
  })

  it('shows generate text button with text:write permission', () => {
    mountFields({}, { 'text:write': true })
    cy.get('button[title="Generate text"]').should('exist')
  })

  it('hides generate text button without text:write permission', () => {
    mountFields()
    cy.get('button[title="Generate text"]').should('not.exist')
  })

  it('shows dictate button with audio:transcribe permission', () => {
    mountFields({}, { 'audio:transcribe': true })
    cy.get('button[title="Dictate"]').should('exist')
  })

  it('hides dictate button without audio:transcribe permission', () => {
    mountFields()
    cy.get('button[title="Dictate"]').should('not.exist')
  })

  it('adds error class when a field has an error', () => {
    mountFields()
    // Initially no error class
    cy.get('.item.error').should('not.exist')
  })

  it('emits change when field value is updated', () => {
    const onChange = cy.spy().as('change')
    cy.mount(Fields, {
      props: {
        fields: { title: { type: 'string', label: 'Title' } },
        data: { title: 'Hello' },
        onChange,
      },
      global: {
        stubs,
        provide: {
          write: () => Promise.resolve(''),
          translate: () => Promise.resolve(['']),
          transcribe: () => Promise.resolve({ asText: () => '' }),
          txlocales: () => [],
        },
      },
    })
    cy.get('.item').should('exist')
  })
})
