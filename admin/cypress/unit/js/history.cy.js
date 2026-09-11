import { assets, blocks, fieldmedia, filepairs, media, plaintext, restore, sections, words } from '../../../js/history'
import { loadVersions } from '../../../js/version'

const block = (id, text = id) => ({ id, type: 'text', group: 'main', data: { text } })
const a = block('a'), b = block('b'), c = block('c')
const moveKey = entry => entry.fields.find(field => field.position).key
const context = () => ({
  user: { can: cy.stub().returns(true) },
  $apollo: { query: cy.stub() },
  messages: { add: cy.spy() },
  $gettext: value => value,
})
const load = (vm, key = 'page', id = 'id', convert = value => value) =>
  loadVersions(vm, 'query', key, id, convert)

describe('History comparisons and restoration', () => {
  it('ignores metadata and object key order without ignoring real scalar changes', () => {
    expect(sections({ editor: 'A', previews: { 200: 'a' }, data: { a: 1, b: 2 } }, {
      editor: 'B', previews: { 200: 'b' }, data: { b: 2, a: 1 }
    })).to.deep.equal({})
    expect(sections({ status: 0 }, { status: 1 }).data).to.have.length(1)
  })

  it('reports movement without treating an insertion as movement of every following block', () => {
    const moved = blocks([a, b, c], [c, a, b])
    expect(moved).to.have.length(1)
    expect(moved[0]).to.include({ from: 2, to: 0, moved: true })
    expect(blocks([a, b], [c, a, b]).map(entry => entry.kind)).to.deep.equal(['added'])
  })

  it('includes text, type and group changes together', () => {
    const diff = blocks([a], [{ ...a, type: 'heading', group: 'aside', data: { text: 'Changed' } }])
    expect(diff[0].fields.map(field => field.path.join('.'))).to.deep.equal(['type', 'group', 'data.text'])
  })

  it('keeps repeated shared references and legacy blocks alongside keyed blocks', () => {
    const before = [a, { type: 'text', data: { text: 'Legacy' } }, { refid: 'ref' }, { refid: 'ref' }]
    const after = [a, { type: 'text', data: { text: 'Edited' } }, { refid: 'ref' }, { refid: 'ref', group: 'aside' }]
    const diffs = sections({ content: before }, { content: after })
    expect(diffs.content).to.have.length(2)
    expect(new Set(diffs.content.map(entry => entry.key)).size).to.equal(2)
    expect(restore({ content: before }, { content: after }, diffs, () => true).content).to.deep.equal(after)
  })

  it('restores only the selected block and preserves unchecked edits', () => {
    const current = { content: [block('a', 'Edited A'), block('b', 'Edited B')] }
    const target = { content: [a, b] }
    const diffs = sections(current, target)
    expect(restore(current, target, diffs, key => diffs.content[0].keys.includes(key))).to.deep.equal({ content: [a, current.content[1]] })
    expect(current.content[0].data.text).to.equal('Edited A')
  })

  it('restores only a selected nested field and preserves other current values', () => {
    const current = { meta: { seo: { data: { title: 'Unsaved title', description: 'Unsaved description' } } } }
    const target = { meta: { seo: { data: { title: 'Old title', description: 'Old description' } } } }
    const diffs = sections(current, target)
    expect(restore(current, target, diffs, key => key === diffs.meta[0].key)).to.deep.equal({
      meta: { seo: { data: { title: 'Old title', description: 'Unsaved description' } } }
    })
  })

  it('deletes selected nested properties and keeps removals serializable', () => {
    const current = { title: 'New', config: { options: { a: 1, b: 2 } } }
    const target = { config: { options: { b: 2 } } }
    const changes = restore(current, target, sections(current, target), () => true)
    expect(JSON.parse(JSON.stringify(changes))).to.deep.equal({ title: null, config: { options: { b: 2 } } })
  })

  it('restores selected additions and removals without removing an unchecked block', () => {
    const current = { content: [a, b] }, target = { content: [b, c] }
    const diffs = sections(current, target)
    expect(restore(current, target, diffs, key => key === diffs.content.find(entry => entry.kind === 'added').key).content).to.deep.equal([a, b, c])
    expect(restore(current, target, diffs, key => key === diffs.content.find(entry => entry.kind === 'removed').key).content).to.deep.equal([b])
  })

  it('restores a selected move while preserving the content of unchecked blocks', () => {
    const current = { content: [block('a', 'Keep this edit'), b, c] }, target = { content: [c, a, b] }
    const diffs = sections(current, target)
    const moved = diffs.content.find(entry => entry.moved)
    expect(restore(current, target, diffs, key => key === moveKey(moved)).content).to.deep.equal([c, current.content[0], b])
  })

  it('restores current edits in reverse, including movement', () => {
    const saved = { content: [a, b, c] }, current = { content: [c, block('a', 'Unsaved A'), b] }
    const diffs = sections(saved, current)
    const moved = diffs.content.find(entry => entry.moved)
    expect(restore(current, saved, diffs, key => key === moveKey(moved)).content).to.deep.equal([current.content[1], b, c])
  })

  it('restores file previews together with a selected path', () => {
    const current = { path: 'new.jpg', previews: { 200: 'new.webp' }, name: 'Keep name' }
    const target = { path: 'old.jpg', previews: { 200: 'old.webp' }, name: 'Old name' }
    const diffs = sections(current, target)
    expect(restore(current, target, diffs, key => key === diffs.data[0].key)).to.deep.equal({ path: 'old.jpg', previews: target.previews })
  })

  it('shows both media versions when the path changes under the same file ID', () => {
    const before = { f: { id: 'f', path: 'old.jpg', editor: 'a' } }
    expect(media(before, { f: { ...before.f, editor: 'b' } })).to.deep.equal([])
    expect(media(before, { f: { id: 'f', path: 'new.jpg' } }).map(entry => entry.side)).to.deep.equal(['before', 'after'])
  })

  it('restores combinations of edits, moves, additions and removals in both directions', () => {
    let seed = 17
    const random = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 2 ** 32 }
    const pool = [a, b, c, block('d'), block('e')]
    const sample = () => pool.filter(() => random() > 0.25)
      .map(item => ({ ...item, data: { text: random() > 0.5 ? item.data.text : 'Changed' } }))
      .sort(() => random() - 0.5)

    for (let index = 0; index < 100; index++) {
      const before = { content: sample() }, after = { content: sample() }
      const diffs = sections(before, after)
      expect({ ...before, ...restore(before, after, diffs, () => true) }).to.deep.equal(after)
      expect({ ...after, ...restore(after, before, diffs, () => true) }).to.deep.equal(before)
    }
  })
  it('restores a single block field independently of its move and other fields', () => {
    const current = { content: [a, { ...b, group: 'aside', data: { title: 'New title', text: 'New text' } }, c] }
    const target = { content: [{ ...b, data: { title: 'Old title', text: 'Old text' } }, a, c] }
    const diffs = sections(current, target)
    const entry = diffs.content.find(entry => entry.before.id === 'b')
    const title = entry.fields.find(field => field.path?.join('.') === 'data.title')
    expect(restore(current, target, diffs, key => key === title.key).content).to.deep.equal([
      a, { ...current.content[1], data: { title: 'Old title', text: 'New text' } }, c
    ])
    expect(restore(current, target, diffs, key => key === moveKey(entry)).content).to.deep.equal([current.content[1], a, c])
  })

  it('updates derived media references when restoring a single image field', () => {
    const image = id => ({ id, type: 'file' })
    const current = { content: [{ ...a, data: { image: image('new'), other: image('keep') }, files: ['new', 'keep'] }] }
    const target = { content: [{ ...a, data: { image: image('old'), other: image('different') }, files: ['old', 'different'] }] }
    const diffs = sections(current, target)
    expect(diffs.content[0].fields).to.have.length(2)
    const field = diffs.content[0].fields.find(field => field.path.at(-1) === 'image')
    const result = restore(current, target, diffs, key => key === field.key)
    expect(result.content[0].files).to.deep.equal(['old', 'keep'])
    expect(result.content[0].data.other).to.deep.equal(image('keep'))
    expect(current.content[0].files).to.deep.equal(['new', 'keep'])
  })

  it('keeps meta and configuration media references consistent with partially restored data', () => {
    for (const section of ['meta', 'config']) {
      const current = { [section]: { seo: { type: 'seo', data: { image: { type: 'file', id: 'new' }, title: 'Keep' }, files: ['new'] } } }
      const target = { [section]: { seo: { type: 'seo', data: { image: { type: 'file', id: 'old' }, title: 'Old' }, files: ['old'] } } }
      const diffs = sections(current, target)
      const field = diffs[section].find(field => field.path.at(-1) === 'image')
      const result = restore(current, target, diffs, key => key === field.key)
      expect(result[section].seo.files).to.deep.equal(['old'])
      expect(result[section].seo.data.title).to.equal('Keep')
      expect(current[section].seo.files).to.deep.equal(['new'])
    }
  })

  it('pairs media around insertions and removals without shifting unchanged files', () => {
    const a = { id: 'a', path: 'a.jpg' }, b = { id: 'b', path: 'b.jpg' }, c = { id: 'c', path: 'c.jpg' }
    const rows = filepairs([a, b], [a, c, b])
    expect(rows.map(row => [row.before?.id, row.after?.id, row.kind, row.moved])).to.deep.equal([
      ['a', 'a', 'unchanged', false], [undefined, 'c', 'added', false], ['b', 'b', 'unchanged', false]
    ])
    expect(filepairs([a, c, b], [a, b]).map(row => row.kind)).to.deep.equal(['unchanged', 'removed', 'unchanged'])
  })

  it('distinguishes moved media, changed files and changes to attribution', () => {
    const a = { id: 'a', path: 'a.jpg' }, b = { id: 'b', path: 'b.jpg' }, c = { id: 'c', path: 'c.jpg' }
    const rows = filepairs([a, b, c], [c, { ...a, path: 'edited.jpg' }, { ...b, editor: 'Someone' }])
    expect(rows[0]).to.include({ kind: 'unchanged', moved: true, from: 2, to: 0 })
    expect(rows[1]).to.include({ kind: 'changed', moved: false })
    expect(rows[2]).to.include({ kind: 'unchanged', moved: false })
    expect(filepairs([a], [b])[0]).to.include({ before: a, after: b, kind: 'changed' })
  })

})

