import PageDetailItemSection from '../../../js/components/PageDetailItemSection.vue'
import { reactive } from 'vue'
import { useUserStore, useSchemaStore, useSideStore } from '../../../js/stores'

const stubs = {
  Fields: { template: '<div class="fields-stub" />' },
  SchemaItems: { template: '<div class="schema-items-stub" />' },
  VDialog: {
    template: '<div class="v-dialog" v-if="modelValue"><slot /></div>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}

const schemas = {
  meta: {
    seo: { fields: { description: { type: 'string', label: 'Description' } } },
  },
}

const item = {
  id: '1',
  meta: {
    seo: { type: 'seo', data: { description: 'Test' }, files: [] },
  },
}

function setupTranslations() {
  return {
    install(app) {
      app.config.globalProperties.$pgettext = (context, value) =>
        context === 'st' && value === 'seo' ? 'Suchmaschinenoptimierung' : value
    },
  }
}

function setupSchemaPlugin() {
  return {
    install() {
      const store = useSchemaStore()
      Object.assign(store, schemas)
    },
  }
}

function mountMeta(props = {}, perms = {}) {
  return cy.mount(PageDetailItemSection, {
    props: {
      item: { ...item, meta: { ...item.meta } },
      section: 'meta',
      assets: {},
      ...props,
    },
    global: {
      stubs,
      plugins: [setupSchemaPlugin(), setupTranslations()],
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('PageDetailItemSection (meta)', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountMeta()
    cy.get('.v-container').should('exist')
  })

  it('renders expansion panels for meta items', () => {
    mountMeta()
    cy.get('.v-expansion-panel').should('have.length', 1)
  })

  it('displays meta element type', () => {
    mountMeta()
    cy.get('.element-type').should('contain', 'Suchmaschinenoptimierung')
  })

  it('shows add button with page:save permission', () => {
    mountMeta({}, { 'page:save': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides add button without page:save permission', () => {
    mountMeta()
    cy.get('button.btn-add').should('not.exist')
  })

  it('shows remove button with page:save permission', () => {
    mountMeta({}, { 'page:save': true })
    cy.get('.v-expansion-panel').first().click()
    cy.get('button.btn-remove').should('exist')
  })

  it('renders Fields stub inside expansion panel', () => {
    mountMeta()
    cy.get('.v-expansion-panel').first().click()
    cy.get('.fields-stub').should('exist')
  })

  it('renders without meta items', () => {
    mountMeta({ item: { id: '1', meta: {} } })
    cy.get('.v-expansion-panel').should('not.exist')
  })

  it('hides add button if the section has no schemas', () => {
    mountMeta({ section: 'config', item: { id: '1', config: {} } }, { 'page:save': true })
    cy.get('.v-expansion-panel').should('not.exist')
    cy.get('button.btn-add').should('not.exist')
  })

  it('keeps the open panels of other entries and updates the counts when removing an entry', () => {
    const page = reactive({
      id: '1',
      meta: {
        seo: { type: 'seo', data: {}, files: [] },
        social: { type: 'social', data: {}, files: [] },
      },
    })

    mountMeta({ item: page, onPatch: (entries) => Object.assign(page, entries) }, { 'page:save': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailItemSection).vm
      vm.panel = ['seo', 'social']
      vm.remove('seo')

      expect(vm.panel).to.deep.equal(['social'])
      expect(useSideStore().store.type).to.deep.equal({ social: 1 })
    })
  })

  it('keeps the error state of the remaining entries when removing an entry', () => {
    const page = reactive({
      id: '1',
      meta: {
        seo: { type: 'seo', data: {}, files: [] },
        social: { type: 'social', data: {}, files: [] },
      },
    })
    const onError = cy.spy().as('error')
    const consoleError = cy.spy(console, 'error')

    mountMeta({ item: page, onError, onPatch: (entries) => Object.assign(page, entries) }, { 'page:save': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailItemSection).vm
      vm.error('social', true)
      vm.remove('seo')
      expect(onError.lastCall.args[0]).to.equal(true)

      vm.remove('social')
      expect(onError.lastCall.args[0]).to.equal(false)
    })

    // no deferred validation may run (and fail) after removing an entry
    cy.wait(50).then(() => {
      expect(onError.lastCall.args[0]).to.equal(false)
      expect(consoleError).not.to.have.been.calledWithMatch(/validate/)
    })
  })

  it('adds canonical meta items without a group', () => {
    const page = { id: '1', meta: {} }

    const onPatch = cy.spy()

    mountMeta({ item: page, onPatch }, { 'page:save': true }).then(({ wrapper }) => {
      wrapper.findComponent(PageDetailItemSection).vm.add({ type: 'seo' })

      expect(page.meta).to.deep.equal({})
      expect(onPatch.lastCall.args[0].meta.seo).to.deep.equal({
        type: 'seo',
        data: {},
        files: [],
      })
    })
  })
})
