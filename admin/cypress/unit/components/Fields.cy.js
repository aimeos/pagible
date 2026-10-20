import { h, ref, nextTick } from 'vue'
import Fields from '../../../js/components/Fields.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  String: { render() { return h('div', { class: 'field-string' }) } },
  Text: { render() { return h('input', { class: 'field-text' }) } },
  File: { props: ['label'], render() { return h('div', { class: 'field-file' }, this.label) } },
  Images: { props: ['label'], render() { return h('div', { class: 'field-images' }, this.label) } },
  Hidden: { render() { return h('div', { class: 'field-hidden' }) } },
  Number: { render() { return h('div', { class: 'field-number' }) } },
  Url: {
    props: ['rel'],
    emits: ['update:rel'],
    render() {
      return h('button', {
        class: 'field-url',
        onClick: () => this.$emit('update:rel', 'nofollow')
      }, this.rel || 'none')
    }
  },
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
        txLocaleItems: () => [],
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

  it('marks fields containing private files with an info border', () => {
    mountFields({
      fields: {
        public: { type: 'file', label: 'Public' },
        private: { type: 'file', label: 'Private' },
        gallery: { type: 'images', label: 'Gallery' },
      },
      data: {
        public: { id: 'public-file', type: 'file' },
        private: { id: 'private-file', type: 'file' },
        gallery: [
          { id: 'public-file', type: 'file' },
          { id: 'private-image', type: 'file' },
        ],
      },
      assets: {
        'public-file': { id: 'public-file', disk: 'public' },
        'private-file': { id: 'private-file', disk: 'private' },
        'private-image': { id: 'private-image', disk: 'private' },
      },
    })

    cy.get('.item.protected').should('have.length', 2)
    cy.get('.item.protected').first().should(($item) => {
      const style = getComputedStyle($item[0])
      const border = style.borderInlineStartColor.match(/\d+/g)?.slice(0, 3)
      const info = style.getPropertyValue('--v-theme-info').match(/\d+/g)?.slice(0, 3)

      expect(border).to.deep.equal(info)
    })
    cy.contains('.item:not(.protected)', 'Public').should('exist')
  })

  it('updates relocated files without duplicating their attachments', () => {
    const assets = {
      '1': { id: '1', disk: 'public' },
      '2': { id: '2', disk: 'public' },
    }
    const onUpdate = cy.spy()
    const onAssets = cy.spy()

    mountFields({
      fields: { gallery: { type: 'images', label: 'Gallery' } },
      data: {
        gallery: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      },
      files: ['1', '2'],
      assets,
      'onUpdate:files': onUpdate,
      onAddAssets: onAssets,
    }).then(({ wrapper }) => {
      wrapper.findComponent(Fields).vm.addFile([
        { id: '1', disk: 'private' },
        { id: '2', disk: 'private' },
      ])

      expect(onUpdate).to.have.been.calledOnceWith(['1', '2'])
      expect(onAssets).to.have.been.calledOnceWith({
        '1': { id: '1', disk: 'private' },
        '2': { id: '2', disk: 'private' },
      })
      // the assets prop is left to the owner
      expect(assets['1'].disk).to.equal('public')
      expect(assets['2'].disk).to.equal('public')
    })
  })

  it('renders a hint below fields not rendering it themselves', () => {
    mountFields({
      fields: {
        title: { type: 'string', label: 'Title', hint: 'Shown by the input' },
        body: { type: 'text', label: 'Body text', hint: 'Main content of the page' },
        secret: { type: 'hidden', hint: 'Never shown' },
      },
    })
    cy.get('.item .v-messages__message').should('have.length', 1)
      .and('contain', 'Main content of the page')
      .and('be.visible')
  })

  it('hides the label for hidden field type', () => {
    mountFields({
      fields: { secret: { type: 'hidden', label: 'Secret' } },
    })
    cy.get('.label').should('not.exist')
  })

  it('does not show translate button in readonly mode', () => {
    mountFields({ readonly: true })
    cy.get('.btn-translate button').should('not.exist')
  })

  it('shows translate button for text fields when not readonly', () => {
    mountFields({}, { 'text:translate': true })
    cy.get('.btn-translate button').should('exist')
  })

  it('shows generate text button with text:write permission', () => {
    mountFields({}, { 'text:write': true })
    cy.get('button.btn-generate').should('exist')
  })

  it('hides generate text button without text:write permission', () => {
    mountFields()
    cy.get('button.btn-generate').should('not.exist')
  })

  it('shows dictate button with audio:transcribe permission', () => {
    mountFields({}, { 'audio:transcribe': true })
    cy.get('button.btn-dictate').should('exist')
  })

  it('hides dictate button without audio:transcribe permission', () => {
    mountFields()
    cy.get('button.btn-dictate').should('not.exist')
  })

  it('adds error class when a field has an error', () => {
    mountFields()
    // Initially no error class
    cy.get('.item.error').should('not.exist')
  })

  function mountTyped(onError, fieldsRef, type, extra = {}) {
    const Host = {
      render: () => h(Fields, { fields: fieldsRef.value, data: { title: 'Hello', body: 'World' }, type: type.value, onError })
    }

    return cy.mount(Host, {
      global: {
        stubs: { ...stubs, ...extra },
        provide: {
          write: () => Promise.resolve(''),
          translate: () => Promise.resolve(['']),
          transcribe: () => Promise.resolve({ asText: () => '' }),
          txLocaleItems: () => [],
        },
      },
    }).then(({ wrapper }) => wrapper.findComponent(Fields).vm)
  }

  it('drops the errors and changes of fields not used by the new type', () => {
    const onError = cy.spy().as('error')
    const fieldsRef = ref(fields)
    const type = ref('heading')

    mountTyped(onError, fieldsRef, type).then((vm) => {
      vm.error('title', true)
      vm.update('title', 'Changed')
      expect(onError.lastCall.args[0]).to.equal(true)
      expect(vm.isDirty('title')).to.equal(true)

      fieldsRef.value = { body: fields.body }
      type.value = 'text'

      return nextTick().then(() => {
        expect(onError.lastCall.args[0]).to.equal(false)
        expect(vm.lastError).to.equal(false)
        expect(vm.errors).to.deep.equal({})
        expect(vm.isDirty('title')).to.equal(false)
        expect(vm.original).to.deep.equal({})
      })
    })
  })

  it('keeps the error of a field used unchanged by the new type', () => {
    const onError = cy.spy().as('error')
    const fieldsRef = ref(fields)
    const type = ref('heading')

    mountTyped(onError, fieldsRef, type).then((vm) => {
      vm.error('title', true)
      vm.update('title', 'Changed')
      const calls = onError.callCount

      fieldsRef.value = { title: fields.title }
      type.value = 'text'

      return nextTick().then(() => {
        expect(onError.callCount).to.equal(calls)
        expect(vm.lastError).to.equal(true)
        expect(vm.errors).to.deep.equal({ title: true })
        expect(vm.isDirty('title')).to.equal(false)
      })
    })
  })

  it('uses the state reported by fields remounted for the new type', () => {
    const onError = cy.spy().as('error')
    const fieldsRef = ref(fields)
    const type = ref('heading')
    const remounted = {
      emits: ['error'],
      created() { this.$emit('error', false) },
      render: () => h('div', { class: 'field-number' })
    }

    mountTyped(onError, fieldsRef, type, { Number: remounted }).then((vm) => {
      vm.error('title', true)

      fieldsRef.value = { title: { type: 'number', label: 'Title' } }
      type.value = 'text'

      return nextTick().then(() => {
        expect(onError.lastCall.args[0]).to.equal(false)
        expect(vm.lastError).to.equal(false)
        expect(vm.errors).to.deep.equal({ title: false })
      })
    })
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
          txLocaleItems: () => [],
        },
      },
    })
    cy.get('.item').should('exist')
  })

  it('immutably updates an items field when the data object is frozen', () => {
    const data = Object.freeze({
      cards: [{ position: 'start' }],
    })
    const onUpdate = cy.spy().as('updateData')

    mountFields({
      fields: { cards: { type: 'items', label: 'Footer cards' } },
      data,
      'onUpdate:data': onUpdate,
    }).then(({ wrapper }) => {
      wrapper.findComponent(Fields).vm.update('cards', [...data.cards, { position: 'end' }])

      expect(onUpdate).to.have.been.calledOnce
      expect(onUpdate.firstCall.args[0]).to.deep.equal({
        cards: [{ position: 'start' }, { position: 'end' }],
      })
      expect(onUpdate.firstCall.args[0]).not.to.equal(data)
      expect(data.cards).to.deep.equal([{ position: 'start' }])
    })
  })

  it('stores the selected relationship next to its URL', () => {
    const onUpdate = cy.spy().as('updateData')

    mountFields({
      fields: { link: { type: 'url', label: 'Link', rel: true } },
      data: { link: 'https://example.com', 'link-rel': '' },
      'onUpdate:data': onUpdate,
    })

    cy.get('.field-url').click()
    cy.get('@updateData').should('have.been.calledOnceWith', {
      link: 'https://example.com',
      'link-rel': 'nofollow',
    })
  })
})
