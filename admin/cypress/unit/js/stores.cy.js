import { createPinia, setActivePinia } from 'pinia'
import { effectScope, nextTick } from 'vue'
import {
  useAppStore,
  useChangeStore,
  useUserStore,
  useClipboardStore,
  useDirtyStore,
  useDrawerStore,
  useLanguageStore,
  useMessageStore,
  useSchemaStore,
  useSideStore,
  useTranslationStore,
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

  describe('setting()', () => {
    it('returns the stored value or the default', () => {
      const user = useUserStore()
      user.me = { settings: { page: { sort: { column: 'ID' } } } }
      const scope = effectScope()
      scope.run(() => {
        expect(user.setting('page', 'sort', { column: 'LFT' }).value).to.deep.equal({ column: 'ID' })
        expect(user.setting('file', 'sort', { column: 'LFT' }).value).to.deep.equal({ column: 'LFT' })
      })
      scope.stop()
    })

    it('saves replaced values until the scope stops', async () => {
      const user = useUserStore()
      user.me = { settings: {} }
      const scope = effectScope()
      const sort = scope.run(() => user.setting('schema', 'sort', { column: 'POSITION' }))

      sort.value = { column: 'NAME' }
      await nextTick()
      expect(user.me.settings.schema.sort).to.deep.equal({ column: 'NAME' })

      scope.stop()
      user.me.settings = {}
      sort.value = { column: 'POSITION' }
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

  describe('relogin()', () => {
    it('renews the session without resetting the state and releases waiting requests', () => {
      cy.stub(window, 'fetch').resolves({ ok: true, json: () => Promise.resolve({}) })
      const mutate = cy.stub(apolloClient, 'mutate').resolves({ data: { cmsLogin: { id: 'user-1' } } })
      cy.stub(apolloClient, 'query').resolves({
        data: { me: { email: 'editor@example.com', permission: '{"page:save":true}', settings: '{}', token: '' } }
      })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: { page: { sort: 'name' } } }
      const clear = cy.stub(user, 'clear').resolves()
      const waiting = user.reauth()

      expect(user.expired).to.equal(true)

      return user.relogin('secret').then(() => waiting).then(() => {
        expect(user.expired).to.equal(false)
        expect(clear).not.to.have.been.called
        expect(user.me.permission).to.deep.equal({ 'page:save': true })
        expect(user.me.settings).to.deep.equal({ page: { sort: 'name' } })
        expect(mutate.firstCall.args[0].variables).to.deep.equal({ email: 'editor@example.com', password: 'secret' })
        expect(mutate.firstCall.args[0].context).to.deep.equal({ relogin: true })
      })
    })

    it('keeps the dialog open if the login fails', () => {
      cy.stub(window, 'fetch').resolves({ ok: true, json: () => Promise.resolve({}) })
      cy.stub(apolloClient, 'mutate').rejects(new Error('Invalid credentials'))

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      user.reauth().catch(() => {})

      return user.relogin('wrong').then(
        () => { throw new Error('relogin unexpectedly resolved') },
        (error) => {
          expect(error.message).to.equal('Invalid credentials')
          expect(user.expired).to.equal(true)
        }
      )
    })
  })

  describe('check()', () => {
    afterEach(() => useUserStore().clear())

    it('asks to sign in again if the session expired', () => {
      cy.stub(apolloClient, 'query').rejects({ networkError: { statusCode: 419 } })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return user.check().then((valid) => {
        expect(valid).to.equal(false)
        expect(user.expired).to.equal(true)
      })
    })

    it('asks to sign in again if nobody is signed in anymore', () => {
      cy.stub(apolloClient, 'query').resolves({ data: { me: null } })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return user.check().then((valid) => {
        expect(valid).to.equal(false)
        expect(user.expired).to.equal(true)
      })
    })

    it('keeps the session if it is still valid', () => {
      cy.stub(apolloClient, 'query').resolves({ data: { me: { email: 'editor@example.com' } } })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return user.check().then((valid) => {
        expect(valid).to.equal(true)
        expect(user.expired).to.equal(false)
      })
    })

    it('ignores network failures', () => {
      cy.stub(apolloClient, 'query').rejects({ networkError: { statusCode: 503 } })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return user.check().then((valid) => {
        expect(valid).to.equal(true)
        expect(user.expired).to.equal(false)
      })
    })

    it('does nothing if nobody is signed in', () => {
      const query = cy.stub(apolloClient, 'query')

      return useUserStore().check().then(() => {
        expect(query).not.to.have.been.called
      })
    })
  })

  describe('resume()', () => {
    // release the module-level re-login listeners and waiting requests between tests
    afterEach(() => useUserStore().clear())

    it('continues when the same user signed in again in another tab', () => {
      cy.stub(apolloClient, 'query').resolves({
        data: { me: { email: 'editor@example.com', permission: '{}', settings: '{}', token: '' } }
      })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      const waiting = user.reauth()

      window.dispatchEvent(new Event('focus'))

      return waiting.then(() => {
        expect(user.expired).to.equal(false)
      })
    })

    it('stays expired if another user signed in', () => {
      cy.stub(apolloClient, 'query').resolves({
        data: { me: { email: 'other@example.com', permission: '{}', settings: '{}', token: '' } }
      })

      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      user.reauth().catch(() => {})

      return user.resume().then((resumed) => {
        expect(resumed).to.equal(false)
        expect(user.expired).to.equal(true)
      })
    })

    it('does nothing while the session is valid', () => {
      const query = cy.stub(apolloClient, 'query')
      const user = useUserStore()

      return user.resume().then((resumed) => {
        expect(resumed).to.equal(false)
        expect(query).not.to.have.been.called
      })
    })
  })

  describe('expire()', () => {
    it('rejects waiting requests and resets the state', () => {
      const user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      cy.stub(apolloClient, 'clearStore').resolves()
      const waiting = user.reauth()

      return user.expire().then(() => waiting).then(
        () => { throw new Error('waiting request unexpectedly resolved') },
        () => {
          expect(user.me).to.equal(false)
          expect(user.expired).to.equal(false)
        }
      )
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



describe('useLanguageStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns the first available locale as default', () => {
    const languages = useLanguageStore()
    languages.available = ['de', 'en']
    expect(languages.default()).to.equal('de')
  })

  it('falls back to en without available locales', () => {
    const languages = useLanguageStore()
    languages.available = []
    expect(languages.default()).to.equal('en')
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

  it('adds a message with an action', () => {
    const msg = useMessageStore()
    const handler = cy.stub()

    msg.add('Moved', 'success', null, { label: 'Undo', handler })
    const id = msg.queue[0]['data-action']

    expect(msg.queue[0].timeout).to.equal(8000)
    expect(msg.action(id).label).to.equal('Undo')

    msg.run(id)
    expect(handler).to.have.been.calledOnce
    expect(msg.action(id)).to.equal(null)
  })

  it('drops the action when the message is dismissed', () => {
    const msg = useMessageStore()

    msg.add('Moved', 'success', null, { label: 'Undo', handler() {} })
    const item = msg.queue[0]

    item.onDismiss()
    expect(msg.action(item['data-action'])).to.equal(null)
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

describe('useTranslationStore', () => {
  const progressApollo = (progress) => ({
    query: cy.stub().callsFake(({ variables }) => Promise.resolve({ data: { translateProgress: progress[variables.batch] } }))
  })

  beforeEach(() => {
    sessionStorage.removeItem('cms-translations')
    setActivePinia(createPinia())
  })

  afterEach(() => {
    useTranslationStore().clear()
  })

  it('restores the batches of the session and resumes polling them', () => {
    sessionStorage.setItem('cms-translations', JSON.stringify([
      { id: 'b1', total: 2, done: 0, failed: 0, ids: ['p1'], langs: ['de'], success: 'Done' },
      { id: 'b2', total: 1, done: 0, failed: 0, ids: ['p2'], langs: ['fr'] }
    ]))

    const store = useTranslationStore()
    const apollo = progressApollo({ b1: { total: 2, done: 2, failed: 0 }, b2: { total: 1, done: 0, failed: 1 } })
    const add = cy.spy(useMessageStore(), 'add')
    const listener = cy.stub()
    store.listen(listener)

    expect(store.progress).to.deep.equal({ total: 3, done: 0, failed: 0 })
    expect(store.has('p1', 'de')).to.be.true
    expect(store.has('p1')).to.be.true
    expect(store.has('p1', 'fr')).to.be.false

    store.resume(apollo)

    cy.wrap(store).its('batches').should('have.length', 0)
    cy.then(() => {
      expect(store.progress).to.equal(null)
      expect(listener).to.have.been.calledTwice
      expect(listener).to.have.been.calledWithMatch({ id: 'b1', ids: ['p1'] }, { done: 2 })
      expect(add).to.have.been.calledWith('Done', 'success')
      expect(add).to.have.been.calledWithMatch(/1 translation failed/, 'error')
      expect(JSON.parse(sessionStorage.getItem('cms-translations'))).to.deep.equal([])
    })
  })

  it('polls each batch only once', () => {
    const store = useTranslationStore()
    const apollo = progressApollo({ b1: { total: 1, done: 1, failed: 0 } })

    const first = store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'] }, apollo)
    const second = store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'] }, apollo)

    expect(store.batches).to.have.length(1)

    return Promise.all([first, second]).then(([progress, same]) => {
      expect(progress).to.deep.include({ total: 1, done: 1 })
      expect(same).to.equal(progress)
      expect(apollo.query).to.have.been.calledOnce
    })
  })

  it('ignores invalid stored batches', () => {
    sessionStorage.setItem('cms-translations', JSON.stringify([{ total: 1 }, { id: 'b1', ids: 'p1' }, null]))

    const store = useTranslationStore()

    expect(store.batches).to.have.length(1)
    expect(store.batches[0]).to.deep.include({ id: 'b1', ids: [], langs: [] })
    expect(store.has('p1')).to.be.false
  })

  it('stops polling the batches when cleared', () => {
    const store = useTranslationStore()
    const apollo = progressApollo({ b1: { total: 2, done: 0, failed: 0 } })
    const listener = cy.stub()
    store.listen(listener)

    const polling = store.add({ id: 'b1', total: 2 }, { ids: ['p1'], langs: ['de'] }, apollo)
    store.clear()

    return polling.then((progress) => {
      expect(progress).to.equal(null)
      expect(store.batches).to.have.length(0)
      expect(listener).not.to.have.been.called
    })
  })
})