describe('History text presentation', () => {
  it('refines short replacements without splitting graphemes or losing input', () => {
    expect(words('SKU-1234', 'SKU-1235').filter(part => part.removed).map(part => part.value).join('')).to.equal('4')
    expect(words('SKU-1234', 'SKU-1235').filter(part => part.added).map(part => part.value).join('')).to.equal('5')

    for (const [before, after] of [['A 👍🏻', 'A 👍🏽'], ['cafe\u0301', 'cafe'], ['A B', 'A  B'], ['A\nB', 'A\n\nB'], ['**Team**', '__Team__']]) {
      const result = words(before, after)
      expect(result.filter(part => !part.added).map(part => part.value).join('')).to.equal(before)
      expect(result.filter(part => !part.removed).map(part => part.value).join('')).to.equal(after)
    }

    expect(words('👍🏻', '👍🏽').filter(part => part.removed).map(part => part.value)).to.deep.equal(['👍🏻'])
    expect(words('cafe\u0301', 'cafe').filter(part => part.removed).map(part => part.value)).to.deep.equal(['e\u0301'])
  })

  it('extracts safe plain text for block titles', () => {
    expect(plaintext('<p>Useful <strong>context</strong></p><p>Second</p><script>window.historyTextInjected=true</script>'))
      .to.equal('Useful context Second')
    expect(plaintext('Literal **text**')).to.equal('Literal **text**')
    expect(window.historyTextInjected).to.equal(undefined)
  })

  it('resolves direct media references without duplicating or expanding structured fields', () => {
    const file = { id: 'image', path: 'image.jpg' }, files = { image: file }
    expect(assets([{ image: { type: 'file', id: 'image' } }, 'image'], files)).to.deep.equal([file])
    expect(fieldmedia({ before: { type: 'file', id: 'image' }, after: 'image' }, { files }, { files }))
      .to.deep.equal({ before: [file], after: [file] })
    expect(fieldmedia({ before: [{ image: { type: 'file', id: 'image' } }], after: [] }, { files }, { files }))
      .to.deep.equal({ before: [], after: [] })
  })
})

