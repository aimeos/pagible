import { h, reactive } from 'vue'
import { apolloClient } from '../../../js/graphql'
import PageDetail from '../../../js/views/PageDetail.vue'
import { sections } from '../../../js/history'
import { useTranslationStore, useUserStore } from '../../../js/stores'
import '../../../js/assets/base.css'

const stubs = {
  AsideMeta: { template: '<div class="aside-meta-stub" />' },
  AsideCount: { template: '<div class="aside-count-stub" />' },
  FieldsAside: {
    props: { actions: Boolean, previewSize: String, saveCount: Number },
    emits: ['add-after', 'add-before', 'remove', 'update:previewSize'],
    render() {
      return h('div', { class: 'fields-aside-stub' }, [
        ...(this.actions ? [
          h('button', { class: 'btn-add-before', onClick: () => this.$emit('add-before') }),
          h('button', { class: 'btn-add-after', onClick: () => this.$emit('add-after') }),
          h('button', { class: 'btn-remove', onClick: () => this.$emit('remove') }),
        ] : []),
        h('button', { class: 'btn-responsive', onClick: () => this.$emit('update:previewSize', 'tablet') }),
      ])
    },
  },
  HistoryDialog: { template: '<div class="history-dialog-stub" />' },
  PageDetailItem: { template: '<div class="page-detail-item-stub" />', methods: { reset() {} } },
  PageDetailEditor: {
    props: { asideVisible: Boolean, previewSize: String },
    emits: ['change', 'edit'],
    methods: { addAfter() {}, addBefore() {}, reload() {}, remove() {} },
    render() {
      return h('button', {
        class: 'page-detail-editor-stub',
        'data-aside-visible': this.asideVisible,
        'data-preview-size': this.previewSize,
        onClick: () => this.$emit('change', 'content'),
      }, 'Close changed element')
    },
  },
  PageDetailContent: {
    template: '<div class="page-detail-content-stub" />',
    methods: { flush() {}, reset() {} },
  },
  PageDetailMetrics: { template: '<div class="page-detail-metrics-stub" />' },
}

const baseItem = {
  id: '1',
  name: 'Test Page',
  title: 'Test Title',
  path: '/test',
  lang: 'en',
  status: 1,
  cache: 5,
  domain: '',
  tag: '',
  to: '',
  type: '',
  theme: '',
  content: [],
  config: {},
  meta: {},
  published: false,
}

