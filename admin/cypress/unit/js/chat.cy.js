import { createPinia, setActivePinia } from 'pinia'
import { chat } from '../../../js/chat'
import { useSessionStore, useUserStore } from '../../../js/stores'

describe('chat()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sends the prompt again after the user signed in again', () => {
    const fetch = cy.stub(window, 'fetch')
    fetch.onFirstCall().resolves(new Response('', { status: 419 }))
    fetch.onSecondCall().resolves(new Response('Done\u001E'))

    const user = useUserStore()
    user.me = { email: 'editor@example.com', permission: {}, settings: {} }
    const reauth = cy.stub(useSessionStore(), 'reauth').resolves()
    const touch = cy.stub(useSessionStore(), 'touch')

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

    const reauth = cy.stub(useSessionStore(), 'reauth').resolves()

    return chat('Create a page').then(
      () => expect.fail('chat() should reject'),
      (error) => {
        expect(error.status).to.equal(401)
        expect(reauth).not.to.have.been.called
        expect(window.fetch).to.have.been.calledOnce
      }
    )
  })

  it('returns the answer without the end marker', () => {
    cy.stub(window, 'fetch').resolves(new Response('Created the page\u001E'))
    const deltas = []

    return chat('Create a page', [], (delta) => deltas.push(delta.text)).then((text) => {
      expect(text).to.equal('Created the page')
      expect(deltas.join('')).to.equal('Created the page')
    })
  })

  it('finds the end marker split from the message across chunks', () => {
    const encoder = new TextEncoder()
    const body = new ReadableStream({
      start(controller) {
        ;['Partial ', 'answer\u001EProvider ', 'overloaded'].forEach((c) => controller.enqueue(encoder.encode(c)))
        controller.close()
      }
    })
    cy.stub(window, 'fetch').resolves(new Response(body))
    cy.stub(console, 'error')
    const deltas = []

    return chat('Create a page', [], (delta) => deltas.push(delta.text)).then(
      () => expect.fail('chat() should reject'),
      (error) => {
        expect(error.shown).to.equal(true)
        expect(error.message).to.equal('Provider overloaded')
        expect(deltas.join('')).to.equal('Partial answer')
      }
    )
  })

  it('fails without shown message if the end marker is missing', () => {
    cy.stub(window, 'fetch').resolves(new Response('Partial answer'))
    cy.stub(console, 'error')

    return chat('Create a page').then(
      () => expect.fail('chat() should reject'),
      (error) => expect(error.shown).not.to.equal(true)
    )
  })
})
