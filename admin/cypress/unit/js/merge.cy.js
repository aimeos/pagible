import { assignMerged, hasUnresolved, resolveChange, unresolveChange } from '../../../js/merge'

function conflicts() {
  return {
    data: { title: { current: 'Mine', overwritten: 'Theirs' } },
    content: { abc123: { current: {}, overwritten: {} } },
  }
}

describe('merge', () => {
  describe('resolveChange()', () => {
    it('marks the conflict as resolved without a target', () => {
      const changed = conflicts()

      resolveChange(changed, undefined, 'data', 'title', 'My Title')

      expect(changed.data.title.resolved).to.equal(true)
      expect(changed.data.title.chosen).to.equal('My Title')
      expect(hasUnresolved(changed)).to.equal(false)
    })

    it('resolves the conflict with an empty value', () => {
      const changed = conflicts()
      const target = { title: 'old' }

      resolveChange(changed, { data: target }, 'data', 'title', '')

      expect(target.title).to.equal('')
      expect(hasUnresolved(changed)).to.equal(false)

      unresolveChange(changed, { data: target }, 'data', 'title')

      expect(target.title).to.equal('old')
      expect(changed.data.title).not.to.have.property('chosen')
      expect(hasUnresolved(changed)).to.equal(true)
    })

    it('updates the target object', () => {
      const changed = conflicts()
      const target = { title: 'old' }

      resolveChange(changed, { data: target }, 'data', 'title', 'My Title')

      expect(target.title).to.equal('My Title')
      expect(changed.data.title.snapshot).to.equal('old')
    })

    it('updates the target array by id match', () => {
      const changed = conflicts()
      const target = [{ id: 'abc123', type: 'text', data: { text: 'old' } }]
      const replacement = { id: 'abc123', type: 'text', data: { text: 'mine' } }

      resolveChange(changed, { content: target }, 'content', 'abc123', replacement)

      expect(target[0]).to.equal(replacement)
    })
  })

  describe('unresolveChange()', () => {
    it('restores the original target value', () => {
      const changed = conflicts()
      const target = { title: 'original' }

      resolveChange(changed, { data: target }, 'data', 'title', 'Their Title')
      unresolveChange(changed, { data: target }, 'data', 'title')

      expect(target.title).to.equal('original')
      expect(changed.data.title).not.to.have.property('resolved')
      expect(changed.data.title).not.to.have.property('snapshot')
      expect(hasUnresolved(changed)).to.equal(true)
    })
  })

  describe('assignMerged()', () => {
    it('applies all merged fields without a sent copy', () => {
      const item = { name: 'mine', path: 'old' }

      assignMerged(item, { name: 'merged', path: 'new' })

      expect(item).to.deep.equal({ name: 'merged', path: 'new' })
    })

    it('keeps the fields edited while saving', () => {
      const sent = { name: 'sent', path: 'old', meta: { a: 1 } }
      const item = { name: 'edited', path: 'old', meta: { a: 1 } }

      assignMerged(item, { name: 'merged', path: 'new', meta: { a: 2 } }, sent)

      expect(item).to.deep.equal({ name: 'edited', path: 'new', meta: { a: 2 } })
    })

    it('compares nested values by content', () => {
      const sent = { content: [{ id: 'a', data: { text: 'x' } }] }
      const item = { content: [{ id: 'a', data: { text: 'y' } }] }

      assignMerged(item, { content: [] }, sent)

      expect(item.content).to.deep.equal([{ id: 'a', data: { text: 'y' } }])
    })
  })
})
