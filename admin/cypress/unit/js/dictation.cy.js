import { createPinia, setActivePinia } from 'pinia'
import { dictate, useDictation } from '../../../js/dictation'
import { recording, toMp3 } from '../../../js/audio'
import { useMessageStore } from '../../../js/stores'

describe('recording()', () => {
  it('releases the audio resources and can be started again after a failed start', () => {
    const getUserMedia = cy.stub(navigator.mediaDevices, 'getUserMedia').rejects(new Error('denied'))
    const revoke = cy.spy(URL, 'revokeObjectURL')
    const rec = recording()

    cy.wrap(rec.start().then(() => 'started', (error) => error.message)).should('equal', 'denied')
    cy.then(() => {
      expect(revoke).to.have.been.calledOnce
      return rec.start().catch(() => null)
    })
    cy.then(() => {
      expect(getUserMedia).to.have.been.calledTwice
    })
  })

  it('returns null when stopping a recording that was not started', () => {
    cy.wrap(recording().stop()).should('equal', null)
  })
})

describe('toMp3()', () => {
  it('rejects empty input instead of fetching the current page', () => {
    const fetch = cy.spy(window, 'fetch')

    cy.wrap(toMp3('').then(() => 'converted', (error) => error.message)).should('contain', 'No audio input')
    cy.then(() => expect(fetch).not.to.have.been.called)
  })

  it('rejects failed audio downloads with the status', () => {
    cy.stub(window, 'fetch').resolves(new Response('missing', { status: 404 }))

    cy.wrap(toMp3('/audio.mp3').then(() => 'converted', (error) => error.message)).should('contain', '404')
  })

  it('rejects and terminates the worker if encoding fails', () => {
    const terminate = cy.stub()

    cy.stub(window, 'Worker').callsFake(function () {
      this.terminate = terminate
      this.postMessage = () => setTimeout(() => this.onmessage({ data: { error: 'encoding failed' } }))
    })

    const buffer = new AudioBuffer({ length: 100, numberOfChannels: 1, sampleRate: 16000 })

    cy.wrap(toMp3(buffer).then(() => 'converted', (error) => error.message)).should('equal', 'encoding failed')
    cy.then(() => expect(terminate).to.have.been.calledOnce)
  })

  it('resolves the encoded MP3 posted by the worker', () => {
    const mp3 = new Blob(['mp3'], { type: 'audio/mpeg' })

    cy.stub(window, 'Worker').callsFake(function () {
      this.terminate = cy.stub()
      this.postMessage = () => setTimeout(() => this.onmessage({ data: { mp3 } }))
    })

    const buffer = new AudioBuffer({ length: 100, numberOfChannels: 1, sampleRate: 16000 })

    cy.wrap(toMp3(buffer)).should('equal', mp3)
  })
})

describe('dictate()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('reports a failed recording start and resolves to null', () => {
    cy.stub(navigator.mediaDevices, 'getUserMedia').rejects(new Error('denied'))
    cy.stub(console, 'error')
    const error = cy.spy(useMessageStore(), 'error')

    cy.wrap(dictate(null, cy.stub(), cy.stub())).should('equal', null)
    cy.then(() => {
      expect(error).to.have.been.calledWithMatch('Error recording audio')
    })
  })

  it('skips the transcription when nothing was recorded', () => {
    const busy = cy.stub()
    const done = cy.stub()

    dictate(Promise.resolve({ stop: () => Promise.resolve(null) }), busy, done)

    cy.wrap(busy).should('have.been.calledWith', false)
    cy.then(() => {
      expect(busy).to.have.been.calledWith(true)
      expect(done).not.to.have.been.called
    })
  })

  it('reports a recording that fails to stop', () => {
    const busy = cy.stub()
    const done = cy.stub()
    const error = cy.stub(useMessageStore(), 'error')

    dictate(Promise.resolve({ stop: () => Promise.reject(new Error('encoding failed')) }), busy, done)

    cy.wrap(busy).should('have.been.calledWith', false)
    cy.then(() => {
      expect(error).to.have.been.calledWithMatch('Error recording audio')
      expect(done).not.to.have.been.called
    })
  })
})

describe('useDictation()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('ends the recording state when the recording cannot be started', () => {
    cy.stub(navigator.mediaDevices, 'getUserMedia').rejects(new Error('denied'))
    cy.stub(console, 'error')
    const dictation = useDictation()

    dictation.toggle(cy.stub())
    expect(dictation.active()).to.be.true

    cy.wrap(dictation).invoke('active').should('be.false')
  })
})
