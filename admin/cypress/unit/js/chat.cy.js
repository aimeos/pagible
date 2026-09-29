import { createPinia, setActivePinia } from 'pinia'
import { chat } from '../../../js/chat'
import { useUserStore } from '../../../js/stores'

describe('chat()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sends the prompt again after the user signed in again', () => {
    const fetch = cy.stub(window, 'fetch')
    fetch.onFirstCall().resolves(new Response('', { status: 419 }))
    fetch.onSecondCall().resolves(new Response('Done'))

    const user = useUserStore()
    user.me = { email: 'editor@example.com', permission: {}, settings: {} }
    const reauth = cy.stub(user, 'reauth').resolves()
    const touch = cy.stub(user, 'touch')

    return chat('Create a page').then((text) => {
      expect(text).to.equal('Done')
      expect(touch).to.have.been.calledOnce
      expect(reauth).to.have.been.calledOnce
      expect(fetch).to.have.been.calledTwice
    })
  })

  it('fails without retry if nobody is signed in', () => {
    cy.stub(window, 'fetch').resolves(new Response('', { status: 401 }))
    cy.stub(console, 'error')

    const user = useUserStore()
    const reauth = cy.stub(user, 'reauth').resolves()

    return chat('Create a page').then(
      () => expect.fail('chat() should reject'),
      (error) => {
        expect(error.status).to.equal(401)
        expect(reauth).not.to.have.been.called
        expect(window.fetch).to.have.been.calledOnce
      }
    )
  })
})
