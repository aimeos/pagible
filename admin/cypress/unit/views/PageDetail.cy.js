import PageDetail from '../../../js/views/PageDetail.vue'
import { sections } from '../../../js/history'
import { useUserStore } from '../../../js/stores'
import '../../../js/assets/base.css'

const stubs = {
  AsideMeta: { template: '<div class="aside-meta-stub" />' },
  AsideCount: { template: '<div class="aside-count-stub" />' },
  HistoryDialog: { template: '<div class="history-dialog-stub" />' },
  PageDetailItem: { template: '<div class="page-detail-item-stub" />', methods: { reset() {} } },
  PageDetailEditor: { template: '<div class="page-detail-editor-stub" />' },
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
    props: { item: { ...baseItem, ...item } },
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
      expect(sections({ ...data, related_id: null, scheduled: 0, editor: 'Another editor' }, vm.historyCurrent().data)).to.deep.equal({})

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
      .and('have.css', 'background-color', 'rgba(0, 0, 0, 0.08)')
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

  it('shows translate button with text:translate permission', () => {
    mountDetail({ 'text:translate': true })
    cy.get('.btn-translate-page button').should('exist')
  })

  it('hides translate button without text:translate permission', () => {
    mountDetail({})
    cy.get('.btn-translate-page button').should('not.exist')
  })

  it('renders the history button', () => {
    mountDetail()
    cy.get('button.btn-history').should('exist')
  })

  it('invalidates page lists', () => {
    const evict = cy.stub()
    const gc = cy.stub()

    mountDetail({}, {}, {
      provider: { defaultClient: { cache: { evict, gc } } },
    }).then(() => {
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

  describe('update()', () => {
    it('sets changed flag for the given key', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.update('content', [])
        expect(vm.dirty.content).to.be.true
      })
    })

    it('assigns value to item when key is "page"', () => {
      mountDetail({}, { name: 'Old' }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.update('page', { name: 'New' })
        expect(vm.item.name).to.equal('New')
        expect(vm.dirty.page).to.be.true
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
        cy.get('.menu-save').should('have.class', 'bg-warning')
      })
    })

    it('uses primary color on save button when no conflicts', () => {
      mountDetail({ 'page:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.dirty = { page: true }
        cy.get('.menu-save').should('have.class', 'bg-primary')
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
})
