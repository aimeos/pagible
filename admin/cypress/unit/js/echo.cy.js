import {
  backoff,
  bindReconnect,
  channelName,
  disconnect,
  joinChannel,
  realtime,
  resubscribe,
  resync,
  setupEcho,
  subscribe,
  PATCH_ACTIONS,
  LIST_ACTIONS,
  RECONNECT,
} from '../../../js/echo'
import { setupReload } from '../../../js/livesync'

describe('backoff()', () => {
  it('retries loading the Echo libraries with increasing delays', () => {
    expect(backoff(0)).to.equal(0)
    expect(backoff(1)).to.equal(30000)
    expect(backoff(2)).to.equal(60000)
    expect(backoff(3)).to.equal(120000)
    expect(backoff(10)).to.equal(600000)
  })
})

describe('bindReconnect()', () => {
  function connection() {
    let connected
    const echo = {
      connector: {
        pusher: {
          connection: {
            bind(name, callback) {
              expect(name).to.equal('connected')
              connected = callback
            }
          }
        }
      }
    }

    return { echo, fire: () => connected() }
  }

  it('treats the first connection of each Echo instance as initial', () => {
    const reconnect = cy.stub()
    const first = connection()
    const second = connection()

    bindReconnect(first.echo, reconnect)
    bindReconnect(second.echo, reconnect)

    first.fire()
    second.fire()
    expect(reconnect).not.to.have.been.called

    first.fire()
    expect(reconnect).to.have.been.calledOnce

    second.fire()
    expect(reconnect).to.have.been.calledTwice
  })
})

describe('realtime()', () => {
  beforeEach(() => {
    document.querySelector('[data-cy-root]').id = 'app'
  })

  afterEach(() => {
    document.querySelector('#app')?.removeAttribute('data-reverb')
  })

  it('is disabled without Reverb configuration', () => {
    expect(realtime()).to.equal(false)
  })

  it('is enabled with Reverb configuration', () => {
    document.querySelector('#app').dataset.reverb = '{"key":"test"}'
    expect(realtime()).to.equal(true)
  })
})

describe('subscribe()', () => {
  beforeEach(() => {
    document.querySelector('[data-cy-root]').id = 'app'
    document.querySelector('#app').dataset.reverb = '{"key":"test","host":"127.0.0.1","port":1,"scheme":"http"}'
  })

  afterEach(() => {
    document.querySelector('#app')?.removeAttribute('data-reverb')
    return disconnect()
  })

  it('does not subscribe on a connection closed while the libraries were loading', () => {
    const pending = subscribe('page', cy.stub())

    return disconnect().then(() => pending).then((release) => {
      expect(release).to.be.null
    })
  })

  it('does not count releases of a closed connection for the current one', () => {
    let stale

    return subscribe('page', cy.stub())
      .then((release) => {
        stale = release
        return disconnect()
      })
      .then(() => subscribe('page', cy.stub()))
      .then((current) => {
        const timeout = cy.spy(window, 'setTimeout')
        const idle = () => timeout.getCalls().filter((call) => call.args[1] === 5 * 60 * 1000)

        stale()
        expect(idle()).to.have.length(0)

        current()
        expect(idle()).to.have.length(1)
      })
  })
})

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

  it('list actions extend patch actions with the structural and variant ones', () => {
    expect(LIST_ACTIONS).to.deep.equal([
      'saved', 'published', 'restored', 'dropped', 'added', 'moved', 'purged', 'translated', 'source', 'ignored',
    ])
  })

  it('the reconnect sentinel is not a real action', () => {
    expect(PATCH_ACTIONS).to.not.include(RECONNECT)
    expect(LIST_ACTIONS).to.not.include(RECONNECT)
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

  it('keeps notifying subscribers when one throws', () => {
    const calls = []
    const a = () => { throw new Error('boom') }
    const b = (event, name) => calls.push(['b', event, name])

    resync([a, b])

    expect(calls).to.deep.equal([['b', null, RECONNECT]])
  })
})

describe('resubscribe()', () => {
  it('subscribes again to the channels whose authorization failed', () => {
    const pusher = {
      allChannels: () => [
        { name: 'private-cms.page', subscribed: true },
        { name: 'private-cms.file', subscribed: false },
      ],
      subscribe: cy.stub(),
    }

    resubscribe({ connector: { pusher } })

    expect(pusher.subscribe).to.have.been.calledOnceWith('private-cms.file')
  })

  it('does nothing without a connection', () => {
    expect(() => resubscribe(null)).not.to.throw()
  })
})

