import { h } from 'vue'
import FieldsDialog from '../../src/components/FieldsDialog.vue'
import { useSchemaStore } from '../../src/stores'

const stubs = {
  Fields: { template: '<div class="fields-stub" />' },
}

const defaultSchemas = {
  content: {
    heading: { fields: { title: { type: 'string', label: 'Title' } } },
  },
}

const element = {
  type: 'heading',
  data: { title: 'Hello' },
  files: [],
  _changed: false,
  _error: false,
}

function mountDialog(props = {}, schemas = {}) {
  return cy.mount(FieldsDialog, {
    props: {
      modelValue: true,
      element: { ...element },
      ...props,
    },
    global: {
      stubs,
      plugins: [{
        install() {
          const store = useSchemaStore()
          Object.assign(store, { ...defaultSchemas, ...schemas })
        }
      }],
    },
  })
}

describe('FieldsDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "Content Element" as the title', () => {
    mountDialog()
    cy.contains('Content Element').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[title="Close"]').should('exist')
  })

  it('emits update:modelValue when close button is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(FieldsDialog, {
      props: {
        modelValue: true,
        element: { ...element },
        'onUpdate:modelValue': onUpdate,
      },
      global: {
        stubs,
        plugins: [{
          install() {
            const store = useSchemaStore()
            Object.assign(store, defaultSchemas)
          }
        }],
      },
    })
    cy.get('button[title="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('hides save button when element is not changed', () => {
    mountDialog({ element: { ...element, _changed: false } })
    cy.contains('.v-btn', 'Save').should('not.exist')
  })

  it('shows save button when element is changed and no error', () => {
    mountDialog({ element: { ...element, _changed: true } })
    cy.contains('.v-btn', 'Save').should('exist')
  })

  it('hides save button when readonly', () => {
    mountDialog({ element: { ...element, _changed: true }, readonly: true })
    cy.contains('.v-btn', 'Save').should('not.exist')
  })

  it('hides save button when element has error', () => {
    // Use a Fields stub that emits error on mount to set the local error state
    cy.mount(FieldsDialog, {
      props: {
        modelValue: true,
        element: { ...element, _changed: true },
      },
      global: {
        stubs: {
          Fields: {
            render() { return h('div', { class: 'fields-stub' }) },
            emits: ['error'],
            mounted() { this.$emit('error', true) },
          },
        },
        plugins: [{
          install() {
            const store = useSchemaStore()
            Object.assign(store, defaultSchemas)
          }
        }],
      },
    })
    cy.contains('.v-btn', 'Save').should('not.exist')
  })

  it('renders the Fields stub component', () => {
    mountDialog()
    cy.get('.fields-stub').should('exist')
  })
})
