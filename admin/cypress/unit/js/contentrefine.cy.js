import { contentRefine } from '../../../js/contentrefine'

function refiner(content, refined) {
  let resolve
  const vm = {
    ...contentRefine.data(),
    chat: 'Improve',
    content,
    section: 'main',
    item: { lang: 'en', type: 'page' },
    user: { can: () => true },
    messages: { add: cy.stub(), error: cy.stub(), denied: cy.stub() },
    $gettext: (msg) => msg,
    $emit: cy.stub(),
    $apollo: { mutate: () => new Promise((res) => (resolve = res)) }
  }

  const promise = contentRefine.methods.refine.call(vm)
  return { vm, promise, respond: () => resolve({ data: { refine: JSON.stringify(refined) } }) }
}

describe('contentRefine', () => {
  it('ignores the key order and UI state when detecting changes', () => {
    const content = [{ id: 'a', type: 'text', group: 'main', data: { text: 'x', level: 1 }, _changed: false }]
    const { vm, promise, respond } = refiner(content, [{ data: { level: 1, text: 'x' }, group: 'main', type: 'text', id: 'a' }])

    respond()

    return promise.then(() => {
      const list = vm.$emit.firstCall.args[1]
      expect(list[0]).not.to.have.property('_changed')
      expect(vm.messages.add).not.to.have.been.called
    })
  })

  it('keeps the elements edited during the request', () => {
    const content = [
      { id: 'a', type: 'text', group: 'main', data: { text: 'a' } },
      { id: 'b', type: 'text', group: 'main', data: { text: 'b' } },
      { id: 'c', type: 'text', group: 'main', data: { text: 'c' } }
    ]
    const { vm, promise, respond } = refiner(content, [
      { id: 'a', type: 'text', data: { text: 'A' } },
      { id: 'b', type: 'text', data: { text: 'B' } },
      { id: 'c', type: 'text', data: { text: 'C' } }
    ])

    const edited = { id: 'a', type: 'text', group: 'main', data: { text: 'mine' } }
    const added = { id: 'd', type: 'text', group: 'main', data: { text: 'new' } }
    // "a" is edited, "c" removed and "d" added while the request runs
    vm.content = [edited, content[1], added]
    respond()

    return promise.then(() => {
      const list = vm.$emit.firstCall.args[1]

      expect(list.map((item) => item.id)).to.deep.equal(['a', 'b', 'd'])
      expect(list[0]).to.equal(edited)
      expect(list[1].data.text).to.equal('B')
      expect(list[1]._changed).to.equal(true)
      expect(list[2]).to.equal(added)
      expect(vm.messages.add).to.have.been.calledWith('Elements changed during the refinement were kept unchanged', 'info')
    })
  })
})
