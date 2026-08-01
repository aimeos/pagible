import { h } from 'vue'
import ItemsField from '../../../js/fields/Items.vue'
import { useUserStore, useClipboardStore } from '../../../js/stores'

const itemConfig = {
  item: {
    title: { type: 'string', label: 'Title' },
    text: { type: 'plaintext', label: 'Text' }
  }
}

const identityConfig = {
  identity: 'id',
  item: {
    title: { type: 'string', label: 'Title' },
    prices: {
      type: 'items',
      identity: 'id',
      item: {
        amount: { type: 'string', label: 'Amount' }
      }
    }
  }
}

function mountItems(props = {}, perms = {}, stubs = {}) {
  return cy
    .mount(ItemsField, {
      props: { config: {}, assets: {}, ...props },
      global: { stubs }
    })
    .then((mounted) => {
      const user = useUserStore()
      user.me = { permission: perms }
      return mounted
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
      config: itemConfig
    })
    cy.get('.v-expansion-panel').should('have.length', 2)
  })

  it('shows item title in panel header', () => {
    mountItems({
      modelValue: [{ title: 'My Item' }],
      config: itemConfig
    })
    cy.contains('.element-title', 'My Item').should('exist')
  })

  it('falls back to other fields for title when title is empty', () => {
    mountItems({
      modelValue: [{ text: 'Some text content' }],
      config: itemConfig
    })
    cy.contains('.element-title', 'Some text content').should('exist')
  })

  it('shows the "Add element" button when not readonly', () => {
    mountItems({ modelValue: [], config: {} })
    cy.get('button.btn-add').should('exist')
  })

  it('hides "Add element" button in readonly mode', () => {
    mountItems({ modelValue: [], config: {}, readonly: true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('hides "Add element" when at config.max items', () => {
    mountItems({
      modelValue: [{ title: 'A' }, { title: 'B' }],
      config: { ...itemConfig, max: 2 }
    })
    cy.get('button.btn-add').should('not.exist')
  })

  it('shows "Add element" when below config.max', () => {
    mountItems({
      modelValue: [{ title: 'A' }],
      config: { ...itemConfig, max: 3 }
    })
    cy.get('button.btn-add').should('exist')
  })

  it('emits update:modelValue when add is clicked', () => {
    const onUpdate = cy.spy().as('update')
    mountItems({
      modelValue: [{ title: 'Existing' }],
      config: itemConfig,
      'onUpdate:modelValue': onUpdate
    })
    cy.get('button.btn-add').click()
    cy.get('@update').should('have.been.called')
  })

  it('generates an identity when an item is added', () => {
    const onUpdate = cy.spy().as('update')
    mountItems({
      modelValue: [],
      config: identityConfig,
      'onUpdate:modelValue': onUpdate
    })
    cy.get('button.btn-add').click()
    cy.get('@update').should((spy) => {
      expect(spy.lastCall.args[0][0].id).to.match(/^[A-Za-z][A-Za-z0-9_-]{5}$/)
    })
  })

  it('generates missing nested identities', () => {
    mountItems({
      modelValue: [{ title: 'First', prices: [{ amount: '10.00' }] }],
      config: identityConfig
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ItemsField).vm
      expect(vm.items[0].id).to.match(/^[A-Za-z][A-Za-z0-9_-]{5}$/)
      expect(vm.items[0].prices[0].id).to.match(/^[A-Za-z][A-Za-z0-9_-]{5}$/)
    })
  })

  it('regenerates nested identities on copy and consumes them on paste', () => {
    const original = {
      id: 'package-1',
      title: 'First',
      prices: [{ id: 'price-1', amount: '10.00' }]
    }

    mountItems({
      modelValue: [original],
      config: identityConfig
    }).then(({ wrapper }) => {
      const clipboard = useClipboardStore()
      const vm = wrapper.findComponent(ItemsField).vm
      vm.copy(0)

      const copied = clipboard.get('items-content')
      expect(copied.id).not.to.equal(original.id)
      expect(copied.prices[0].id).not.to.equal(original.prices[0].id)

      vm.paste()

      expect(vm.items[1]).to.deep.equal(copied)
      expect(clipboard.get('items-content')).to.equal(null)
    })
  })

  it('preserves identities when an item is cut and pasted', () => {
    const original = {
      id: 'package-1',
      title: 'First',
      prices: [{ id: 'price-1', amount: '10.00' }]
    }

    mountItems({
      modelValue: [original],
      config: identityConfig
    }).then(({ wrapper }) => {
      const clipboard = useClipboardStore()
      const vm = wrapper.findComponent(ItemsField).vm
      vm.cut(0)
      vm.paste()

      expect(vm.items[0].id).to.equal('package-1')
      expect(vm.items[0].prices[0].id).to.equal('price-1')
      expect(clipboard.get('items-content')).to.equal(null)
    })
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
      onError
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when items below config.min', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'One' }],
      config: { min: 3 },
      onError
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when items meet config.min', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'A' }, { title: 'B' }, { title: 'C' }],
      config: { min: 3 },
      onError
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when items exceed config.max', () => {
    const onError = cy.spy().as('error')
    mountItems({
      modelValue: [{ title: 'A' }, { title: 'B' }, { title: 'C' }],
      config: { max: 2 },
      onError
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('uses config.default when modelValue is not an array', () => {
    const onUpdate = cy.spy().as('update')
    mountItems({
      modelValue: null,
      config: { default: [{ title: 'Default' }] },
      'onUpdate:modelValue': onUpdate
    })
    cy.contains('.element-title', 'Default').should('exist')
  })

  it('shows action menu buttons on panels when not readonly', () => {
    mountItems({
      modelValue: [{ title: 'Item' }],
      config: itemConfig
    })
    cy.get('.v-expansion-panel .btn-actions button').should('exist')
  })

  it('hides action menu buttons in readonly mode', () => {
    mountItems({
      modelValue: [{ title: 'Item' }],
      config: itemConfig,
      readonly: true
    })
    cy.get('.v-expansion-panel .btn-actions button').should('not.exist')
  })

  it('renders field labels from config', () => {
    mountItems({
      modelValue: [{ title: 'Test' }],
      config: itemConfig
    })
    cy.get('.v-expansion-panel-title').first().click()
    cy.contains('.label', 'Title').should('exist')
    cy.contains('.label', 'Text').should('exist')
  })

  it('forwards batched file updates from nested fields', () => {
    const onAddFile = cy.spy()
    const Images = {
      emits: ['addFile'],
      render() {
        return h('button', {
          class: 'field-images',
          onClick: () => this.$emit('addFile', [{ id: '1' }, { id: '2' }])
        })
      }
    }

    mountItems({
      modelValue: [{ gallery: [] }],
      config: { item: { gallery: { type: 'images' } } },
      onAddFile
    }, {}, { Images })

    cy.get('.v-expansion-panel-title').click()
    cy.get('.field-images').click()
    cy.wrap(onAddFile).should('have.been.calledOnceWith', [{ id: '1' }, { id: '2' }])
  })
})
