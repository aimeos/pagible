import FileDetail from '../../../js/views/FileDetail.vue'
import { apolloClient } from '../../../js/graphql'
import { sections } from '../../../js/history'
import { useUserStore, useMessageStore } from '../../../js/stores'
import '../../../js/assets/base.css'

const stubs = {
  AsideMeta: { template: '<div class="aside-meta-stub" />' },
  HistoryDialog: { template: '<div class="history-dialog-stub" />' },
  FileDetailItem: { template: '<div class="file-detail-item-stub" />' },
  DetailRefs: { template: '<div class="file-detail-refs-stub" />' },
}

const baseItem = {
  id: '1',
  name: 'photo.jpg',
  path: 'images/photo.jpg',
  mime: 'image/jpeg',
  lang: 'en',
  previews: {},
  description: {},
  transcription: {},
  published: false,
}

function mountDetail(perms = {}, item = {}, apollo = {}) {
  return cy.mount(FileDetail, {
    props: { initial: { ...baseItem, ...item } },
    global: {
      stubs,
      provide: {
        closeView: () => {},
      },
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
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

describe('FileDetail', () => {
  it('matches the saved file history shape without attribution or preview noise', () => {
    mountDetail().then(() => {
      const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
      const { id, published, ...data } = baseItem
      expect(sections({ ...data, scheduled: 0, editor: 'Another editor' }, vm.historyCurrent().data)).to.deep.equal({})
    })
  })

  it('compares the history with the state of the time the dialog was opened', () => {
    mountDetail().then(async () => {
      const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
      vm.vhistory = true
      await vm.$nextTick()

      vm.itemUpdated({ name: 'renamed.jpg' })
      expect(vm.historyData.data.name).to.equal('photo.jpg')
    })
  })

  it('renders the app bar', () => {
    mountDetail()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "File: <name>" in the title', () => {
    mountDetail({}, { name: 'logo.png' })
    cy.get('.v-app-bar-title').should('contain', 'File').and('contain', 'logo.png')
  })

  it('renders the back button', () => {
    mountDetail()
    cy.get('button.btn-back').should('exist')
  })

  it('renders the File and Used by tabs', () => {
    mountDetail()
    cy.contains('.v-tab', 'File').should('exist')
    cy.contains('.v-tab', 'Used by').should('exist')
  })

  it('shows the File tab as active by default', () => {
    mountDetail()
    cy.contains('.detail-tabs .v-tab', 'File')
      .should('have.class', 'v-tab--selected')
      .and('have.css', 'box-shadow')
      .and('include', 'inset')
    cy.get('.detail-tabs .v-tab__slider').should('not.exist')
  })

  it('renders the FileDetailItem stub', () => {
    mountDetail()
    cy.get('.file-detail-item-stub').should('exist')
  })

  it('renders the AsideMeta stub', () => {
    mountDetail()
    cy.get('.aside-meta-stub').should('exist')
  })

  it('keeps the private disk when loading a file', () => {
    const query = cy.stub().resolves({
      data: {
        file: {
          disk: 'private',
          id: '1',
          latest: {
            id: 'version-1',
            data: '{"name":"private.pdf","path":"cms/file-1/private.pdf","previews":{}}',
            aux: '{}',
          },
        },
      },
    })

    mountDetail({ 'file:view': true }, {}, { query }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(FileDetail).vm

      cy.wrap(null).should(() => {
        expect(vm.loading).to.equal(false)
        expect(vm.item.disk).to.equal('private')
      })
    })
  })

  it('disables save button without file:save permission', () => {
    mountDetail({})
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables save button when nothing has changed', () => {
    mountDetail({ 'file:save': true })
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables publish button without file:publish permission', () => {
    mountDetail({})
    cy.get('.menu-publish').first().should('be.disabled')
  })

  it('renders the history button', () => {
    mountDetail()
    cy.get('button.btn-history').should('exist')
  })

  it('invalidates file lists', () => {
    const evict = cy.stub(apolloClient.cache, 'evict')
    const gc = cy.stub(apolloClient.cache, 'gc')

    mountDetail().then(() => {
      Cypress.vueWrapper.findComponent(FileDetail).vm.invalidate()

      expect(evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'files' })
      expect(gc).to.have.been.calledOnce
    })
  })

  it('renders the aside toggle button', () => {
    mountDetail()
    cy.get('button.btn-sidemenu').should('exist')
  })

  describe('reset()', () => {
    it('clears changed and error flags', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.dirty = true
        vm.error = true
        vm.reset()
        expect(vm.dirty).to.be.false
        expect(vm.error).to.be.false
      })
    })
  })

  describe('use()', () => {
    it('assigns version data to item and sets changed to true', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.use({ data: { name: 'updated.jpg', path: 'new/path.jpg' } })
        expect(vm.item.name).to.equal('updated.jpg')
        expect(vm.dirty).to.be.true
        expect(vm.vhistory).to.be.false
      })
    })

    it('drops the pending upload when discarding the changes', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.fileUpdated(new File([''], 'new.jpg'))
        vm.use({ data: { name: 'photo.jpg' } }, true)
        expect(vm.file).to.equal(null)
        expect(vm.dirty).to.be.false
      })
    })
  })

  describe('item events', () => {
    it('applies patches to the own copy and marks it dirty', () => {
      const initial = { ...baseItem }

      cy.mount(FileDetail, {
        props: { initial },
        global: { stubs, mocks: { $apollo: { query: () => Promise.resolve({ data: {} }) } } },
      }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'renamed.jpg' })

        expect(vm.item.name).to.equal('renamed.jpg')
        expect(vm.dirty).to.be.true
        expect(initial.name).to.equal('photo.jpg')
      })
    })

    it('applies synced changes without marking the item dirty', () => {
      mountDetail().then(({ wrapper }) => {
        const vm = wrapper.findComponent(FileDetail).vm
        vm.itemSynced({ disk: 'private' })

        expect(vm.item.disk).to.equal('private')
        expect(vm.dirty).to.be.false
      })
    })

    it('uses the synced cover version as base of the next save', () => {
      mountDetail().then(({ wrapper }) => {
        const vm = wrapper.findComponent(FileDetail).vm
        vm.itemSynced({ previews: { 500: 'files/clip.png' }, latestId: 'v9', published: false })

        expect(vm.item.latestId).to.equal('v9')
        expect(vm.item.published).to.be.false
        expect(vm.savedPreviews).to.equal(JSON.stringify({ 500: 'files/clip.png' }))
        expect(vm.dirty).to.be.false
      })
    })
  })

  describe('save()', () => {
    it('returns false when permission is denied', () => {
      mountDetail({}).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.save().then(result => {
          expect(result).to.be.false
        })
      })
    })

    it('returns false when there are errors', () => {
      mountDetail({ 'file:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.error = true
        vm.dirty = true
        vm.save().then(result => {
          expect(result).to.be.false
        })
      })
    })

    it('returns true when nothing has changed', () => {
      mountDetail({ 'file:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.save().then(result => {
          expect(result).to.be.true
        })
      })
    })

    it('only sends the previews if they have been changed', () => {
      const inputs = []
      const mutate = (options) => {
        inputs.push(options.variables.input)
        return Promise.resolve({ data: { saveFile: { latest: { id: 'v2', data: '{}' } } } })
      }

      mountDetail({ 'file:save': true }, { previews: { 480: 'a_480.webp' } }, { mutate }).then(() => {
        // the previews are taken when created, as reload() isn't called without file:view
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.dirty = true

        return vm.save().then(() => {
          expect(inputs[0]).to.not.have.property('previews')

          vm.item.previews = {}
          vm.dirty = true

          return vm.save()
        }).then(() => {
          expect(inputs[1].previews).to.equal('{}')
        })
      })
    })

    it('uploads an edited file only with the next save', () => {
      const files = []
      const mutate = (options) => {
        files.push(options.variables.file)
        return Promise.resolve({ data: { saveFile: { latest: { id: 'v2', data: '{}' } } } })
      }

      mountDetail({ 'file:save': true }, {}, { mutate }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        const file = new File(['png'], 'photo.png', { type: 'image/png' })

        vm.fileUpdated(file)

        return vm.save().then(() => {
          expect(files[0]).to.equal(file)
          expect(vm.file).to.equal(null)

          vm.item.name = 'renamed.jpg'
          vm.dirty = true

          return vm.save()
        }).then(() => {
          expect(files[1]).to.equal(null)
        })
      })
    })

    it('waits for a running save and skips the follow-up without new edits', () => {
      let resolve
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolve = res }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'renamed.jpg' })

        const first = vm.save()
        const second = vm.save(true)
        expect(second).not.to.equal(first)

        await new Promise((res) => setTimeout(res, 0))
        resolve({ data: { saveFile: { latest: { id: 'v2', data: '{}' } } } })

        expect(await first).to.be.true
        expect(await second).to.be.true
        expect(mutate).to.have.been.calledOnce
      })
    })

    it('saves edits made while saving with one shared follow-up save', () => {
      const resolves = []
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolves.push(res) }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        const first = vm.save()
        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledOnce

        vm.itemUpdated({ name: 'second.jpg' })
        const second = vm.save(true)
        const third = vm.save(true)
        expect(third).to.equal(second)

        resolves[0]({ data: { saveFile: { latest: { id: 'v2', data: '{"name":"first.jpg"}' } } } })
        expect(await first).to.be.true

        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledTwice
        expect(mutate.secondCall.args[0].variables.input.name).to.equal('second.jpg')
        expect(mutate.secondCall.args[0].variables.latestId).to.equal('v2')

        resolves[1]({ data: { saveFile: { latest: { id: 'v3', data: '{"name":"second.jpg"}' } } } })
        expect(await second).to.be.true
        expect(mutate).to.have.been.calledTwice
        expect(vm.item.latestId).to.equal('v3')
        expect(vm.dirty).to.be.false
      })
    })

    it('shows the success message of the follow-up save if any waiting caller wants it', () => {
      const resolves = []
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolves.push(res) }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        const add = Cypress.sinon.spy(useMessageStore(), 'add')
        vm.itemUpdated({ name: 'first.jpg' })

        const first = vm.save(true)
        await new Promise((res) => setTimeout(res, 0))

        vm.itemUpdated({ name: 'second.jpg' })
        const second = vm.save()
        expect(vm.save(true)).to.equal(second)

        resolves[0]({ data: { saveFile: { latest: { id: 'v2', data: '{"name":"first.jpg"}' } } } })
        expect(await first).to.be.true
        expect(add).not.to.have.been.calledWith('File saved successfully')

        await new Promise((res) => setTimeout(res, 0))
        resolves[1]({ data: { saveFile: { latest: { id: 'v3', data: '{"name":"second.jpg"}' } } } })
        expect(await second).to.be.true
        expect(add).to.have.been.calledWith('File saved successfully')
      })
    })

    it('keeps the follow-up save quiet if all waiting callers want it', () => {
      const resolves = []
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolves.push(res) }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        const add = Cypress.sinon.spy(useMessageStore(), 'add')
        vm.itemUpdated({ name: 'first.jpg' })

        const first = vm.save(true)
        await new Promise((res) => setTimeout(res, 0))

        vm.itemUpdated({ name: 'second.jpg' })
        const second = vm.save(true)
        vm.save(true)

        resolves[0]({ data: { saveFile: { latest: { id: 'v2', data: '{"name":"first.jpg"}' } } } })
        expect(await first).to.be.true

        await new Promise((res) => setTimeout(res, 0))
        resolves[1]({ data: { saveFile: { latest: { id: 'v3', data: '{"name":"second.jpg"}' } } } })
        expect(await second).to.be.true
        expect(mutate).to.have.been.calledTwice
        expect(add).not.to.have.been.calledWith('File saved successfully')
      })
    })

    it('does not publish when the file was edited while saving', () => {
      let resolve
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolve = res }))

      mountDetail({ 'file:save': true, 'file:publish': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        vm.publish()
        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledOnce

        vm.itemUpdated({ name: 'second.jpg' })
        resolve({ data: { saveFile: { latest: { id: 'v2', data: '{"name":"first.jpg"}' } } } })

        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledOnce
        expect(vm.publishing).to.be.false
        expect(vm.item.published).to.be.false
      })
    })

    it('keeps fields edited while saving when the server merged the changes', () => {
      let resolve
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolve = res }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        const saving = vm.save(true)
        await new Promise((res) => setTimeout(res, 0))

        vm.itemUpdated({ name: 'second.jpg' })
        resolve({ data: { saveFile: {
          latest: { id: 'v2', data: '{"name":"first.jpg"}' },
          changed: JSON.stringify({ latest: { id: 'v3', data: { name: 'first.jpg', path: 'images/other.jpg' } } }),
        } } })
        await saving

        expect(vm.item.name).to.equal('second.jpg')
        expect(vm.item.path).to.equal('images/other.jpg')
        expect(vm.dirty).to.be.true
      })
    })

    it('keeps edits and a file picked while saving unsaved', () => {
      let resolve
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res) => { resolve = res }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        const file = new File(['png'], 'photo.png', { type: 'image/png' })
        vm.itemUpdated({ name: 'first.jpg' })

        const saving = vm.save()
        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledOnce

        vm.itemUpdated({ name: 'second.jpg' })
        vm.fileUpdated(file)
        resolve({ data: { saveFile: { latest: { id: 'v2', data: '{"name":"first.jpg","path":"images/new.jpg"}' } } } })
        await saving

        expect(vm.item.name).to.equal('second.jpg')
        expect(vm.item.path).to.equal('images/new.jpg')
        expect(vm.item.latestId).to.equal('v2')
        expect(vm.file).to.equal(file)
        expect(vm.dirty).to.be.true
      })
    })

    it('publishes after a save merged changes without conflicts', () => {
      const mutate = Cypress.sinon.stub()
      mutate.onFirstCall().resolves({ data: { saveFile: {
        latest: { id: 'v2', data: '{"name":"first.jpg"}' },
        changed: JSON.stringify({ editor: 'other', latest: { id: 'v3', data: { path: 'images/other.jpg' } } }),
      } } })
      mutate.resolves({ data: {} })

      mountDetail({ 'file:save': true, 'file:publish': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        vm.publish()
        await new Promise((res) => setTimeout(res, 10))

        expect(vm.changed).not.to.equal(null)
        expect(mutate).to.have.been.calledTwice
        expect(vm.item.published).to.be.true
      })
    })

    it('does not publish with unresolved conflicts and tells the user', () => {
      const mutate = Cypress.sinon.stub().resolves({ data: { saveFile: {
        latest: { id: 'v2', data: '{"name":"first.jpg"}' },
        changed: JSON.stringify({ editor: 'other', latest: { id: 'v3', data: {} }, data: { name: { overwritten: 'theirs.jpg' } } }),
      } } })

      mountDetail({ 'file:save': true, 'file:publish': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        const messages = useMessageStore()
        messages.queue = []
        vm.itemUpdated({ name: 'first.jpg' })

        vm.publish()
        await new Promise((res) => setTimeout(res, 10))

        expect(mutate).to.have.been.calledOnce
        expect(vm.item.published).to.be.false
        expect(messages.queue.map((msg) => msg.text)).to.include('Not published, please review the changes and publish again')
      })
    })

    it('returns false when saving fails', () => {
      const mutate = Cypress.sinon.stub().rejects(new Error('offline'))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        expect(await vm.save(true)).to.be.false
        expect(vm.dirty).to.be.true
      })
    })

    it('does not repeat a failed save without new edits', () => {
      let reject
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res, rej) => { reject = rej }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        const first = vm.save(true)
        await new Promise((res) => setTimeout(res, 0))
        const second = vm.save(true)

        reject(new Error('offline'))

        expect(await first).to.be.false
        expect(await second).to.be.false
        expect(mutate).to.have.been.calledOnce
      })
    })

    it('does not repeat a failed save for field changes applied before saving', () => {
      let reject
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res, rej) => { reject = rej }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm

        const first = vm.save(true)
        // the fields apply their changes in the next tick, before the item is saved
        vm.itemUpdated({ name: 'first.jpg' })
        const second = vm.save(true)

        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables.input.name).to.equal('first.jpg')

        reject(new Error('offline'))

        expect(await first).to.be.false
        expect(await second).to.be.false
        expect(mutate).to.have.been.calledOnce
      })
    })

    it('saves edits made during a failed save with the follow-up save', () => {
      const rejects = []
      const mutate = Cypress.sinon.stub().callsFake(() => new Promise((res, rej) => { rejects.push(rej) }))

      mountDetail({ 'file:save': true }, {}, { mutate }).then(async () => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.itemUpdated({ name: 'first.jpg' })

        const first = vm.save(true)
        await new Promise((res) => setTimeout(res, 0))
        vm.itemUpdated({ name: 'second.jpg' })
        vm.save(true)

        rejects[0](new Error('invalid'))
        expect(await first).to.be.false

        await new Promise((res) => setTimeout(res, 0))
        expect(mutate).to.have.been.calledTwice
        expect(mutate.secondCall.args[0].variables.input.name).to.equal('second.jpg')
      })
    })
  })

  describe('versions()', () => {
    it('returns empty array without file:view permission', () => {
      mountDetail({}).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.versions('1').then(result => {
          expect(result).to.deep.equal([])
        })
      })
    })

    it('returns empty array when id is falsy', () => {
      mountDetail({ 'file:view': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.versions('').then(result => {
          expect(result).to.deep.equal([])
        })
      })
    })
  })

  describe('publish menu', () => {
    it('renders one publish menu with both actions', () => {
      mountDetail({ 'file:publish': true })
      cy.get('.menu-publish').should('have.length', 1).click()
      cy.get('.menu-publish-now').should('contain', 'Publish')
      cy.get('.menu-schedule-at').should('contain', 'Schedule')
    })

    it('opens menu with date and time pickers', () => {
      mountDetail({ 'file:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.v-date-picker').should('exist')
      cy.get('.v-time-picker').should('exist')
    })

    it('disables schedule action when no date selected', () => {
      mountDetail({ 'file:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.menu-schedule-at').should('be.disabled')
    })

    it('schedule() combines date and time', () => {
      mountDetail({ 'file:publish': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
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
      mountDetail({ 'file:publish': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
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
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('shows changes button even when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c', resolved: true, chosen: 'c' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('uses warning color on save button when hasConflict is true', () => {
      mountDetail({ 'file:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c' } } }
        vm.dirty = true
        cy.get('.menu-save').should('have.class', 'text-warning')
      })
    })

    it('hasConflict is false when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c', resolved: true, chosen: 'c' } } }
        expect(vm.hasConflict).to.be.false
      })
    })

    it('hasConflict is true when overwritten exists without resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c' } } }
        expect(vm.hasConflict).to.be.true
      })
    })

    it('hasConflict includes auxiliary file fields', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.changed = { editor: 'x', aux: { description: { previous: {}, current: {}, overwritten: { en: 'theirs' } } } }
        expect(vm.hasConflict).to.be.true
      })
    })
  })
})
