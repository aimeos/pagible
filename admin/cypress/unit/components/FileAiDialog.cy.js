import { h } from 'vue'
import FileAiDialog from '../../../js/components/FileAiDialog.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  FileListItems: {
    render: () => h('div', { class: 'file-list-stub' }),
    methods: { invalidate() {} },
  },
}

function mountDialog(props = {}, perms = {}, apollo = {}) {
  return cy.mount(FileAiDialog, {
    props: {
      modelValue: true,
      ...props,
    },
    global: {
      stubs,
      mocks: {
        $apollo: {
          mutate: () => Promise.resolve({ data: {} }),
          ...apollo,
        },
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileAiDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "Create image" as the title', () => {
    mountDialog()
    cy.contains('Create image').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[aria-label="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(FileAiDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
      global: { stubs },
    })
    cy.get('button[aria-label="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('renders a textarea for image description', () => {
    mountDialog()
    cy.get('textarea').should('exist')
  })

  it('renders the "New image" button', () => {
    mountDialog()
    cy.contains('.v-btn', 'New image').should('exist')
  })

  it('disables the "New image" button when textarea is empty', () => {
    mountDialog()
    cy.contains('.v-btn', 'New image').should('be.disabled')
  })

  it('shows dictate button with audio:transcribe permission', () => {
    mountDialog({}, { 'audio:transcribe': true })
    cy.get('button[aria-label="Dictate"]').should('exist')
  })

  it('hides dictate button without audio:transcribe permission', () => {
    mountDialog()
    cy.get('button[aria-label="Dictate"]').should('not.exist')
  })

  it('renders the FileListItems stub for selecting images', () => {
    mountDialog()
    cy.get('.file-list-stub').should('exist')
  })

  it('shows "Select images" heading without tab semantics', () => {
    mountDialog()
    cy.contains('h3', 'Select images').should('exist')
    cy.get('[role="tablist"]').should('not.exist')
  })

  it('stores generated images directly on the selected disk', () => {
    const mutate = cy.stub().resolves({
      data: {
        addFile: {
          id: '1',
          disk: 'private',
          name: 'Generated image',
          path: 'cms/test/file/generated.png',
          previews: '{}',
        },
      },
    })

    mountDialog({ disk: 'private' }, {}, { mutate }).then(({ wrapper }) => {
      wrapper.findComponent(FileAiDialog).vm.add({
        blob: new Blob(['image'], { type: 'image/png' }),
        mime: 'image/png',
        name: 'Generated image',
        path: 'blob:generated',
      })
    })

    cy.wrap(mutate).should('have.been.calledOnce')
    cy.wrap(mutate).should((stub) => {
      expect(stub.firstCall.args[0].variables.disk).to.equal('private')
      expect(stub.firstCall.args[0].variables.file).to.be.instanceOf(File)
    })
  })

  it('uploads a generated image only once on double clicks', () => {
    const mutate = cy.stub().returns(new Promise(() => {}))

    mountDialog({}, {}, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileAiDialog).vm
      const item = { blob: new Blob(['image'], { type: 'image/png' }), mime: 'image/png', name: 'Image', path: 'blob:generated' }

      vm.add(item)
      vm.add(item)

      expect(mutate).to.have.been.calledOnce
    })
  })

  it('drops image responses arriving after the dialog was closed', () => {
    let resolve
    const mutate = cy.stub().returns(new Promise((res) => (resolve = res)))

    mountDialog({}, { 'image:imagine': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileAiDialog).vm

      vm.chat = 'a red ball'
      vm.create()
      expect(vm.loading).to.equal(true)

      // the props of the wrapped component can't be changed, so the close handler is called directly
      vm.$options.watch.modelValue.handler.call(vm, false)
      expect(vm.loading).to.equal(false)
      resolve({ data: { imagine: btoa('png') } })

      return new Promise((res) => setTimeout(res, 0)).then(() => {
        expect(vm.items).to.have.length(0)
        expect(vm.loading).to.equal(false)
      })
    })
  })

  it('reports an upload finished after closing the dialog as success', () => {
    const mutate = cy.stub().resolves({ data: { addFile: { id: '1', path: 'cms/test/file/a.png', previews: '{}' } } })

    mountDialog({}, {}, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileAiDialog).vm
      const error = cy.stub(vm.messages, 'error')
      const item = { blob: new Blob(['image'], { type: 'image/png' }), mime: 'image/png', name: 'Image', path: 'blob:generated' }

      vm.add(item)
      expect(vm.uploading).to.equal(true)
      // the list isn't rendered after closing the dialog
      vm.$refs.filelist = undefined

      cy.wrap(vm).its('uploading').should('equal', false).then(() => {
        expect(error).not.to.have.been.called
        expect(wrapper.findComponent(FileAiDialog).emitted('add')).to.have.length(1)
      })
    })
  })

  it('stops the dictation when the dialog is closed', () => {
    mountDialog().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileAiDialog).vm
      const stop = cy.stub(vm.dictation, 'stop')

      vm.$options.watch.modelValue.handler.call(vm, false)
      expect(stop).to.have.been.calledOnce
    })
  })

  it('appends dictated text to the prompt', () => {
    mountDialog().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileAiDialog).vm
      cy.stub(vm.dictation, 'toggle').callsFake((done) => done('blue sky'))

      vm.chat = 'a red ball'
      vm.record()
      expect(vm.chat).to.equal('a red ball blue sky')
    })
  })

  it('shortens long prompts at a word boundary for the file name', () => {
    mountDialog().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileAiDialog).vm
      const words = 'word '.repeat(60).trim()

      expect(vm.filename('short prompt')).to.equal('short prompt')
      expect(vm.filename(words).length).to.be.at.most(250)
      expect(vm.filename(words).endsWith('word')).to.equal(true)
      expect(vm.filename('x'.repeat(300))).to.have.length(250)
    })
  })
})

