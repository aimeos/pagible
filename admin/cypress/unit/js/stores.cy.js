import { createPinia, setActivePinia } from 'pinia'
import { effectScope, nextTick } from 'vue'
import {
  useAppStore,
  useChangeStore,
  useUserStore,
  useClipboardStore,
  useDirtyStore,
  useDrawerStore,
  useMessageStore,
  useSchemaStore,
  useSideStore,
  useViewStack,
} from '../../../js/stores'
import { apolloClient } from '../../../js/graphql'

describe('useUserStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('can()', () => {
    it('returns false when me is null', () => {
      const user = useUserStore()
      expect(user.can('page:view')).to.be.false
    })

    it('returns false when me has no matching permission', () => {
      const user = useUserStore()
      user.me = { permission: { 'file:view': true } }
      expect(user.can('page:view')).to.be.false
    })

    it('returns true when me has the exact permission', () => {
      const user = useUserStore()
      user.me = { permission: { 'page:view': true } }
      expect(user.can('page:view')).to.be.true
    })

    it('accepts an array and returns true if any matches', () => {
      const user = useUserStore()
      user.me = { permission: { 'file:view': true } }
      expect(user.can(['page:view', 'file:view'])).to.be.true
    })

    it('returns false when array has no matching permissions', () => {
      const user = useUserStore()
      user.me = { permission: { 'element:view': true } }
      expect(user.can(['page:view', 'file:view'])).to.be.false
    })

    it('returns false when permission value is falsy', () => {
      const user = useUserStore()
      user.me = { permission: { 'page:view': 0 } }
      expect(user.can('page:view')).to.be.false
    })
  })

  describe('clear()', () => {
    it('clears all session caches', () => {
      const clearStore = cy.stub(apolloClient, 'clearStore').resolves()
      const user = useUserStore()
      const app = useAppStore()
      const clip = useClipboardStore()
      const changes = useChangeStore()
      const dirty = useDirtyStore()
      const drawer = useDrawerStore()
      const schema = useSchemaStore()
      const side = useSideStore()
      const views = useViewStack()
      app.urlproxy = '/cmsproxy?token=old'
      clip.set('page', { id: 'page-1' })
      changes.changed = { page: [{ id: 'page-1' }] }
      dirty.dirty = true
      dirty.saveFn = () => {}
      drawer.nav = true
      schema.content = { text: {} }
      side.store = { text: 1 }
      views.stack = [{ component: {} }]
      user.saveTimer = setTimeout(() => {}, 10000)
      user.tokenTimer = setTimeout(() => {}, 10000)

      return user.clear().then(() => {
        expect(user.saveTimer).to.be.null
        expect(user.tokenTimer).to.be.null
        expect(app.urlproxy).to.include('url=')
        expect(app.urlproxy).not.to.include('token=old')
        expect(clip.$state).to.deep.equal({})
        expect(changes.changed).to.deep.equal({})
        expect(dirty.dirty).to.be.false
        expect(dirty.saveFn).to.be.null
        expect(drawer.nav).to.be.null
        expect(schema.content).to.deep.equal({})
        expect(side.store).to.deep.equal({})
        expect(views.stack).to.deep.equal([])
        expect(clearStore).to.have.been.calledOnce
      })
    })
  })

  describe('filter()', () => {
    it('merges the stored filter into the defaults', () => {
      const user = useUserStore()
      user.me = { settings: { page: { filter: { view: 'list' } } } }
      const scope = effectScope()
      const filter = scope.run(() => user.filter('page', { view: 'tree', lang: null }))
      expect(filter).to.deep.equal({ view: 'list', lang: null })
      scope.stop()
    })

    it('saves the filter on changes until the scope stops', async () => {
      const user = useUserStore()
      user.me = { settings: {} }
      const scope = effectScope()
      const filter = scope.run(() => user.filter('page', { view: 'tree' }))

      filter.view = 'list'
      await nextTick()
      expect(user.me.settings.page.filter).to.deep.equal({ view: 'list' })

      scope.stop()
      user.me.settings = {}
      filter.view = 'tree'
      await nextTick()
      expect(user.me.settings).to.deep.equal({})
      clearTimeout(user.saveTimer)
    })
  })

  describe('getData()', () => {
    it('returns defval when me is null', () => {
      const user = useUserStore()
      user.me = null
      expect(user.getData('page', 'filter')).to.be.null
    })

    it('returns defval when settings is null', () => {
      const user = useUserStore()
      user.me = { settings: null }
      expect(user.getData('page', 'filter', 'default')).to.equal('default')
    })

    it('returns defval when panel does not exist', () => {
      const user = useUserStore()
      user.me = { settings: {} }
      expect(user.getData('page', 'filter', 'fallback')).to.equal('fallback')
    })

    it('returns stored value', () => {
      const user = useUserStore()
      user.me = { settings: { page: { filter: { view: 'list' } } } }
      expect(user.getData('page', 'filter')).to.deep.equal({ view: 'list' })
    })
  })

  describe('login()', () => {
    it('clears the previous session before loading the authenticated user', () => {
      cy.stub(window, 'fetch').resolves({ ok: true, json: () => Promise.resolve({}) })
      cy.stub(apolloClient, 'mutate').resolves({ data: { cmsLogin: { id: 'user-1' } } })

      const user = useUserStore()
      const session = user.session
      const clear = cy.stub(user, 'clear').resolves()
      const authenticated = cy.stub(user, 'isAuthenticated').callsFake(() => {
        user.me = { email: 'editor@example.com', permission: {}, settings: {} }
        return Promise.resolve(true)
      })

      return user.login('editor@example.com', 'secret').then((result) => {
        expect(user.session).to.equal(session + 1)
        expect(clear).to.have.been.calledOnce
        expect(authenticated).to.have.been.calledOnceWith(true)
        expect(clear).to.have.been.calledBefore(authenticated)
        expect(result.email).to.equal('editor@example.com')
      })
    })
  })

  describe('saveData()', () => {
    it('creates settings structure when missing', () => {
      const user = useUserStore()
      user.me = {}
      user.saveData('page', 'filter', { view: 'list' })
      expect(user.me.settings.page.filter).to.deep.equal({ view: 'list' })
      clearTimeout(user._saveTimer)
    })

    it('does nothing when me is null', () => {
      const user = useUserStore()
      user.me = null
      user.saveData('page', 'filter', { view: 'list' })
      expect(user.me).to.be.null
    })

    it('sets a debounce timer', () => {
      const user = useUserStore()
      user.me = {}
      user.saveData('page', 'sort', { column: 'ID' })
      expect(user._saveTimer).to.not.be.null
      clearTimeout(user._saveTimer)
    })

    it('overwrites existing values', () => {
      const user = useUserStore()
      user.me = { settings: { page: { filter: { view: 'tree' } } } }
      user.saveData('page', 'filter', { view: 'list' })
      expect(user.me.settings.page.filter).to.deep.equal({ view: 'list' })
      clearTimeout(user._saveTimer)
    })
  })

  describe('flush()', () => {
    it('does nothing without pending timer', () => {
      const user = useUserStore()
      user.me = { settings: {} }
      user._saveTimer = null
      user.flush()
      expect(user._saveTimer).to.be.null
    })
  })

  describe('intended()', () => {
    it('stores and returns the intended URL', () => {
      const user = useUserStore()
      user.intended('/pages')
      expect(user.intended()).to.equal('/pages')
    })

    it('returns null when no URL has been set', () => {
      const user = useUserStore()
      expect(user.intended()).to.be.null
    })

    it('returns the URL when setting it', () => {
      const user = useUserStore()
      expect(user.intended('/files')).to.equal('/files')
    })
  })
})


