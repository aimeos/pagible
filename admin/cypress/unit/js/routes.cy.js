import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import router, { guard } from '../../../js/routes'
import gettext, { ready } from '../../../js/i18n'
import { useDirtyStore, useSessionStore, useUserStore } from '../../../js/stores'

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

  it('cancels the navigation when unsaved changes are kept', async () => {
    const dirty = useDirtyStore()
    dirty.dirty = true
    cy.stub(dirty, 'confirm').resolves(false)
    const isAuthenticated = cy.stub(useSessionStore(), 'isAuthenticated').resolves(true)

    const result = await guard({ fullPath: '/pages', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'page:view' })

    expect(result).to.equal(false)
    expect(isAuthenticated).not.to.have.been.called
  })

  it('checks the permission after unsaved changes are discarded', async () => {
    const dirty = useDirtyStore()
    dirty.dirty = true
    cy.stub(dirty, 'confirm').resolves(true)
    cy.stub(useSessionStore(), 'isAuthenticated').resolves(true)
    const can = cy.stub(useUserStore(), 'can').returns(false)

    const result = await guard({ fullPath: '/access', matched: [{ meta: { auth: true } }], meta: { auth: true }, name: 'access:view' })

    expect(can).to.have.been.calledWith('access:view')
    expect(result).to.equal(false)
  })
})
