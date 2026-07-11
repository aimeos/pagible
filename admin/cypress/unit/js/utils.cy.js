import { debounce, uid } from '../../../js/utils'

describe('uid()', () => {
  it('returns a string of length 6', () => {
    expect(uid()).to.have.length(6)
  })

  it('first character is always a letter (A-Z or a-z)', () => {
    for (let i = 0; i < 100; i++) {
      expect(uid()[0]).to.match(/[A-Za-z]/)
    }
  })

  it('remaining characters are base64url alphabet', () => {
    for (let i = 0; i < 50; i++) {
      expect(uid()).to.match(/^[A-Za-z][A-Za-z0-9\-_]{5}$/)
    }
  })

  it('generates unique IDs on consecutive calls', () => {
    const ids = new Set()
    for (let i = 0; i < 100; i++) {
      ids.add(uid())
    }
    expect(ids.size).to.equal(100)
  })

  it('returns a different value on each call', () => {
    const a = uid()
    const b = uid()
    expect(a).to.not.equal(b)
  })
})

describe('debounce()', () => {
  it('invokes the function once after the delay, coalescing rapid calls', () => {
    cy.clock().then((clock) => {
      let calls = 0
      const fn = debounce(() => { calls++ }, 300)
      fn()
      fn()
      clock.tick(300)
      expect(calls).to.equal(1)
    })
  })

  it('cancel() prevents a pending invocation', () => {
    cy.clock().then((clock) => {
      let calls = 0
      const fn = debounce(() => { calls++ }, 300)
      fn()
      fn.cancel()
      clock.tick(300)
      expect(calls).to.equal(0)
    })
  })
})