function mountDetail(perms = {}, item = {}, apollo = {}) {
  return cy.mount(PageDetail, {
    props: { item: reactive({ ...baseItem, ...item }) },
    global: {
      stubs,
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
      provide: {
        closeView: () => {},
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

describe('PageDetail', () => {
  it('matches saved page history and retains historical dependencies when applying selected content', () => {
    mountDetail().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
      const { id, published, ...data } = baseItem
      expect(sections({ ...data, scheduled: 0, editor: 'Another editor' }, vm.historyCurrent().data)).to.deep.equal({})

      const file = { id: 'old-file', path: 'old.jpg', previews: {} }
      const element = { id: 'old-element', type: 'text', name: 'Shared campaign', data: '{"text":"Saved element"}', files: [] }
      vm.apply({ content: [{ type: 'reference', refid: element.id }] }, { files: { [file.id]: file }, elements: [element] })
      expect(vm.elements[element.id].data.text).to.equal('Saved element')
      expect(vm.assets[file.id]).to.deep.equal(file)
      expect(vm.item.content[0].refid).to.equal(element.id)
      expect(vm.historyCurrent().elements.map(element => element.name)).to.deep.equal(['Shared campaign'])
    })
  })

  it('mounts history on demand and finishes saving without a reset method', () => {
    const latest = { id: 'saved-version', published: false, created_at: '2026-09-09T12:00:00Z' }
    mountDetail({ 'page:save': true }, {}, { mutate: () => Promise.resolve({ data: { savePage: { latest } } }) })
    cy.get('.history-dialog-stub').should('not.exist')
    cy.get('button.btn-history').click()
    cy.get('.history-dialog-stub').should('exist')
    cy.then(async () => {
      const wrapper = Cypress.vueWrapper.findComponent(PageDetail)
      const vm = wrapper.vm
      vm.item.title = 'Edited title'
      await wrapper.setData({ dirty: { page: true } })
      const mutate = cy.spy(vm.$apollo, 'mutate')
      const messages = cy.spy(vm.messages, 'add')
      expect(vm.hasChanged).to.equal(true)
      expect(await vm.save()).to.equal(true)
      expect(mutate).to.have.been.calledOnce
      expect(vm.hasChanged).to.equal(false)
      expect(vm.latest.id).to.equal(latest.id)
      expect(vm.item.updated_at).to.equal(latest.created_at)
      expect(messages).to.have.been.calledWith('Page saved successfully', 'success')
    })
  })

  it('renders the app bar', () => {
    mountDetail()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Page: <name>" in the title', () => {
    mountDetail({}, { name: 'About Us' })
    cy.get('.v-app-bar-title').should('contain', 'Page').and('contain', 'About Us')
  })

  it('renders the back button', () => {
    mountDetail()
    cy.get('button.btn-back').should('exist')
  })

  it('renders Editor, Content, and Page tabs', () => {
    mountDetail()
    cy.contains('.v-tab', 'Editor').should('exist')
    cy.contains('.v-tab', 'Content').should('exist')
    cy.contains('.v-tab', 'Page').should('exist')
  })

  it('uses the drawer active state for the selected detail tab', () => {
    mountDetail()
    cy.contains('.detail-tabs .v-tab', 'Editor')
      .should('have.class', 'v-tab--selected')
      .and('have.css', 'box-shadow')
      .and('include', 'inset')
    cy.get('.detail-tabs .v-tab__slider').should('not.exist')
  })

  it('shows Metrics tab when page:metrics permission is granted', () => {
    mountDetail({ 'page:metrics': true })
    cy.contains('.v-tab', 'Metrics').should('exist')
  })

  it('hides Metrics tab without page:metrics permission', () => {
    mountDetail({})
    cy.contains('.v-tab', 'Metrics').should('not.exist')
  })

  it('disables save button without page:save permission', () => {
    mountDetail({})
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables save button when nothing has changed', () => {
    mountDetail({ 'page:save': true })
    cy.get('.menu-save').should('be.disabled')
  })

  it('enables the save button when the editor reports an unsaved content change', () => {
    mountDetail({ 'page:save': true })
    cy.get('.menu-save').should('be.disabled')
    cy.get('.page-detail-editor-stub').click()
    cy.get('.menu-save').should('not.be.disabled')
  })

  it('shows the selected content element in the editor sidebar', () => {
    const element = { id: 'element-1', type: 'heading', data: { title: 'Selected' } }

    mountDetail().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
      vm.editElement(element)

      expect(vm.editorElement).to.equal(element)
      expect(vm.aside).to.equal('editor')
      expect(vm.drawer.aside).to.equal(true)
    })

    cy.get('.fields-aside-stub').should('exist')
    cy.get('.page-detail-editor-stub').should('have.attr', 'data-aside-visible', 'true')
    cy.contains('.detail-tabs .v-tab', 'Content').click()
    cy.get('.fields-aside-stub').should('not.exist')
    cy.get('.page-detail-editor-stub').should('have.attr', 'data-aside-visible', 'false')
    cy.get('.aside-count-stub').should('exist')
    cy.contains('.detail-tabs .v-tab', 'Editor').click()
    cy.get('.fields-aside-stub').should('exist')
  })

  it('routes content sidebar actions to the preview editor', () => {
    const element = { id: 'element-1', type: 'heading', data: { title: 'Selected' } }

    mountDetail({ 'page:save': true }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
      cy.spy(vm.$refs.editor, 'addBefore').as('addBefore')
      cy.spy(vm.$refs.editor, 'addAfter').as('addAfter')
      cy.spy(vm.$refs.editor, 'remove').as('remove')
      vm.editElement(element, true)
      expect(vm.editorActions).to.equal(true)
    })

    cy.get('.fields-aside-stub .btn-add-before').click()
    cy.get('.fields-aside-stub .btn-add-after').click()
    cy.get('.fields-aside-stub .btn-remove').click()
    cy.get('@addBefore').should('have.been.calledOnce')
    cy.get('@addAfter').should('have.been.calledOnce')
    cy.get('@remove').should('have.been.calledOnce')
  })

  it('routes responsive sizes from the sidebar to the preview editor', () => {
    const element = { id: 'element-1', type: 'heading', data: { title: 'Selected' } }

    mountDetail().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
      vm.editElement(element)
      expect(vm.previewSize).to.equal('computer')
    })

    cy.get('.fields-aside-stub .btn-responsive').click()
    cy.get('.page-detail-editor-stub').should('have.attr', 'data-preview-size', 'tablet')
    cy.then(() => expect(Cypress.vueWrapper.findComponent(PageDetail).vm.previewSize).to.equal('tablet'))
  })

  it('waits for pending content updates before flushing on save', () => {
    mountDetail()
    cy.contains('.v-tab', 'Content').click()
    cy.contains('.v-tab', 'Editor').click()

    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
      const flush = cy.spy(vm.$refs.content, 'flush')
      const saving = vm.save()

      expect(flush).not.to.have.been.called

      return saving.then(() => {
        expect(flush).to.have.been.calledOnce
      })
    })
  })

  it('disables publish button without page:publish permission', () => {
    mountDetail({})
    cy.get('.menu-publish').first().should('be.disabled')
  })

  it('shows the translate button for variants with text:translate permission', () => {
    mountDetail({ 'page:save': true, 'text:translate': true }, { lang: 'de', source: 'en' })
    cy.get('button.btn-translate-page').should('exist')
  })

  it('hides the translate button without text:translate permission', () => {
    mountDetail({ 'page:save': true }, { lang: 'de', source: 'en' })
    cy.get('button.btn-translate-page').should('not.exist')
  })

  it('hides the translate button for source variants', () => {
    mountDetail({ 'page:save': true, 'text:translate': true }, { lang: 'en', source: 'en' })
    cy.get('button.btn-translate-page').should('not.exist')
  })

  it('renders the history button', () => {
    mountDetail()
    cy.get('button.btn-history').should('exist')
  })

  it('invalidates page lists', () => {
    const evict = cy.stub(apolloClient.cache, 'evict')
    const gc = cy.stub(apolloClient.cache, 'gc')

    mountDetail().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
      vm.invalidate()

      expect(evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'pages' })
      expect(gc).to.have.been.calledOnce
    })
  })

  describe('computed properties', () => {
    it('hasChanged is false by default', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        expect(vm.hasChanged).to.be.false
      })
    })

    it('hasError is false by default', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        expect(vm.hasError).to.be.false
      })
    })

    it('hasChanged is true when changed has a truthy entry', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.dirty = { content: true }
        expect(vm.hasChanged).to.be.true
      })
    })

    it('hasError is true when errors has a truthy entry', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.errors = { page: true }
        expect(vm.hasError).to.be.true
      })
    })
  })

  describe('clean()', () => {
    it('keeps the relationship belonging to a URL field', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.schemas.content = {
          hero: { fields: { title: { type: 'string' }, url: { type: 'url', rel: true } } }
        }

        const result = vm.clean([{
          type: 'hero',
          data: {
            title: 'Example',
            url: 'https://example.com',
            'url-rel': 'nofollow',
            obsolete: true,
          },
        }], 'content')

        expect(result[0].data).to.deep.equal({
          title: 'Example',
          url: 'https://example.com',
          'url-rel': 'nofollow',
        })

        vm.schemas.content.hero.fields.url.rel = false
        expect(vm.clean(result, 'content')[0].data).to.deep.equal({
          title: 'Example',
          url: 'https://example.com',
        })
      })
    })
  })

  describe('fileIds()', () => {
    it('returns empty array when content/meta/config have no files', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        expect(vm.fileIds()).to.deep.equal([])
      })
    })

    it('collects file IDs from content, meta, and config', () => {
      const item = {
        content: [
          { files: ['f1', 'f2'] },
          { files: ['f3'] },
        ],
        meta: { seo: { files: ['f4'] } },
        config: { theme: { files: ['f5'] } },
      }
      mountDetail({}, item).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const ids = vm.fileIds()
        expect(ids).to.include('f1')
        expect(ids).to.include('f4')
        expect(ids).to.include('f5')
      })
    })

    it('deduplicates file IDs', () => {
      const item = {
        content: [{ files: ['f1', 'f2'] }, { files: ['f1'] }],
      }
      mountDetail({}, item).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const ids = vm.fileIds()
        expect(ids.filter(id => id === 'f1')).to.have.length(1)
      })
    })
  })

  describe('files()', () => {
    it('keeps shared-element files authoritative when an ID is also directly attached', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const shared = Object.freeze({ id: 'f1', name: 'shared.jpg', previews: { 100: 'shared-thumb.jpg' } })
        const result = vm.files([{ id: 'f1', name: 'direct.jpg' }, { id: 'f2', name: 'other.jpg' }], {
          element: { files: [shared] }
        })
        expect(result.f1).to.equal(shared)
        expect(result.f2.name).to.equal('other.jpg')
      })
    })

    it('uses latest file data with published fields as fallback', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const result = vm.files([
          {
            disk: 'private',
            id: 'f1',
            name: 'published.jpg',
            path: 'published.jpg',
            previews: '{"100":"published-thumb.jpg"}',
            description: '{}',
            transcription: '{}',
            latest: {
              data: '{"name":"draft.jpg","path":"draft.jpg","previews":{"100":"draft-thumb.jpg"}}',
              aux: '{"description":{"en":"Draft"}}',
            },
          },
        ])
        expect(result.f1.disk).to.equal('private')
        expect(result.f1.name).to.equal('draft.jpg')
        expect(result.f1.path).to.equal('draft.jpg')
        expect(result.f1.previews).to.deep.equal({ 100: 'draft-thumb.jpg' })
        expect(result.f1.description).to.deep.equal({ en: 'Draft' })
      })
    })
  })

  describe('obsolete()', () => {
    it('removes file IDs not present in assets', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.assets = { f1: {} }
        const content = [{ files: ['f1', 'f2', 'f3'] }]
        const result = vm.obsolete(content)
        expect(result[0].files).to.deep.equal(['f1'])
      })
    })
  })

  describe('publish menu', () => {
    it('renders one publish menu with both actions', () => {
      mountDetail({ 'page:publish': true })
      cy.get('.menu-publish').should('have.length', 1).click()
      cy.get('.menu-publish-now').should('contain', 'Publish')
      cy.get('.menu-schedule-at').should('contain', 'Schedule')
    })

    it('opens menu with date and time pickers', () => {
      mountDetail({ 'page:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.v-date-picker').should('exist')
      cy.get('.v-time-picker').should('exist')
    })

    it('keeps date and time pickers at equal height on desktop', () => {
      cy.viewport(1000, 800)
      mountDetail({ 'page:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.v-date-picker').then($date => {
        cy.get('.v-time-picker').should($time => {
          expect($time[0].getBoundingClientRect().height).to.equal($date[0].getBoundingClientRect().height)
        })
      })
    })

    it('disables schedule action when no date selected', () => {
      mountDetail({ 'page:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.menu-schedule-at').should('be.disabled')
    })

    it('schedule() combines date and time', () => {
      mountDetail({ 'page:publish': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.publishAt = new Date(2026, 5, 15)
        vm.publishTime = '14:30'
        cy.spy(vm, 'publish').as('publishSpy')
        vm.schedule()
        cy.get('@publishSpy').should('have.been.calledOnce').then(() => {
          const arg = vm.publish.args[0][0]
          expect(arg.getFullYear()).to.equal(2026)
          expect(arg.getMonth()).to.equal(5)
          expect(arg.getDate()).to.equal(15)
          expect(arg.getHours()).to.equal(14)
          expect(arg.getMinutes()).to.equal(30)
        })
      })
    })

    it('schedule() uses midnight when no time selected', () => {
      mountDetail({ 'page:publish': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.publishAt = new Date(2026, 5, 15)
        vm.publishTime = null
        cy.spy(vm, 'publish').as('publishSpy')
        vm.schedule()
        cy.get('@publishSpy').should('have.been.calledOnce').then(() => {
          const arg = vm.publish.args[0][0]
          expect(arg.getHours()).to.equal(0)
          expect(arg.getMinutes()).to.equal(0)
        })
      })
    })
  })

  describe('conflict UI', () => {
    it('hides changes button when changed is null', () => {
      mountDetail()
      cy.get('.menu-changed').should('not.exist')
    })

    it('shows changes button when changed is set', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('shows changes button even when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c', resolved: 'c' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('uses warning color on save button when hasConflict is true', () => {
      mountDetail({ 'page:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c' } } }
        vm.dirty = { page: true }
        cy.get('.menu-save').should('have.class', 'text-warning')
      })
    })

    it('uses primary color on save button when no conflicts', () => {
      mountDetail({ 'page:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.dirty = { page: true }
        cy.get('.menu-save').should('have.class', 'text-primary')
      })
    })

    it('hasConflict is false when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c', resolved: 'c' } } }
        expect(vm.hasConflict).to.be.false
      })
    })

    it('hasConflict is true when overwritten exists without resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c' } } }
        expect(vm.hasConflict).to.be.true
      })
    })
  })

  describe('language variants', () => {
    function pageData(lang, variants) {
      return {
        data: {
          page: {
            id: '1',
            lang,
            source: 'en',
            stale: false,
            has: 0,
            restricted: false,
            variants,
            latest: {
              id: 'v-' + lang,
              published: false,
              publish_at: null,
              data: JSON.stringify({ ...baseItem, lang }),
              aux: JSON.stringify({ content: [], meta: {}, config: {} }),
              editor: 'test@test.com',
              created_at: '2026-01-01 00:00:00',
              files: [],
              elements: [],
            },
          },
        },
      }
    }

    const variants = [
      { id: '1', lang: 'en', source: true, state: 'current', published: true },
      { id: '1', lang: 'de', source: false, state: 'stale', published: false },
    ]

    it('lists all site languages with the state of their variants', () => {
      const query = cy.stub().resolves(pageData('en', variants))

      mountDetail({ 'page:view': true }, {}, { query }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(PageDetail).vm
        vm.languages.available = ['en', 'de', 'fr']

        cy.wrap(null).should(() => {
          expect(vm.langs.map(({ code, state, source }) => ({ code, state, source }))).to.deep.equal([
            { code: 'en', state: 'current', source: true },
            { code: 'de', state: 'stale', source: false },
            { code: 'fr', state: 'missing', source: false },
          ])
        })
      })
    })

    it('loads the variant in the language and saves it in that language', () => {
      const query = cy.stub()
      query.onFirstCall().resolves(pageData('en', variants))
      query.resolves(pageData('de', variants))
      const mutate = cy.stub().resolves({ data: { savePage: { id: '1', latest: { id: 'v2', published: false, data: '{}', created_at: '2026-01-01 00:00:00' } } } })

      mountDetail({ 'page:view': true, 'page:save': true }, {}, { query, mutate }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(PageDetail).vm
        vm.languages.available = ['en', 'de']

        cy.wrap(null).should(() => expect(vm.item.variants).to.have.length(2))
        cy.then(() => vm.switchLang({ code: 'de', state: 'stale' }))
        cy.then(() => {
          expect(query.lastCall.args[0].variables).to.deep.include({ id: '1', lang: 'de' })
          expect(vm.variantLang).to.equal('de')
          expect(mutate).not.to.have.been.called

          vm.dirty = { page: true }
          return vm.save(true)
        })
        cy.then(() => {
          const call = mutate.getCalls().find((c) => c.args[0].variables.input)
          expect(call.args[0].variables.lang).to.equal('de')
        })
      })
    })

    it('creates a missing variant before switching to it', () => {
      const query = cy.stub()
      query.onFirstCall().resolves(pageData('en', variants))
      query.resolves(pageData('fr', [...variants, { id: '1', lang: 'fr', source: false, state: 'current', published: false }]))
      const mutate = cy.stub().resolves({ data: { addVariant: { id: '1', lang: 'fr' } } })

      mountDetail({ 'page:view': true, 'page:add': true }, {}, { query, mutate }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(PageDetail).vm

        cy.wrap(null).should(() => expect(vm.item.variants).to.have.length(2))
        cy.then(() => vm.switchLang({ code: 'fr', state: 'missing' }))
        cy.then(() => {
          expect(mutate).to.have.been.calledOnce
          expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: '1', lang: 'fr' })
          expect(query.lastCall.args[0].variables.lang).to.equal('fr')
          expect(vm.variantLang).to.equal('fr')
        })
      })
    })

    it('does not create a missing variant without page:add permission', () => {
      const query = cy.stub().resolves(pageData('en', variants))
      const mutate = cy.stub()

      mountDetail({ 'page:view': true }, {}, { query, mutate }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(PageDetail).vm

        cy.wrap(null).should(() => expect(vm.item.variants).to.have.length(2))
        cy.then(() => vm.switchLang({ code: 'fr', state: 'missing' }))
        cy.then(() => {
          expect(mutate).not.to.have.been.called
          expect(vm.variantLang).to.equal('en')
        })
      })
    })

    it('loads the history of the edited variant', () => {
      const query = cy.stub()
      query.onFirstCall().resolves(pageData('de', variants))
      query.resolves({ data: { page: { id: '1', versions: [] } } })

      mountDetail({ 'page:view': true }, {}, { query }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(PageDetail).vm

        cy.wrap(null).should(() => expect(vm.variantLang).to.equal('de'))
        cy.then(() => vm.versions('1'))
        cy.then(() => {
          expect(query.lastCall.args[0].variables).to.deep.equal({ id: '1', lang: 'de' })
        })
      })
    })

    describe('translate', () => {
      // stops polling the translation batches of the test
      afterEach(() => useTranslationStore().clear())

      const content = [
        { id: 'el1', type: 'heading', group: 'main', data: { title: 'Alt' } },
        { id: 'el2', type: 'text', group: 'main', data: { text: 'Text' } },
      ]

      function variant() {
        const page = pageData('de', variants).data.page
        page.stale = true
        page.latest.data = JSON.stringify({ ...baseItem, lang: 'de', source: 'en', title: 'Alt' })
        page.latest.aux = JSON.stringify({ content, meta: {}, config: {} })
        return page
      }

      function mountVariant(perms, apollo) {
        const query = apollo.query || cy.stub().resolves({ data: { page: variant() } })
        return mountDetail({ 'page:view': true, ...perms }, {}, { ...apollo, query }).then(({ wrapper }) => {
          const vm = wrapper.findComponent(PageDetail).vm
          cy.wrap(null).should(() => expect(vm.item.lang).to.equal('de'))
          return cy.wrap(vm)
        })
      }

      function progressQuery(progress) {
        return cy.stub().callsFake(({ variables }) => Promise.resolve(variables.batch
          ? { data: { translateProgress: progress } }
          : { data: { page: variant() } }
        ))
      }

      it('translates the variant into a new draft and reloads it', () => {
        const mutate = cy.stub().resolves({ data: { translatePage: { id: 'batch-1', total: 1 } } })
        const query = progressQuery({ total: 1, done: 1, failed: 0 })

        mountVariant({ 'page:save': true, 'text:translate': true }, { mutate, query }).then((vm) => {
          const add = cy.spy(vm.messages, 'add')
          const refresh = cy.spy(vm, 'refresh')

          return vm.translate().then(() => {
            expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['1'], lang: ['de'] })
            expect(query).to.have.been.calledWithMatch({ variables: { batch: 'batch-1' }, fetchPolicy: 'no-cache' })
            expect(add).to.have.been.calledWithMatch(/Translation saved as draft/, 'success')
            expect(refresh).to.have.been.calledOnce
            expect(vm.translating).to.equal(false)
          })
        })
      })

      it('reports that nothing needs to be translated', () => {
        const mutate = cy.stub().resolves({ data: { translatePage: { id: 'batch-1', total: 0 } } })
        const query = progressQuery({ total: 0, done: 0, failed: 0 })

        mountVariant({ 'page:save': true, 'text:translate': true }, { mutate, query }).then((vm) => {
          const add = cy.spy(vm.messages, 'add')
          const refresh = cy.spy(vm, 'refresh')

          return vm.translate().then(() => {
            expect(query).not.to.have.been.calledWithMatch({ variables: { batch: 'batch-1' } })
            expect(add).to.have.been.calledWithMatch(/Nothing to translate/, 'info')
            expect(refresh).not.to.have.been.called
            expect(vm.translating).to.equal(false)
          })
        })
      })

      it('shows an error if the translation failed', () => {
        const mutate = cy.stub().resolves({ data: { translatePage: { id: 'batch-1', total: 1 } } })
        const query = progressQuery({ total: 1, done: 0, failed: 1 })

        mountVariant({ 'page:save': true, 'text:translate': true }, { mutate, query }).then((vm) => {
          const add = cy.spy(vm.messages, 'add')
          const refresh = cy.spy(vm, 'refresh')

          return vm.translate().then(() => {
            expect(add).to.have.been.calledWithMatch(/1 translation failed/, 'error')
            expect(refresh).not.to.have.been.called
            expect(vm.isTranslating).to.equal(false)
          })
        })
      })

      it('shows an error if the translation could not be queued', () => {
        const mutate = cy.stub().rejects(new Error('queue failed'))

        mountVariant({ 'page:save': true, 'text:translate': true }, { mutate }).then((vm) => {
          const error = cy.stub(vm.messages, 'error')

          return vm.translate().then(() => {
            expect(error).to.have.been.calledWithMatch(/Error translating page/)
            expect(vm.isTranslating).to.equal(false)
          })
        })
      })

      it('shows the variant as translating while the batch is queued', () => {
        const mutate = cy.stub().resolves({ data: { translatePage: { id: 'batch-1', total: 1 } } })
        const query = progressQuery({ total: 1, done: 0, failed: 0 })

        mountVariant({ 'page:save': true, 'text:translate': true }, { mutate, query }).then((vm) => {
          vm.translate()
          // the store keeps the state after the mutation returned
          cy.wrap(vm).its('translating').should('equal', false)
          cy.wrap(vm).its('isTranslating').should('equal', true)
          cy.get('button.btn-translate-page').should('have.class', 'v-btn--loading')
        })
      })

      it('keeps unsaved changes when the translation finishes', () => {
        mountVariant({ 'page:save': true }, {}).then((vm) => {
          const refresh = cy.stub(vm, 'refresh').resolves()
          const progress = { total: 1, done: 1, failed: 0 }

          // changes made while the translation was running
          vm.dirty = { page: true }
          vm.translated({ ids: ['1'], langs: ['de'] }, progress)
          expect(refresh).not.to.have.been.called

          // translations of other pages or languages are ignored
          vm.dirty = {}
          vm.translated({ ids: ['2'], langs: ['de'] }, progress)
          vm.translated({ ids: ['1'], langs: ['fr'] }, progress)
          expect(refresh).not.to.have.been.called

          vm.translated({ ids: ['1'], langs: ['de'] }, progress)
          expect(refresh).to.have.been.calledOnce
        })
      })

      it('marks the variant as up to date without changing it', () => {
        const mutate = cy.stub().resolves({ data: { ignoreChanges: [{ id: '1', stale: false }] } })

        mountVariant({ 'page:save': true }, { mutate }).then((vm) => {
          cy.get('button.btn-ignore-changes').should('exist')

          return cy.then(() => vm.ignoreChanges()).then(() => {
            expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['1'], lang: 'de' })
            expect(vm.item.stale).to.equal(false)
            expect(vm.item.variants.find((v) => v.lang === 'de').state).to.equal('current')
          })
        })
        cy.get('button.btn-ignore-changes').should('not.exist')
      })

      it('marks the variant as outdated when saving a restored version', () => {
        const latest = { id: 'v3', published: false, created_at: '2026-01-01 00:00:00' }
        const mutate = cy.stub().resolves({ data: { savePage: { id: '1', stale: true, latest } } })

        mountVariant({ 'page:save': true }, { mutate }).then((vm) => {
          vm.item.stale = false
          vm.item.variants = vm.item.variants.map((v) => (v.lang === 'de' ? { ...v, state: 'current' } : v))
          vm.use({ data: { title: 'Older' }, elements: [], files: {} })
          expect(vm.restored).to.equal(true)

          return vm.save().then(() => {
            const call = mutate.getCalls().find((c) => c.args[0].variables.input)
            expect(call.args[0].variables).to.deep.include({ lang: 'de', restore: true })
            expect(vm.restored).to.equal(false)
            expect(vm.item.stale).to.equal(true)
            expect(vm.item.variants.find((v) => v.lang === 'de').state).to.equal('stale')
          })
        })
      })

      it('does not restore when discarding unsaved changes', () => {
        mountVariant({ 'page:save': true }, {}).then((vm) => {
          vm.use({ data: { title: 'Alt' }, elements: [], files: {} }, true)
          expect(vm.restored).to.equal(false)
          vm.apply({ title: 'Older' })
          expect(vm.restored).to.equal(true)
        })
      })
    })
  })
})
