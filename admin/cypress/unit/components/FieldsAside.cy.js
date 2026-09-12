import { h } from 'vue'
import FieldsAside from '../../../js/components/FieldsAside.vue'
import { useDrawerStore, useSchemaStore } from '../../../js/stores'

const stubs = {
  Fields: { template: '<div class="fields-stub" />' },
  SchemaDialog: {
    props: { elements: Boolean, modelValue: Boolean },
    emits: ['add', 'update:modelValue'],
    render() {
      return this.modelValue
        ? h('button', { class: 'schema-dialog-stub', onClick: () => this.$emit('add', { type: 'text' }) }, 'Choose text')
        : null
    },
  },
}

const defaultSchemas = {
  content: {
    heading: { fields: { title: { type: 'string', label: 'Title' } } },
    text: { fields: { text: { type: 'text', label: 'Text' } } },
  },
}

const element = {
  type: 'heading',
  data: { title: 'Hello' },
  files: [],
  _changed: false,
  _error: false,
}

function mountAside(props = {}, schemas = {}) {
  return cy.mount(FieldsAside, {
    props: {
      element: { ...element },
      ...props,
    },
    global: {
      stubs,
      plugins: [{
        install() {
          const drawer = useDrawerStore()
          const store = useSchemaStore()
          drawer.aside = true
          Object.assign(store, { ...defaultSchemas, ...schemas })
        }
      }],
    },
  })
}