describe('useClipboardStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns defval when key is not set', () => {
    const clip = useClipboardStore()
    expect(clip.get('foo')).to.be.null
    expect(clip.get('foo', 'fallback')).to.equal('fallback')
  })

  it('stores and retrieves a value', () => {
    const clip = useClipboardStore()
    clip.set('content', { type: 'text', data: 'hello' })
    expect(clip.get('content')).to.deep.equal({ type: 'text', data: 'hello' })
  })

  it('ignores set when key is not a string', () => {
    const clip = useClipboardStore()
    clip.set(123, 'value')
    expect(clip.get(123)).to.be.null
  })

  it('overwrites an existing value', () => {
    const clip = useClipboardStore()
    clip.set('key', 'first')
    clip.set('key', 'second')
    expect(clip.get('key')).to.equal('second')
  })
})


describe('useSchemaStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useSchemaStore().clear()
  })

  it('ignores a previous session load that finishes after clearing', () => {
    let resolve
    cy.stub(apolloClient, 'query').returns(new Promise((done) => { resolve = done }))

    const schema = useSchemaStore()
    const pending = schema.load()

    schema.clear()
    resolve({
      data: {
        schemas: [{ name: 'old', types: {}, content: { text: {} }, meta: {}, config: {} }]
      }
    })

    return pending.then(() => {
      expect(schema.themes).to.deep.equal({})
      expect(schema.content).to.deep.equal({})
    })
  })
})



