import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import router, { guard } from '../../../js/routes'
import gettext, { ready } from '../../../js/i18n'
import { useDirtyStore, useMessageStore, useSessionStore, useUserStore } from '../../../js/stores'

describe('guard()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    gettext.translations = {}
    gettext.current = 'en'
  })

  it('translates raw route titles when catalogs change', async () => {
    await ready
    gettext.translations = { de: { Login: 'Anmeldung' } }
    gettext.current = 'de'

    await router.push({ path: '/', query: { test: 'translated-title' } })
    expect(document.title).to.equal('Anmeldung — PagibleAI CMS')

    gettext.translations = { de: { Login: 'Neue Anmeldung' } }
    await nextTick()

    expect(document.title).to.equal('Neue Anmeldung — PagibleAI CMS')
  })

  it('allows public routes without authentication or permission checks', async () => {
    const user = useUserStore()
    const isAuthenticated = cy.stub(useSessionStore(), 'isAuthenticated').resolves(false)
    const can = cy.stub(user, 'can').returns(false)

    const result = await guard({
      fullPath: '/public',
      matched: [{ meta: {} }],
      meta: {},
      name: 'public',
    })

    expect(result).to.equal(undefined)
    expect(isAuthenticated).not.to.have.been.called
    expect(can).not.to.have.been.called
  })

  it('redirects unauthenticated protected routes to login', async () => {
    const user = useUserStore()
    const intended = cy.stub(useSessionStore(), 'intended')
    cy.stub(useSessionStore(), 'isAuthenticated').resolves(false)

    const result = await guard({
      fullPath: '/pages',
      matched: [{ meta: { auth: true } }],
      meta: { auth: true },
      name: 'page:view',
    })

    expect(intended).to.have.been.calledOnceWith('/pages')
    expect(result).to.deep.equal({ name: 'login' })
  })

  it('stays on the current page if the server is not reachable', async () => {
    const intended = cy.stub(useSessionStore(), 'intended')
    cy.stub(useSessionStore(), 'isAuthenticated').rejects(new TypeError('Failed to fetch'))
    const to = { fullPath: '/pages', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'page:view' }

    expect(await guard(to, { matched: [{ meta: {} }] })).to.equal(false)
    expect(useMessageStore().queue.at(-1)?.text).to.equal('Connection failed, please try again')
    expect(intended).not.to.have.been.called

    // nothing to stay on when the admin is opened
    expect(await guard(to, { matched: [] })).to.deep.equal({ name: 'login' })
    expect(intended).to.have.been.calledOnceWith('/pages')
  })

  it('stays on the current page if the session check fails', async () => {
    const intended = cy.stub(useSessionStore(), 'intended')
    cy.stub(useSessionStore(), 'isAuthenticated').rejects(new Error('Too many requests'))
    const to = { fullPath: '/pages', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'page:view' }

    expect(await guard(to, { matched: [{ meta: {} }] })).to.equal(false)
    expect(useMessageStore().queue.at(-1)?.text).to.equal('Error checking the session, please try again')
    expect(intended).not.to.have.been.called
  })

  it('cancels the navigation when unsaved changes are kept', async () => {
    const dirty = useDirtyStore()
    dirty.dirty = true
    const confirm = cy.stub(dirty, 'confirm').resolves(false)
    cy.stub(useSessionStore(), 'isAuthenticated').resolves(true)
    cy.stub(useUserStore(), 'can').returns(true)

    const result = await guard({ fullPath: '/pages', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'page:view' })

    expect(confirm).to.have.been.calledOnce
    expect(result).to.equal(false)
  })

  it('keeps unsaved changes if the target page is not permitted', async () => {
    const dirty = useDirtyStore()
    dirty.dirty = true
    const confirm = cy.stub(dirty, 'confirm').resolves(true)
    cy.stub(useSessionStore(), 'isAuthenticated').resolves(true)
    const can = cy.stub(useUserStore(), 'can').returns(false)

    const result = await guard({ fullPath: '/access', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'access:view' })

    expect(can).to.have.been.calledWith('access:view')
    expect(confirm).not.to.have.been.called
    expect(result).to.equal(false)
  })

  it('keeps unsaved changes if the server is not reachable', async () => {
    const dirty = useDirtyStore()
    dirty.dirty = true
    const confirm = cy.stub(dirty, 'confirm').resolves(true)
    cy.stub(useSessionStore(), 'isAuthenticated').rejects(new TypeError('Failed to fetch'))

    const result = await guard({ fullPath: '/pages', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'page:view' }, { matched: [{ meta: {} }] })

    expect(confirm).not.to.have.been.called
    expect(result).to.equal(false)
  })

  it('asks for unsaved changes before leaving to public routes', async () => {
    const dirty = useDirtyStore()
    dirty.dirty = true
    const confirm = cy.stub(dirty, 'confirm').resolves(false)

    expect(await guard({ fullPath: '/login', matched: [{ meta: {} }], meta: {}, name: 'login' })).to.equal(false)
    expect(confirm).to.have.been.calledOnce
  })
})