describe('History version loading', () => {
  for (const key of ['page', 'element', 'file']) {
    it(`loads and converts ${key} snapshots without caching`, async () => {
      const vm = context()
      vm.$apollo.query.resolves({ data: { [key]: { versions: [{ data: '{"title":"Saved"}' }] } } })
      const result = await load(vm, key, 'saved-id', version => ({ data: JSON.parse(version.data) }))
      expect(result).to.deep.equal([{ data: { title: 'Saved' } }])
      expect(vm.user.can).to.have.been.calledWith(key + ':view')
      expect(vm.$apollo.query).to.have.been.calledOnceWithExactly({ query: 'query', variables: { id: 'saved-id' }, fetchPolicy: 'no-cache' })
    })
  }

  it('skips requests when permission or an ID is missing', async () => {
    const vm = context()
    vm.user.can.returns(false)
    expect(await load(vm)).to.deep.equal([])
    expect(vm.messages.add).to.have.been.calledOnceWithExactly('Permission denied', 'error')
    vm.user.can.returns(true)
    expect(await load(vm, 'page', '')).to.deep.equal([])
    expect(vm.$apollo.query).not.to.have.been.called
  })

  it('returns empty history for an item with no versions', async () => {
    const vm = context()
    vm.$apollo.query.resolves({ data: { page: {} } })
    expect(await load(vm)).to.deep.equal([])
  })

  for (const failure of ['network', 'graphql', 'missing item', 'conversion']) {
    it(`rejects ${failure} failures without disguising them as empty history`, async () => {
      const vm = context()
      if (failure === 'network') vm.$apollo.query.rejects(new Error('Offline'))
      else vm.$apollo.query.resolves(failure === 'graphql' ? { errors: [{ message: 'Denied' }] }
        : { data: { page: failure === 'missing item' ? null : { versions: [{}] } } })
      let rejected = false
      try {
        await load(vm, 'page', 'id', () => { throw new Error('Invalid snapshot') })
      } catch {
        rejected = true
      }
      expect(rejected).to.equal(true)
      expect(vm.messages.add).not.to.have.been.called
    })
  }
})