describe('FieldsAside', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the content element editor in a navigation drawer', () => {
    cy.viewport(1000, 720)
    mountAside()
    cy.get('.v-navigation-drawer')
      .should('exist')
      .and('have.attr', 'aria-label', 'Content Element')
      .and('have.css', 'border-start-start-radius', '8px')
      .and('have.css', 'border-top-right-radius', '0px')
      .and('have.css', 'border-bottom-right-radius', '0px')
      .and('have.css', 'border-bottom-left-radius', '0px')
      .and('have.css', 'overflow', 'hidden')
    cy.get('.v-dialog').should('not.exist')
  })

  it('keeps the drawer label without rendering it as a header title', () => {
    mountAside()
    cy.get('.v-navigation-drawer').should('have.attr', 'aria-label', 'Content Element')
    cy.get('.v-toolbar-title').should('not.exist')
  })

  it('renders the close button first in the header', () => {
    mountAside()
    cy.get('.v-toolbar button').first().should('have.attr', 'aria-label', 'Close')
  })

  it('uses the theme surface header background', () => {
    mountAside()
    cy.get('.element-toolbar').should('have.class', 'bg-surface')
  })

  it('shows the element type between the header and fields', () => {
    mountAside()
    cy.get('.element-toolbar + .element-info + .fields').should('exist')
    cy.get('.element-type').should('contain', 'heading')
  })

  it('switches the element type and can revert the element', () => {
    mountAside({ actions: true })
    cy.get('.btn-revert').should('be.disabled')
    cy.get('.btn-change-type').click()
    cy.get('.schema-dialog-stub').click()

    cy.then(() => {
      const aside = Cypress.vueWrapper.findComponent(FieldsAside)
      expect(aside.vm.element.type).to.equal('text')
      expect(aside.vm.element._changed).to.equal(true)
      expect(aside.vm.element._error).to.equal(undefined)
      expect(aside.emitted('change')).to.have.length(1)
    })

    cy.get('.element-type').should('contain', 'text')
    cy.get('.btn-revert').should('not.be.disabled').click()
    cy.then(() => {
      const aside = Cypress.vueWrapper.findComponent(FieldsAside)
      expect(aside.vm.element.type).to.equal('heading')
      expect(aside.vm.element.data).to.deep.equal({ title: 'Hello' })
      expect(aside.vm.element._changed).to.equal(false)
      expect(aside.emitted('change')).to.have.length(2)
    })
  })

  it('hides type management buttons for readonly elements', () => {
    mountAside({ actions: true, readonly: true })
    cy.get('.btn-change-type').should('not.exist')
    cy.get('.btn-revert').should('not.exist')
  })

  it('shows content management actions for editable selected elements', () => {
    mountAside({ actions: true })
    cy.get('.v-toolbar .v-spacer').should('exist')
    cy.get('.btn-add-before').should('exist')
    cy.get('.btn-add-after').should('exist')
    cy.get('.btn-remove').should('exist')
  })

  it('centers element actions and places the responsive selector last', () => {
    mountAside({ actions: true })
    cy.get('.element-toolbar button').last().should('have.class', 'btn-responsive')
    cy.get('.element-toolbar').then(($toolbar) => {
      cy.get('.element-actions').then(($actions) => {
        const toolbar = $toolbar[0].getBoundingClientRect()
        const actions = $actions[0].getBoundingClientRect()

        expect(actions.left + actions.width / 2).to.be.closeTo(toolbar.left + toolbar.width / 2, 1)
      })
    })
  })

  it('offers mobile, tablet, and computer preview sizes', () => {
    mountAside({ previewSize: 'computer' })
    cy.get('.btn-responsive').click()
    cy.get('.preview-size-option').should('have.length', 3)
    cy.contains('.preview-size-option', 'Mobile').should('contain', '384 px')
    cy.contains('.preview-size-option', 'Tablet').should('contain', '768 px')
    cy.contains('.preview-size-option', 'Computer').should('contain', 'Full width')
    cy.contains('.preview-size-option', 'Mobile').click()
    cy.then(() => {
      const aside = Cypress.vueWrapper.findComponent(FieldsAside)
      expect(aside.emitted('update:previewSize')).to.deep.equal([['mobile']])
    })
  })

  it('hides content management actions without permission or a selected element', () => {
    mountAside()
    cy.get('.btn-add-before').should('not.exist')
    cy.get('.btn-add-after').should('not.exist')
    cy.get('.btn-remove').should('not.exist')
  })

  it('emits content management actions', () => {
    mountAside({ actions: true })
    cy.get('.btn-add-before').click()
    cy.get('.btn-add-after').click()
    cy.get('.btn-remove').click()
    cy.then(() => {
      const aside = Cypress.vueWrapper.findComponent(FieldsAside)
      expect(aside.emitted('add-before')).to.have.length(1)
      expect(aside.emitted('add-after')).to.have.length(1)
      expect(aside.emitted('remove')).to.have.length(1)
    })
  })

  it('closes the shared drawer', () => {
    mountAside()
    cy.get('button[aria-label="Close"]').click()
    cy.then(() => expect(useDrawerStore().aside).to.equal(false))
  })

  it('reports a changed element when the sidebar is closed', () => {
    const onChange = cy.spy().as('change')

    mountAside({ element: { ...element, _changed: true }, onChange })
    cy.get('button[aria-label="Close"]').click()
    cy.get('@change').should('have.been.calledOnceWith', Cypress.sinon.match({ _changed: true }))
  })

  it('reports field changes immediately', () => {
    const onChange = cy.spy().as('change')

    cy.mount(FieldsAside, {
      props: { element: { ...element }, onChange },
      global: {
        stubs: {
          Fields: {
            render() { return h('button', { class: 'fields-stub', onClick: () => this.$emit('change') }, 'Change') },
            emits: ['change'],
          },
        },
        plugins: [{
          install() {
            useDrawerStore().aside = true
            Object.assign(useSchemaStore(), defaultSchemas)
          }
        }],
      },
    })

    cy.get('.fields-stub').click()
    cy.get('@change').should('have.been.calledOnceWith', Cypress.sinon.match({ _changed: true }))
  })

  it('does not render a local save button for changed elements', () => {
    mountAside({ element: { ...element, _changed: true } })
    cy.contains('.v-btn', 'Save').should('not.exist')
  })

  it('uses a full-width touch-friendly editor on mobile', () => {
    cy.viewport(375, 667)
    mountAside({ actions: true })
    cy.get('.v-navigation-drawer').should('have.css', 'width', '375px')
    cy.get('.v-navigation-drawer').should('have.css', 'border-top-left-radius', '0px')
    cy.get('.element-toolbar').should('have.css', 'position', 'sticky')
    cy.get('.element-toolbar button').first().should('have.css', 'min-height', '44px')
    cy.get('.fields').should('have.css', 'padding-left', '12px')
  })

  it('renders the Fields component', () => {
    mountAside()
    cy.get('.fields-stub').should('exist')
  })
})
