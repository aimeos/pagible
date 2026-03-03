import { createPinia, setActivePinia } from 'pinia'
import {
  useAuthStore,
  useClipboardStore,
  useConfigStore,
  useDrawerStore,
  useMessageStore,
  useSideStore,
} from '../../src/stores'

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('can()', () => {
    it('returns false when me is null', () => {
      const auth = useAuthStore()
      expect(auth.can('page:view')).to.be.false
    })

    it('returns false when me has no matching permission', () => {
      const auth = useAuthStore()
      auth.me = { permission: { 'file:view': true } }
      expect(auth.can('page:view')).to.be.false
    })

    it('returns true when me has the exact permission', () => {
      const auth = useAuthStore()
      auth.me = { permission: { 'page:view': true } }
      expect(auth.can('page:view')).to.be.true
    })

    it('accepts an array and returns true if any matches', () => {
      const auth = useAuthStore()
      auth.me = { permission: { 'file:view': true } }
      expect(auth.can(['page:view', 'file:view'])).to.be.true
    })

    it('returns false when array has no matching permissions', () => {
      const auth = useAuthStore()
      auth.me = { permission: { 'element:view': true } }
      expect(auth.can(['page:view', 'file:view'])).to.be.false
    })

    it('returns false when permission value is falsy', () => {
      const auth = useAuthStore()
      auth.me = { permission: { 'page:view': 0 } }
      expect(auth.can('page:view')).to.be.false
    })
  })

  describe('intended()', () => {
    it('stores and returns the intended URL', () => {
      const auth = useAuthStore()
      auth.intended('/pages')
      expect(auth.intended()).to.equal('/pages')
    })

    it('returns null when no URL has been set', () => {
      const auth = useAuthStore()
      expect(auth.intended()).to.be.null
    })

    it('returns the URL when setting it', () => {
      const auth = useAuthStore()
      expect(auth.intended('/files')).to.equal('/files')
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


describe('useConfigStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns defval for missing keys', () => {
    const config = useConfigStore()
    expect(config.get('missing')).to.be.null
    expect(config.get('missing', 42)).to.equal(42)
  })

  it('returns defval when key is not a string', () => {
    const config = useConfigStore()
    expect(config.get(null, 'fallback')).to.equal('fallback')
    expect(config.get(123, 'fallback')).to.equal('fallback')
  })

  // Note: state is read from DOM (#app data-config) at import time,
  // so top-level/nested value retrieval can't be tested without the DOM element.

  it('returns defval when the dot-path leads nowhere', () => {
    const config = useConfigStore()
    expect(config.get('a.b.c', 'nope')).to.equal('nope')
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

  it('limits the queue to 5 messages', () => {
    const msg = useMessageStore()
    for (let i = 0; i < 10; i++) {
      msg.add(`msg-${i}`)
    }
    expect(msg.queue).to.have.length(5)
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