describe('useDrawerStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('starts with nav and aside as null', () => {
    const drawer = useDrawerStore()
    expect(drawer.nav).to.be.null
    expect(drawer.aside).to.be.null
  })

  it('toggle() flips nav from null to true', () => {
    const drawer = useDrawerStore()
    drawer.toggle('nav')
    expect(drawer.nav).to.be.true
  })

  it('toggle() flips nav from true to false', () => {
    const drawer = useDrawerStore()
    drawer.toggle('nav')
    drawer.toggle('nav')
    expect(drawer.nav).to.be.false
  })

  it('toggles aside independently of nav', () => {
    const drawer = useDrawerStore()
    drawer.toggle('aside')
    expect(drawer.aside).to.be.true
    expect(drawer.nav).to.be.null
  })
})


describe('useMessageStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('starts with an empty queue', () => {
    const msg = useMessageStore()
    expect(msg.queue).to.have.length(0)
  })

  it('adds a message with default info type and 3000ms timeout', () => {
    const msg = useMessageStore()
    msg.add('Hello')
    expect(msg.queue).to.have.length(1)
    expect(msg.queue[0]).to.deep.include({
      text: 'Hello',
      color: 'info',
      timeout: 3000,
    })
  })

  it('adds an error message with 10000ms timeout', () => {
    const msg = useMessageStore()
    msg.add('Failure', 'error')
    expect(msg.queue[0]).to.deep.include({
      text: 'Failure',
      color: 'error',
      timeout: 10000,
    })
  })

  it('uses custom timeout when provided', () => {
    const msg = useMessageStore()
    msg.add('Quick', 'info', 500)
    expect(msg.queue[0].timeout).to.equal(500)
  })

  it('limits the queue to 10 messages', () => {
    const msg = useMessageStore()
    for (let i = 0; i < 12; i++) {
      msg.add(`msg-${i}`)
    }
    expect(msg.queue).to.have.length(10)
  })

  it('sets contentClass to text-pre-line', () => {
    const msg = useMessageStore()
    msg.add('test')
    expect(msg.queue[0].contentClass).to.equal('text-pre-line')
  })
})


describe('useSideStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('shown()', () => {
    it('returns true by default for a new key/what pair', () => {
      const side = useSideStore()
      expect(side.shown('type', 'heading')).to.be.true
    })

    it('returns the existing value after toggle', () => {
      const side = useSideStore()
      side.shown('type', 'heading')
      side.toggle('type', 'heading')
      expect(side.shown('type', 'heading')).to.be.false
    })

    it('handles multiple keys independently', () => {
      const side = useSideStore()
      side.shown('type', 'heading')
      side.shown('state', 'valid')
      side.toggle('type', 'heading')
      expect(side.shown('type', 'heading')).to.be.false
      expect(side.shown('state', 'valid')).to.be.true
    })
  })

  describe('toggle()', () => {
    it('creates the key group if it does not exist', () => {
      const side = useSideStore()
      side.toggle('newgroup', 'item')
      expect(side.show.newgroup.item).to.be.true
    })

    it('flips an existing value', () => {
      const side = useSideStore()
      side.shown('type', 'text') // initialises to true
      side.toggle('type', 'text')
      expect(side.show.type.text).to.be.false
      side.toggle('type', 'text')
      expect(side.show.type.text).to.be.true
    })
  })
})
