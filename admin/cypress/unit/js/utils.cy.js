import { isProxy, reactive } from 'vue'
import { clone, composing, debounce, uid } from '../../../js/utils'

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
    // more than the 128 IDs the old time based IDs could generate within 4 seconds
    const ids = new Set()
    for (let i = 0; i < 300; i++) {
      ids.add(uid())
    }
    expect(ids.size).to.equal(300)
  })

  it('returns a different value on each call', () => {
    const a = uid()
    const b = uid()
    expect(a).to.not.equal(b)
  })
})

describe('composing()', () => {
  it('detects key events of an input method editor', () => {
    expect(composing(new KeyboardEvent('keydown', { key: 'Enter', isComposing: true }))).to.equal(true)
    // Safari confirms the composed word after the composition ended
    expect(composing(new KeyboardEvent('keydown', { key: 'Enter', keyCode: 229 }))).to.equal(true)
    expect(composing(new KeyboardEvent('keydown', { key: 'Enter', keyCode: 13 }))).to.equal(false)
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

  it('settles superseded calls with the result of the final invocation', () => {
    cy.clock().then((clock) => {
      const fn = debounce((val) => val, 300)
      const first = fn('a')
      const second = fn('b')
      clock.tick(300)

      return Promise.all([first, second]).then((results) => {
        expect(results).to.deep.equal(['b', 'b'])
      })
    })
  })

  it('flush() runs a pending invocation immediately and only once', () => {
    cy.clock().then((clock) => {
      let calls = 0
      const fn = debounce(() => ++calls, 300)
      const pending = fn()
      fn.flush()
      expect(calls).to.equal(1)
      clock.tick(300)
      fn.flush()
      expect(calls).to.equal(1)

      return pending.then((result) => {
        expect(result).to.equal(1)
      })
    })
  })

  it('cancel() resolves the pending calls with undefined', () => {
    cy.clock().then((clock) => {
      const fn = debounce(() => 'x', 300)
      const pending = fn()
      fn.cancel()
      clock.tick(300)

      return pending.then((result) => {
        expect(result).to.equal(undefined)
      })
    })
  })
})

describe('clone()', () => {
  it('deep copies plain values', () => {
    const date = new Date(0)
    const value = { a: 1, b: [{ c: 'x' }], d: null, date }
    const copy = clone(value)

    expect(copy).to.deep.equal(value)
    expect(copy).to.not.equal(value)
    expect(copy.b[0]).to.not.equal(value.b[0])
    expect(copy.date).to.not.equal(date)
  })

  it('unwraps nested reactive proxies', () => {
    const element = reactive({ data: { items: [{ title: 'a' }] } })
    element.data = { ...element.data, text: 'b' } // spread keeps "items" as a proxy

    const copy = clone(element)

    expect(copy).to.deep.equal({ data: { items: [{ title: 'a' }], text: 'b' } })
    expect(isProxy(copy.data.items)).to.equal(false)
    expect(isProxy(copy.data.items[0])).to.equal(false)
    expect(() => structuredClone(copy)).to.not.throw()
  })
})

