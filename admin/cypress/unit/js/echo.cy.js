import {
  bulkPatch,
  channelName,
  eventPatch,
  listEcho,
  resync,
  PATCH_ACTIONS,
  LIST_ACTIONS,
  RECONNECT,
} from '../../../js/echo'

describe('channelName()', () => {
  it('targets the per-type channel scoped to the tenant', () => {
    expect(channelName('page', 'acme')).to.equal('cms.acme.page')
  })

  it('omits the tenant segment when there is no tenant', () => {
    expect(channelName('element', '')).to.equal('cms.element')
  })
})

describe('action vocabularies', () => {
  it('patch actions are the in-place row updates', () => {
    expect(PATCH_ACTIONS).to.deep.equal(['saved', 'published', 'restored', 'dropped'])
  })

  it('list actions extend patch actions with the bulk and structural ones', () => {
    expect(LIST_ACTIONS).to.deep.equal([
      'saved', 'published', 'restored', 'dropped', 'bulk', 'added', 'moved', 'purged',
    ])
  })

  it('the reconnect sentinel is not a real action', () => {
    expect(PATCH_ACTIONS).to.not.include(RECONNECT)
    expect(LIST_ACTIONS).to.not.include(RECONNECT)
  })
})

describe('eventPatch()', () => {
  it('maps a list event to the row update fields', () => {
    const event = {
      data: { name: 'Home' },
      id: 'p1',
      published: true,
      deleted_at: null,
      publish_at: null,
      updated_at: '2026-06-18',
      editor: 'a@b',
      latest_id: 'v9',
    }

    expect(eventPatch(event)).to.deep.equal({
      name: 'Home',
      id: 'p1',
      published: true,
      deleted_at: null,
      publish_at: null,
      updated_at: '2026-06-18',
      editor: 'a@b',
      latest_id: 'v9',
    })
  })
})

describe('bulkPatch()', () => {
  it('maps one id of a bulk event to its row update fields', () => {
    // data is pre-sanitized by the caller (sanitized once for the whole batch)
    const data = { lang: 'de', published: false }
    const event = { editor: 'a@b', latest: { p1: 'v1', p2: 'v2' } }

    expect(bulkPatch(data, event, 'p2')).to.deep.equal({
      lang: 'de',
      published: false,
      id: 'p2',
      editor: 'a@b',
      latest_id: 'v2',
    })
  })
})

describe('listEcho()', () => {
  function vm() {
    return {
      outdated: false,
      patched: [],
      patch(p) { this.patched.push(p) },
      patchItems(items) { this.patched.push(...items) }
    }
  }

  it('flags the list outdated on reconnect', () => {
    const m = vm()
    listEcho(m, null, RECONNECT)
    expect(m.outdated).to.be.true
  })

  it('flags the list outdated on a structural change', () => {
    const m = vm()
    listEcho(m, {}, 'added')
    expect(m.outdated).to.be.true
  })

  it('patches the row on a patch action', () => {
    const m = vm()
    listEcho(m, { id: 'p1', data: {} }, 'saved')
    expect(m.outdated).to.be.false
    expect(m.patched[0].id).to.equal('p1')
  })

  it('patches every listed row on a bulk action', () => {
    const m = vm()
    listEcho(m, { ids: ['p1', 'p2'], data: { lang: 'de' }, latest: { p1: 'v1', p2: 'v2' } }, 'bulk')
    expect(m.outdated).to.be.false
    expect(m.patched.map((p) => p.id)).to.deep.equal(['p1', 'p2'])
    expect(m.patched[1].lang).to.equal('de')
    expect(m.patched[1].latest_id).to.equal('v2')
  })
})

describe('resync()', () => {
  it('notifies every subscriber with (null, RECONNECT)', () => {
    const calls = []
    const a = (event, name) => calls.push(['a', event, name])
    const b = (event, name) => calls.push(['b', event, name])

    resync([a, b])

    expect(calls).to.deep.equal([
      ['a', null, RECONNECT],
      ['b', null, RECONNECT],
    ])
  })

  it('drives a list view to a reload on reconnect via listEcho', () => {
    const m = { outdated: false, patch() {} }

    resync([(event, name) => listEcho(m, event, name)])

    expect(m.outdated).to.be.true
  })

  it('keeps notifying subscribers when one throws', () => {
    const calls = []
    const a = () => { throw new Error('boom') }
    const b = (event, name) => calls.push(['b', event, name])

    resync([a, b])

    expect(calls).to.deep.equal([['b', null, RECONNECT]])
  })
})
