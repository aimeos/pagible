import { createPinia, setActivePinia } from 'pinia'
import { effectScope, isReactive, nextTick, reactive } from 'vue'
import {
  useAppStore,
  useChangeStore,
  useSessionStore,
  useUserStore,
  useClipboardStore,
  useDirtyStore,
  useDrawerStore,
  useLanguageStore,
  useMessageStore,
  useSchemaStore,
  useSettingStore,
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

  describe('filter()', () => {
    it('merges the stored filter into the defaults', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: { page: { filter: { view: 'list' } } } }
      const scope = effectScope()
      const filter = scope.run(() => settings.filter('page', { view: 'tree', lang: null }))
      expect(filter).to.deep.equal({ view: 'list', lang: null })
      scope.stop()
    })

    it('saves the filter on changes until the scope stops', async () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      const scope = effectScope()
      const filter = scope.run(() => settings.filter('page', { view: 'tree' }))

      filter.view = 'list'
      await nextTick()
      expect(user.me.settings.page.filter).to.deep.equal({ view: 'list' })

      scope.stop()
      user.me.settings = {}
      filter.view = 'tree'
      await nextTick()
      expect(user.me.settings).to.deep.equal({})
      clearTimeout(settings.saveTimer)
    })
  })

  describe('setting()', () => {
    it('returns the stored value or the default', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: { page: { sort: { column: 'ID' } } } }
      const scope = effectScope()
      scope.run(() => {
        expect(settings.setting('page', 'sort', { column: 'LFT' }).value).to.deep.equal({ column: 'ID' })
        expect(settings.setting('file', 'sort', { column: 'LFT' }).value).to.deep.equal({ column: 'LFT' })
      })
      scope.stop()
    })

    it('saves replaced values until the scope stops', async () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      const scope = effectScope()
      const sort = scope.run(() => settings.setting('schema', 'sort', { column: 'POSITION' }))

      sort.value = { column: 'NAME' }
      await nextTick()
      expect(user.me.settings.schema.sort).to.deep.equal({ column: 'NAME' })

      scope.stop()
      user.me.settings = {}
      sort.value = { column: 'POSITION' }
      await nextTick()
      expect(user.me.settings).to.deep.equal({})
      clearTimeout(settings.saveTimer)
    })
  })

  describe('getData()', () => {
    it('returns defval when me is null', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = null
      expect(settings.getData('page', 'filter')).to.be.null
    })

    it('returns defval when settings is null', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: null }
      expect(settings.getData('page', 'filter', 'default')).to.equal('default')
    })

    it('returns defval when panel does not exist', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      expect(settings.getData('page', 'filter', 'fallback')).to.equal('fallback')
    })

    it('returns stored value', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: { page: { filter: { view: 'list' } } } }
      expect(settings.getData('page', 'filter')).to.deep.equal({ view: 'list' })
    })
  })

  describe('saveData()', () => {
    it('creates settings structure when missing', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = {}
      settings.saveData('page', 'filter', { view: 'list' })
      expect(user.me.settings.page.filter).to.deep.equal({ view: 'list' })
      clearTimeout(settings.saveTimer)
    })

    it('does nothing when me is null', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = null
      settings.saveData('page', 'filter', { view: 'list' })
      expect(user.me).to.be.null
    })

    it('sets a debounce timer', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = {}
      settings.saveData('page', 'sort', { column: 'ID' })
      expect(settings.saveTimer).to.not.be.null
      clearTimeout(settings.saveTimer)
    })

    it('overwrites existing values', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: { page: { filter: { view: 'tree' } } } }
      settings.saveData('page', 'filter', { view: 'list' })
      expect(user.me.settings.page.filter).to.deep.equal({ view: 'list' })
      clearTimeout(settings.saveTimer)
    })
  })

  describe('flush()', () => {
    it('does nothing without pending timer', () => {
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveTimer = null
      settings.flush()
      expect(settings.saveTimer).to.be.null
    })
  })

  describe('clear()', () => {
    it('saves the pending changes while the user is loaded', () => {
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      settings.clear()
      expect(settings.saveTimer).to.be.null
      expect(mutate).to.have.been.calledOnce
      expect(JSON.parse(mutate.firstCall.args[0].variables.settings)).to.deep.equal({ page: { sort: 'name' } })
    })

    it('discards the pending changes without a user', () => {
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      user.me = null
      settings.clear()
      expect(settings.saveTimer).to.be.null
      expect(mutate).not.to.have.been.called
    })
  })

  describe('pagehide', () => {
    it('saves the pending changes with a request outliving the closed page', () => {
      const fetch = cy.stub(window, 'fetch').resolves(new Response('{}'))
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      window.dispatchEvent(new Event('pagehide'))
      expect(settings.saveTimer).to.be.null
      expect(mutate).not.to.have.been.called
      expect(fetch).to.have.been.calledOnce

      const [url, init] = fetch.firstCall.args
      const body = JSON.parse(init.body)
      expect(url).to.equal('/graphql')
      expect(init).to.include({ method: 'POST', keepalive: true, credentials: 'include' })
      expect(init.headers).to.include({ 'Content-Type': 'application/json' })
      expect(body.query).to.contain('setUser(settings: $settings)')
      expect(JSON.parse(body.variables.settings)).to.deep.equal({ page: { sort: 'name' } })
    })

    it('saves the pending changes when the page is hidden', () => {
      const fetch = cy.stub(window, 'fetch').resolves(new Response('{}'))
      cy.stub(document, 'visibilityState').get(() => 'hidden')
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      document.dispatchEvent(new Event('visibilitychange'))
      expect(settings.saveTimer).to.be.null
      expect(fetch).to.have.been.calledOnce
      expect(fetch.firstCall.args[1].keepalive).to.be.true
    })

    it('saves the changes with Apollo if the request can not be sent', () => {
      cy.stub(window, 'fetch').rejects(new TypeError('Request too large'))
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      window.dispatchEvent(new Event('pagehide'))
      cy.wrap(mutate).should('have.been.calledOnce')
    })

    it('saves the changes with Apollo if the request fails on the server', () => {
      cy.stub(window, 'fetch').resolves(new Response('', { status: 419 }))
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      window.dispatchEvent(new Event('pagehide'))
      cy.wrap(mutate).should('have.been.calledOnce')
      cy.then(() => {
        expect(JSON.parse(mutate.firstCall.args[0].variables.settings)).to.deep.equal({ page: { sort: 'name' } })
      })
    })

    it('saves the changes with Apollo if the request returns GraphQL errors', () => {
      cy.stub(window, 'fetch').resolves(new Response(JSON.stringify({ errors: [{ message: 'Unauthenticated.' }] })))
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      window.dispatchEvent(new Event('pagehide'))
      cy.wrap(mutate).should('have.been.calledOnce')
    })

    it('does not save the changes again after a successful request', () => {
      const fetch = cy.stub(window, 'fetch').resolves(new Response('{}'))
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      const user = useUserStore()
      const settings = useSettingStore()
      user.me = { settings: {} }
      settings.saveData('page', 'sort', 'name')
      window.dispatchEvent(new Event('pagehide'))
      expect(fetch).to.have.been.calledOnce

      // the response is handled after the fetch promise resolves
      cy.wrap(fetch.firstCall.returnValue).then(() => {
        expect(mutate).not.to.have.been.called
      })
    })

    it('does nothing without pending changes', () => {
      const fetch = cy.stub(window, 'fetch').resolves(new Response('{}'))
      const mutate = cy.stub(apolloClient, 'mutate').resolves({})
      useUserStore().me = { settings: {} }
      window.dispatchEvent(new Event('pagehide'))
      expect(mutate).not.to.have.been.called
      expect(fetch).not.to.have.been.called
    })
  })
})