describe('Echo lifecycle', () => {
  const flush = () => new Promise((done) => setTimeout(done))

  it('returns a disposer that runs the subscription cleanup once', () => {
    const cleanup = cy.stub()
    const dispose = setupEcho('page', () => {}, LIST_ACTIONS, () => Promise.resolve(cleanup))

    return flush().then(() => {
      expect(cleanup).not.to.have.been.called

      dispose()
      dispose()
      expect(cleanup).to.have.been.calledOnce
    })
  })

  it('owns several typed subscriptions as one lifecycle', () => {
    const callbacks = {}
    const cleanups = { element: cy.stub(), file: cy.stub() }
    const connect = cy.stub().callsFake((type, callback) => {
      callbacks[type] = callback
      return Promise.resolve(cleanups[type])
    })
    const onEvent = cy.stub()
    const dispose = setupEcho(['element', 'file'], onEvent, LIST_ACTIONS, connect)

    return flush().then(() => {
      callbacks.file({ id: 'file-1' }, 'saved')
      expect(onEvent).to.have.been.calledWith({ id: 'file-1' }, 'saved', 'file')

      dispose()
      expect(cleanups.element).to.have.been.calledOnce
      expect(cleanups.file).to.have.been.calledOnce
    })
  })

  it('cleans successful typed subscriptions when another one fails', () => {
    const cleanup = cy.stub()
    const warning = cy.stub(console, 'warn')
    const connect = cy.stub().callsFake((type) => type === 'element'
      ? Promise.resolve(cleanup)
      : Promise.reject(new Error('denied'))
    )
    const dispose = setupEcho(['element', 'file'], () => {}, LIST_ACTIONS, connect)

    return flush().then(() => {
      expect(cleanup).to.have.been.calledOnce
      expect(warning).to.have.been.calledOnce

      dispose()
      expect(cleanup).to.have.been.calledOnce
    })
  })

  it('cleans a pending subscription disposed before it resolves', () => {
    const cleanup = cy.stub()
    let resolve

    const dispose = setupEcho('page', () => {}, LIST_ACTIONS, () => new Promise((done) => { resolve = done }))

    dispose()
    expect(cleanup).not.to.have.been.called
    resolve(cleanup)

    return flush().then(() => {
      expect(cleanup).to.have.been.calledOnce

      dispose()
      expect(cleanup).to.have.been.calledOnce
    })
  })

  it('reloads a detail view debounced and cancels it on dispose', () => {
    const wait = () => new Promise((done) => setTimeout(done, 400))
    const reload = cy.stub()
    let callback

    const connect = (type, cb) => { callback = cb; return Promise.resolve(null) }
    const dispose = setupReload('page', 'page-1', reload, () => true, connect)

    callback({ id: 'other' }, 'saved')
    callback({ id: 'page-1' }, 'saved')
    callback({ ids: ['page-1'] }, 'translated')

    return wait().then(() => {
      expect(reload).to.have.been.calledOnce

      callback(null, RECONNECT)
      dispose()
      return wait()
    }).then(() => {
      expect(reload).to.have.been.calledOnce
    })
  })
})

describe('joinChannel()', () => {
  it('leaves the channel when the last subscriber is released', () => {
    const echo = { leave: cy.stub() }
    const refs = new Map()
    const first = joinChannel(echo, 'cms.page', refs)
    const second = joinChannel(echo, 'cms.page', refs)

    first()
    first()
    expect(echo.leave).not.to.have.been.called

    second()
    expect(echo.leave).to.have.been.calledOnceWith('cms.page')
    expect(refs.size).to.equal(0)
  })

  it('ignores releases after the counts were cleared', () => {
    const echo = { leave: cy.stub() }
    const refs = new Map()
    const old = joinChannel(echo, 'cms.page', refs)

    refs.clear()
    const current = joinChannel(echo, 'cms.page', refs)
    old()

    expect(echo.leave).not.to.have.been.called
    expect(refs.get('cms.page').count).to.equal(1)

    current()
    expect(echo.leave).to.have.been.calledOnce
  })
})
