import ItemsField from '../../js/fields/Items.vue'
import { useUserStore, useClipboardStore } from '../../js/stores'

const itemConfig = {
  item: {
    title: { type: 'string', label: 'Title' },
    text: { type: 'plaintext', label: 'Text' },
  },
}

function mountItems(props = {}, perms = {}) {
  return cy.mount(ItemsField, {
    props: { config: {}, assets: {}, ...props },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('Items', () => {
  it('renders expansion panels container', () => {
    mountItems({ modelValue: [{ title: 'First' }], config: itemConfig })
    cy.get('.v-expansion-panels').should('exist')
  })

  it('renders one panel per item', () => {
    mountItems({
      modelValue: [{ title: 'First' }, { title: 'Second' }],
      config: itemConfig,
    })
    cy.get('.v-expansion-panel').should('have.length', 2)
  })

  it('shows item title in panel header', () => {
    mountItems({
      modelValue: [{ title: 'My Item' }],
      config: itemConfig,
    })
    cy.contains('.element-title', 'My Item').should('exist')
  })

  it('falls back to other fields for title when title is empty', () => {
    mountItems({
      modelValue: [{ text: 'Some text content' }],
      config: itemConfig,
    })
    cy.contains('.element-title', 'Some text content').should('exist')
  })

  it('shows the "Add element" button when not readonly', () => {
    mountItems({ modelValue: [], config: {} })
    cy.get('button[title="Add element"]').should('exist')
  })

  it('hides "Add element" button in readonly mode', () => {
    mountItems({ modelValue: [], config: {}, readonly: true })
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('hides "Add element" when at config.max items', () => {
    mountItems({
      modelValue: [{ title: 'A' }, { title: 'B' }],
      config: { ...itemConfig, max: 2 },
    })
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('shows "Add element" when below config.max', () => {
    mountItems({
      modelValue: [{ title: 'A' }],
      config: { ...itemConfig, max: 3 },
    })
    cy.get('button[title="Add element"]').should('exist')
  })

  it('emits update:modelValue when add is clicked', () => {
    const onUpdate = cy.spy().as('update')
    mountItems({
      modelValue: [{ title: 'Existing' }],
      config: itemConfig,
      'onUpdate:modelValue': onUpdate,
    })
    cy.get('button[title="Add element"]').click()
    cy.get('@update').should('have.been.called')
  })

  it('emits error:true when items below default min of 1', () => {
    const onError = cy.spy().as('error')
    mountItems({ modelValue: [], config: {}, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when items meet default min of 1', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'Item' }],
      config: {},
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when items below config.min', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'One' }],
      config: { min: 3 },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when items meet config.min', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'A' }, { title: 'B' }, { title: 'C' }],
      config: { min: 3 },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when items exceed config.max', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'A' }, { title: 'B' }, { title: 'C' }],
      config: { max: 2 },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('uses config.default when modelValue is not an array', () => {
    const onUpdate = cy.spy().as('update')
    mountItems({
      modelValue: null,
      config: { default: [{ title: 'Default' }] },
      'onUpdate:modelValue': onUpdate,
    })
    cy.contains('.element-title', 'Default').should('exist')
  })

  it('shows action menu buttons on panels when not readonly', () => {
    mountItems({
      modelValue: [{ title: 'Item' }],
      config: itemConfig,
    })
    cy.get('.v-expansion-panel button[title="Actions"]').should('exist')
  })

  it('hides action menu buttons in readonly mode', () => {
    mountItems({
      modelValue: [{ title: 'Item' }],
      config: itemConfig,
      readonly: true,
    })
    cy.get('.v-expansion-panel button[title="Actions"]').should('not.exist')
  })

  it('renders field labels from config', () => {
    mountItems({
      modelValue: [{ title: 'Test' }],
      config: itemConfig,
    })
    cy.get('.v-expansion-panel-title').first().click()
    cy.contains('.label', 'Title').should('exist')
    cy.contains('.label', 'Text').should('exist')
  })
})