describe('useSessionStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })
  describe('clear()', () => {
    it('clears all session caches', () => {
      const clearStore = cy.stub(apolloClient, 'clearStore').resolves()
      const session = useSessionStore()
      const settings = useSettingStore()
      const app = useAppStore()
      const clip = useClipboardStore()
      const changes = useChangeStore()
      const dirty = useDirtyStore()
      const drawer = useDrawerStore()
      const schema = useSchemaStore()
      const side = useSideStore()
      const views = useViewStack()
      const translations = useTranslationStore()
      app.urlproxy = '/cmsproxy?token=old'
      translations.batches = [{ id: 'b1', total: 1, done: 0, failed: 0 }]
      clip.set('page', { id: 'page-1' })
      changes.changed = { page: [{ id: 'page-1' }] }
      dirty.dirty = true
      dirty.saveFn = () => {}
      drawer.nav = true
      schema.content = { text: {} }
      side.store = { text: 1 }
      views.stack = [{ component: {} }]
      settings.saveTimer = setTimeout(() => {}, 10000)

      return session.clear().then(() => {
        expect(settings.saveTimer).to.be.null
        expect(app.urlproxy).to.include('url=')
        expect(app.urlproxy).not.to.include('token=old')
        expect(clip.get('page')).to.be.null
        expect(changes.changed).to.deep.equal({})
        expect(dirty.dirty).to.be.false
        expect(dirty.saveFn).to.be.null
        expect(drawer.nav).to.be.null
        expect(schema.content).to.deep.equal({})
        expect(side.store).to.deep.equal({})
        expect(views.stack).to.deep.equal([])
        expect(translations.batches).to.deep.equal([])
        expect(clearStore).to.have.been.calledOnce
      })
    })
  })

  describe('applyProxyToken()', () => {
    it('refreshes the token before it expires', () => {
      const session = useSessionStore()
      const refresh = cy.stub(session, 'refreshToken')

      cy.clock(Date.now()).then(() => {
        useUserStore().me = { token: btoa(`${Math.floor(Date.now() / 1000) + 120}|uid|hmac`) }
        session.applyProxyToken()
      })
      cy.tick(59000).then(() => expect(refresh).not.to.have.been.called)
      cy.tick(2000).then(() => expect(refresh).to.have.been.calledOnce)
    })

    it('ignores tokens with a malformed expiry', () => {
      const session = useSessionStore()
      const refresh = cy.stub(session, 'refreshToken')

      cy.clock(Date.now()).then(() => {
        useUserStore().me = { token: btoa('soon|uid|hmac') }
        session.applyProxyToken()
      })
      cy.tick(60000).then(() => expect(refresh).not.to.have.been.called)
    })

    it('cancels the refresh when the session is cleared', () => {
      cy.stub(apolloClient, 'clearStore').resolves()
      const session = useSessionStore()
      const refresh = cy.stub(session, 'refreshToken')

      cy.clock(Date.now()).then(() => {
        useUserStore().me = { token: btoa(`${Math.floor(Date.now() / 1000) + 120}|uid|hmac`) }
        session.applyProxyToken()
        return session.clear()
      })
      cy.tick(120000).then(() => expect(refresh).not.to.have.been.called)
    })
  })

  describe('refreshToken()', () => {
    it('retries with a growing delay after failures', () => {
      const query = cy.stub(apolloClient, 'query').rejects(new TypeError('Failed to fetch'))
      cy.stub(apolloClient, 'clearStore').resolves()
      cy.stub(console, 'error')
      const session = useSessionStore()

      cy.clock(Date.now()).then(() => {
        useUserStore().me = { token: null }
        session.refreshToken()
      })
      cy.tick(30000).then(() => expect(query).to.have.been.calledTwice)
      cy.tick(30000).then(() => expect(query).to.have.been.calledTwice)
      cy.tick(30000).then(() => {
        expect(query).to.have.been.calledThrice
        return session.clear()
      })
    })

    it('ignores the new token after signing out', () => {
      let respond
      cy.stub(apolloClient, 'query').returns(new Promise((resolve) => (respond = resolve)))
      const error = cy.stub(console, 'error')
      const user = useUserStore()

      user.me = { token: null }
      useSessionStore().refreshToken()
      user.me = null
      respond({ data: { me: { token: 'new' } } })

      cy.wrap(null).then(() => {
        expect(user.me).to.be.null
        expect(error).not.to.have.been.called
      })
    })
  })

  describe('isAuthenticated()', () => {
    it('stays unknown if the server is not reachable', () => {
      cy.stub(apolloClient, 'query').rejects(new TypeError('Failed to fetch'))
      cy.stub(console, 'error')
      const session = useSessionStore()
      const user = useUserStore()
      user.me = null

      return session.isAuthenticated().then(() => {
        throw new Error('not rejected')
      }, (error) => {
        expect(error.message).to.equal('Failed to fetch')
        expect(user.me).to.be.null
      })
    })

    it('signs out if the session is rejected', () => {
      cy.stub(apolloClient, 'query').rejects({ networkError: { statusCode: 401 } })
      cy.stub(console, 'error')
      const user = useUserStore()
      user.me = null

      return useSessionStore().isAuthenticated().then((result) => {
        expect(result).to.be.false
        expect(user.me).to.be.false
      })
    })
  })

  describe('logout()', () => {
    it('saves the pending settings before signing out', () => {
      const mutate = cy.stub(apolloClient, 'mutate').resolves({ data: { cmsLogout: { email: 'a@b.c' } } })
      cy.stub(apolloClient, 'clearStore').resolves()
      const session = useSessionStore()
      useUserStore().me = { settings: {} }
      useSettingStore().saveData('page', 'sort', 'name')

      return session.logout().then(() => {
        expect(mutate).to.have.been.calledTwice
        expect(mutate.firstCall.args[0].variables.settings).to.equal(JSON.stringify({ page: { sort: 'name' } }))
      })
    })
  })

  describe('login()', () => {
    it('clears the previous session before loading the authenticated user', () => {
      cy.stub(window, 'fetch').resolves({ ok: true, json: () => Promise.resolve({}) })
      cy.stub(apolloClient, 'mutate').resolves({ data: { cmsLogin: { id: 'user-1' } } })

      const user = useUserStore()
      const session = useSessionStore()
      const id = session.id
      const clear = cy.stub(session, 'clear').resolves()
      const authenticated = cy.stub(session, 'isAuthenticated').callsFake(() => {
        user.me = { email: 'editor@example.com', permission: {}, settings: {} }
        return Promise.resolve(true)
      })

      return session.login('editor@example.com', 'secret').then((result) => {
        expect(session.id).to.equal(id + 1)
        expect(clear).to.have.been.calledOnce
        expect(authenticated).to.have.been.calledOnceWith(true)
        expect(clear).to.have.been.calledBefore(authenticated)
        expect(result.email).to.equal('editor@example.com')
      })
    })

    it('keeps the session state unknown if the user check fails after signing in', () => {
      cy.stub(window, 'fetch').resolves({ ok: true, json: () => Promise.resolve({}) })
      cy.stub(apolloClient, 'mutate').resolves({ data: { cmsLogin: { id: 'user-1' } } })

      const user = useUserStore()
      const session = useSessionStore()
      cy.stub(session, 'clear').resolves()
      cy.stub(session, 'isAuthenticated').rejects(new TypeError('Failed to fetch'))

      return session.login('editor@example.com', 'secret').then(() => {
        throw new Error('not rejected')
      }, (error) => {
        expect(error.message).to.equal('Failed to fetch')
        expect(user.me).to.be.null
      })
    })

    it('signs out only if the sign-in is rejected by the server', () => {
      cy.stub(window, 'fetch').resolves({ ok: true, json: () => Promise.resolve({}) })
      const mutate = cy.stub(apolloClient, 'mutate').rejects({ networkError: new TypeError('Failed to fetch') })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = null

      return session.login('editor@example.com', 'secret').catch(() => {
        expect(user.me).to.be.null

        mutate.rejects(new Error('Invalid credentials'))
        return session.login('editor@example.com', 'wrong')
      }).catch((error) => {
        expect(error.message).to.equal('Invalid credentials')
        expect(user.me).to.be.false
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
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: { page: { sort: 'name' } } }
      const clear = cy.stub(session, 'clear').resolves()
      const waiting = session.reauth()

      expect(session.expired).to.equal(true)

      return session.relogin('secret').then(() => waiting).then(() => {
        expect(session.expired).to.equal(false)
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
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      session.reauth().catch(() => {})

      return session.relogin('wrong').then(
        () => { throw new Error('relogin unexpectedly resolved') },
        (error) => {
          expect(error.message).to.equal('Invalid credentials')
          expect(session.expired).to.equal(true)
        }
      )
    })
  })

  describe('check()', () => {
    afterEach(() => useSessionStore().clear())

    it('asks to sign in again if the session expired', () => {
      cy.stub(apolloClient, 'query').rejects({ networkError: { statusCode: 419 } })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return session.check().then((valid) => {
        expect(valid).to.equal(false)
        expect(session.expired).to.equal(true)
      })
    })

    it('asks to sign in again if nobody is signed in anymore', () => {
      cy.stub(apolloClient, 'query').resolves({ data: { me: null } })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return session.check().then((valid) => {
        expect(valid).to.equal(false)
        expect(session.expired).to.equal(true)
      })
    })

    it('keeps the session if it is still valid', () => {
      cy.stub(apolloClient, 'query').resolves({ data: { me: { email: 'editor@example.com' } } })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return session.check().then((valid) => {
        expect(valid).to.equal(true)
        expect(session.expired).to.equal(false)
      })
    })

    it('ignores network failures', () => {
      cy.stub(apolloClient, 'query').rejects({ networkError: { statusCode: 503 } })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }

      return session.check().then((valid) => {
        expect(valid).to.equal(true)
        expect(session.expired).to.equal(false)
      })
    })

    it('does nothing if nobody is signed in', () => {
      const query = cy.stub(apolloClient, 'query')

      return useSessionStore().check().then(() => {
        expect(query).not.to.have.been.called
      })
    })
  })

  describe('resume()', () => {
    // release the module-level re-login listeners and waiting requests between tests
    afterEach(() => useSessionStore().clear())

    it('continues when the same user signed in again in another tab', () => {
      cy.stub(apolloClient, 'query').resolves({
        data: { me: { email: 'editor@example.com', permission: '{}', settings: '{}', token: '' } }
      })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      const waiting = session.reauth()

      window.dispatchEvent(new Event('focus'))

      return waiting.then(() => {
        expect(session.expired).to.equal(false)
      })
    })

    it('stays expired if another user signed in', () => {
      cy.stub(apolloClient, 'query').resolves({
        data: { me: { email: 'other@example.com', permission: '{}', settings: '{}', token: '' } }
      })

      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      session.reauth().catch(() => {})

      return session.resume().then((resumed) => {
        expect(resumed).to.equal(false)
        expect(session.expired).to.equal(true)
      })
    })

    it('does nothing while the session is valid', () => {
      const query = cy.stub(apolloClient, 'query')
      const session = useSessionStore()

      return session.resume().then((resumed) => {
        expect(resumed).to.equal(false)
        expect(query).not.to.have.been.called
      })
    })
  })

  describe('expire()', () => {
    it('rejects waiting requests and resets the state', () => {
      const user = useUserStore()
      const session = useSessionStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      cy.stub(apolloClient, 'clearStore').resolves()
      const waiting = session.reauth()

      return session.expire().then(() => waiting).then(
        () => { throw new Error('waiting request unexpectedly resolved') },
        () => {
          expect(user.me).to.equal(false)
          expect(session.expired).to.equal(false)
        }
      )
    })

    it('removes the messages and undo actions of the previous user', () => {
      const session = useSessionStore()
      const messages = useMessageStore()
      const handler = cy.stub()
      useUserStore().me = { email: 'editor@example.com', permission: {}, settings: {} }
      cy.stub(apolloClient, 'clearStore').resolves()

      messages.add('Page deleted', 'success', null, { label: 'Undo', handler })
      const id = messages.queue[0]['data-action']

      return session.expire().then(() => {
        expect(messages.queue).to.have.length(0)
        expect(messages.action(id)).to.equal(null)
        messages.run(id)
        expect(handler).not.to.have.been.called
      })
    })
  })

  describe('intended()', () => {
    it('stores and returns the intended URL', () => {
      const session = useSessionStore()
      session.intended('/pages')
      expect(session.intended()).to.equal('/pages')
    })

    it('returns null when no URL has been set', () => {
      const session = useSessionStore()
      expect(session.intended()).to.be.null
    })

    it('returns the URL when setting it', () => {
      const session = useSessionStore()
      expect(session.intended('/files')).to.equal('/files')
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

  it('removes all entries on reset', () => {
    const clip = useClipboardStore()
    clip.set('page-content', [{ id: 'a' }])
    clip.$reset()
    expect(clip.get('page-content')).to.be.null
  })

  it('does not overwrite store methods', () => {
    const clip = useClipboardStore()
    clip.set('get', 'value')
    expect(clip.get('get')).to.equal('value')
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

  it('clears the queued messages and their actions', () => {
    const msg = useMessageStore()

    msg.add('Info')
    msg.add('Moved', 'success', null, { label: 'Undo', handler: cy.stub() })
    const id = msg.queue[1]['data-action']

    msg.clear()
    expect(msg.queue).to.have.length(0)
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


describe('useDirtyStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('dismisses the previous confirmation', () => {
    const dirty = useDirtyStore()
    const first = dirty.confirm(() => {})
    const second = dirty.confirm(() => {})

    return first.then((value) => {
      expect(value).to.be.false
      expect(dirty.show).to.be.true
      expect(dirty.pendingResolve).to.be.a('function')
      dirty.cancel()
      return second
    }).then((value) => {
      expect(value).to.be.false
    })
  })

  it('runs the action and resolves true when finalized', () => {
    const dirty = useDirtyStore()
    const action = cy.stub().resolves()
    const confirmed = dirty.confirm(action)

    return dirty.finalize().then(() => confirmed).then((value) => {
      expect(action).to.have.been.calledOnce
      expect(value).to.be.true
      expect(dirty.pendingAction).to.be.null
    })
  })

  it('resolves false if the action fails', () => {
    const dirty = useDirtyStore()
    const confirmed = dirty.confirm(() => Promise.reject(new Error('failed')))
    const log = cy.stub(console, 'error')

    return dirty.finalize().then(() => {
      expect(log).to.have.been.calledWith('Pending action failed')
      return confirmed
    }).then((value) => {
      expect(value).to.be.false
      expect(dirty.pendingResolve).to.be.null
    })
  })

  it('restores the state of the view below when a stacked view unregisters', () => {
    const dirty = useDirtyStore()
    const parent = () => {}
    const child = () => {}

    dirty.register(parent)
    dirty.set(true, parent)
    dirty.register(child)
    expect(dirty.saveFn).to.equal(child)
    expect(dirty.dirty).to.be.true

    dirty.set(true, child)
    dirty.set(false, child)
    expect(dirty.dirty).to.be.true

    dirty.unregister(child)
    dirty.unregister(child)
    expect(dirty.saveFn).to.equal(parent)
    expect(dirty.entries).to.have.length(1)
    expect(dirty.dirty).to.be.true
  })

  it('resolves true if the action unregisters the view', () => {
    const dirty = useDirtyStore()
    const save = () => {}
    dirty.register(save)
    dirty.set(true, save)

    const confirmed = dirty.confirm(() => dirty.unregister(save))

    return dirty.finalize().then(() => confirmed).then((value) => {
      expect(value).to.be.true
      expect(dirty.dirty).to.be.false
      expect(dirty.saveFn).to.be.null
    })
  })

  it('saves only the view in front for a top confirmation', () => {
    const dirty = useDirtyStore()
    const parent = cy.stub().resolves(true)
    const child = cy.stub().resolves(true)
    dirty.register(parent)
    dirty.set(true, parent)
    dirty.register(child)
    dirty.set(true, child)

    const confirmed = dirty.confirm(null, true)

    return dirty.saveAndLeave().then(() => confirmed).then((value) => {
      expect(value).to.be.true
      expect(child).to.have.been.calledOnce
      expect(parent).not.to.have.been.called
      expect(dirty.entries[0].dirty).to.be.true
      expect(dirty.dirty).to.be.true
    })
  })

  it('keeps the confirmation open if saving fails', () => {
    const dirty = useDirtyStore()
    const save = cy.stub().resolves(false)
    dirty.register(save)
    dirty.set(true, save)
    dirty.confirm()

    return dirty.saveAndLeave().then(() => {
      expect(dirty.show).to.be.true
      expect(dirty.dirty).to.be.true
    })
  })
  it('keeps the confirmation open if saving fails without a result', () => {
    const dirty = useDirtyStore()
    const save = cy.stub().resolves(undefined)
    dirty.register(save)
    dirty.set(true, save)
    dirty.confirm()

    return dirty.saveAndLeave().then(() => {
      expect(dirty.show).to.be.true
      expect(dirty.dirty).to.be.true
    })
  })
})

describe('useChangeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('keeps the notified item reactive and stores a copy', () => {
    const changes = useChangeStore()
    const state = reactive({ item: { id: '1', name: 'old' } })

    changes.notify('page', state.item)
    state.item.name = 'new'

    // nested objects are wrapped again on access, which marking them raw would prevent
    expect(isReactive(state.item)).to.be.true
    expect(changes.get('page')).to.have.length(1)
    expect(changes.get('page')[0].name).to.equal('old')
  })
})

describe('useViewStack', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('closes clean views without confirmation', () => {
    const views = useViewStack()
    const dirty = useDirtyStore()
    const confirm = cy.spy(dirty, 'confirm')
    const parent = () => {}
    dirty.register(parent)
    dirty.set(true, parent)
    dirty.register(() => {})
    views.openView({ name: 'A' })

    return views.closeView().then((value) => {
      expect(value).to.be.true
      expect(confirm).not.to.have.been.called
      expect(views.stack).to.have.length(0)
    })
  })

  it('asks before closing a view with unsaved changes', () => {
    const views = useViewStack()
    const dirty = useDirtyStore()
    const child = () => {}
    dirty.register(() => {})
    dirty.register(child)
    dirty.set(true, child)
    views.openView({ name: 'A' })

    const closed = views.closeView()
    expect(dirty.show).to.be.true
    expect(dirty.pendingTop).to.be.true

    return dirty.finalize().then(() => closed).then((value) => {
      expect(value).to.be.true
      expect(views.stack).to.have.length(0)
    })
  })

  it('removes only the oldest views without unsaved changes', () => {
    const views = useViewStack()
    const dirty = useDirtyStore()
    const saves = [() => {}, () => {}, () => {}, () => {}]
    saves.forEach((save) => dirty.register(save))
    ;['A', 'B', 'C'].forEach((name) => views.openView({ name }))
    dirty.set(true, saves[1])

    views.openView({ name: 'D' })

    expect(views.stack.map((view) => view.component.name)).to.deep.equal(['A', 'C', 'D'])
  })

  it('removes the oldest view if nothing is unsaved', () => {
    const views = useViewStack()
    ;['A', 'B', 'C', 'D'].forEach((name) => views.openView({ name }))

    expect(views.stack.map((view) => view.component.name)).to.deep.equal(['B', 'C', 'D'])
  })

  it('keeps all views if their unsaved state is unknown', () => {
    const views = useViewStack()
    useDirtyStore().set(true)
    ;['A', 'B', 'C'].forEach((name) => views.openView({ name }))

    views.openView({ name: 'D' })

    expect(views.stack).to.have.length(4)
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

    it('does not change the state', () => {
      const side = useSideStore()
      side.shown('type', 'heading')
      expect(side.show).to.deep.equal({})
    })

    it('returns the existing value after toggle', () => {
      const side = useSideStore()
      side.toggle('type', 'heading')
      expect(side.shown('type', 'heading')).to.be.false
    })

    it('handles multiple keys independently', () => {
      const side = useSideStore()
      side.toggle('type', 'heading')
      expect(side.shown('type', 'heading')).to.be.false
      expect(side.shown('state', 'valid')).to.be.true
    })
  })

  describe('toggle()', () => {
    it('hides a new entry because entries are shown by default', () => {
      const side = useSideStore()
      side.toggle('newgroup', 'item')
      expect(side.show.newgroup.item).to.be.false
    })

    it('flips an existing value', () => {
      const side = useSideStore()
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

  it('retries failed progress requests without dropping the batch', () => {
    const store = useTranslationStore()
    const warn = cy.stub(console, 'warn')
    const query = cy.stub()
    query.onFirstCall().rejects(new Error('offline'))
    query.resolves({ data: { translateProgress: { total: 1, done: 1, failed: 0 } } })
    let polling

    cy.clock().then(() => {
      polling = store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'] }, { query })
    })
    cy.wrap(query).should('have.been.calledOnce')
    cy.wrap(warn).should('have.been.calledWithMatch', 'translation progress')
    cy.then(() => {
      expect(store.batches).to.have.length(1)
    })
    cy.tick(5000)
    cy.then(() => polling).then((progress) => {
      expect(query).to.have.been.calledTwice
      expect(progress).to.deep.include({ total: 1, done: 1 })
      expect(store.batches).to.have.length(0)
    })
  })

  it('keeps a batch re-added after clearing while the old poll finishes', () => {
    const store = useTranslationStore()
    let resolve
    const stale = { query: cy.stub().returns(new Promise((r) => { resolve = r })) }
    const fresh = { query: cy.stub().returns(new Promise(() => {})) }

    const old = store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'] }, stale)

    cy.wrap(stale.query).should('have.been.calledOnce').then(() => {
      store.clear()
      store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'] }, fresh)

      resolve({ data: { translateProgress: { total: 1, done: 1, failed: 0 } } })

      return old.then((progress) => {
        // the old poll stops without updating or reporting the re-added batch
        expect(progress).to.be.null
        expect(store.batches[0].done).to.equal(0)
        expect(store.batches).to.have.length(1)
        // the poll of the re-added batch is still registered, so no new query is started
        store.poll('b1', fresh)
        expect(fresh.query).to.have.been.calledOnce
      })
    })
  })

  it('drops unknown batches', () => {
    const store = useTranslationStore()
    const apollo = { query: cy.stub().resolves({ data: { translateProgress: null } }) }

    return store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'] }, apollo).then((progress) => {
      expect(progress).to.be.null
      expect(apollo.query).to.have.been.calledOnce
      expect(store.batches).to.have.length(0)
    })
  })

  it('logs failing listeners and still reports the batch', () => {
    const store = useTranslationStore()
    const warn = cy.stub(console, 'warn')
    const add = cy.spy(useMessageStore(), 'add')
    store.listen(() => { throw new Error('broken') })

    return store.add({ id: 'b1', total: 1 }, { ids: ['p1'], langs: ['de'], success: 'Done' }, progressApollo({ b1: { total: 1, done: 1, failed: 0 } })).then(() => {
      expect(warn).to.have.been.calledWithMatch('Translation listener failed')
      expect(add).to.have.been.calledWith('Done', 'success')
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
