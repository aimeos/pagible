import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import router, { guard } from '../../../js/routes'
import gettext, { ready } from '../../../js/i18n'
import { useUserStore } from '../../../js/stores'

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
    const isAuthenticated = cy.stub(user, 'isAuthenticated').resolves(false)
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
    const intended = cy.stub(user, 'intended')
    cy.stub(user, 'isAuthenticated').resolves(false)

    const result = await guard({
      fullPath: '/pages',
      matched: [{ meta: { auth: true } }],
      meta: { auth: true },
      name: 'page:view',
    })

    expect(intended).to.have.been.calledOnceWith('/pages')
    expect(result).to.deep.equal({ name: 'login' })
  })
})
