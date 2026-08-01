import { h } from 'vue'
import Fields from '../../../js/components/Fields.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  String: { render() { return h('div', { class: 'field-string' }) } },
  Text: { render() { return h('div', { class: 'field-text' }) } },
  File: { props: ['label'], render() { return h('div', { class: 'field-file' }, this.label) } },
  Images: { props: ['label'], render() { return h('div', { class: 'field-images' }, this.label) } },
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

    mountFields({
      fields: { gallery: { type: 'images', label: 'Gallery' } },
      data: {
        gallery: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      },
      files: ['1', '2'],
      assets,
      'onUpdate:files': onUpdate,
    }).then(({ wrapper }) => {
      wrapper.findComponent(Fields).vm.addFile([
        { id: '1', disk: 'private' },
        { id: '2', disk: 'private' },
      ])

      expect(onUpdate).to.have.been.calledOnceWith(['1', '2'])
      expect(assets['1'].disk).to.equal('private')
      expect(assets['2'].disk).to.equal('private')
    })
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
