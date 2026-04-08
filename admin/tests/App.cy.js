import App from '../js/App.vue'
import { createPinia, setActivePinia } from 'pinia'
import { slugify, srcset, toBlob, debounce, locales, txlocales, url } from '../js/utils'
import { write, translate, transcribe } from '../js/ai'
import { useAppStore, useUserStore, useMessageStore, useLanguageStore, useViewStack } from '../js/stores'

describe('App', () => {
  it('renders v-application', () => {
    cy.mount(App)
    cy.get('.v-application').should('exist')
  })
})

describe('slugify()', () => {
  it('converts spaces to hyphens and lowercases', () => {
    expect(slugify('Hello World')).to.equal('hello-world')
  })

  it('replaces special characters with hyphens', () => {
    expect(slugify('Foo & Bar')).to.equal('foo-bar')
    expect(slugify('a@b#c')).to.equal('a-b-c')
  })

  it('collapses consecutive hyphens', () => {
    expect(slugify('a  &  b')).to.equal('a-b')
  })

  it('trims leading and trailing hyphens', () => {
    expect(slugify(' Hello ')).to.equal('hello')
  })

  it('returns empty string for falsy input', () => {
    expect(slugify('')).to.equal('')
    expect(slugify(null)).to.equal('')
    expect(slugify(undefined)).to.equal('')
  })
})

describe('url()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns empty string for falsy path', () => {
    expect(url('')).to.equal('')
    expect(url(null)).to.equal('')
  })

  it('returns non-string values as-is', () => {
    const obj = { key: 'val' }
    expect(url(obj)).to.equal(obj)
  })

  it('returns absolute http URLs unchanged', () => {
    expect(url('http://example.com/img.jpg')).to.equal('http://example.com/img.jpg')
    expect(url('https://cdn.example.com/a.png')).to.equal('https://cdn.example.com/a.png')
  })

  it('returns blob URLs unchanged', () => {
    expect(url('blob:http://localhost/abc')).to.equal('blob:http://localhost/abc')
  })

  it('prepends the storage URL for relative paths', () => {
    expect(url('images/photo.jpg')).to.equal('/storage/images/photo.jpg')
  })

  it('proxies http URLs when proxy flag is true', () => {
    const result = url('http://example.com/img.jpg', true)
    expect(result).to.include(encodeURIComponent('http://example.com/img.jpg'))
  })

  it('does not proxy relative paths even when proxy is true', () => {
    expect(url('images/photo.jpg', true)).to.equal('/storage/images/photo.jpg')
  })
})

describe('srcset()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns empty string for null or undefined', () => {
    expect(srcset(null)).to.equal('')
    expect(srcset(undefined)).to.equal('')
  })

  it('returns empty string for empty object', () => {
    expect(srcset({})).to.equal('')
  })

  it('builds srcset from width-path map', () => {
    const result = srcset({ 400: 'img-400.jpg', 800: 'img-800.jpg' })
    expect(result).to.include('400w')
    expect(result).to.include('800w')
    expect(result).to.include('/storage/img-400.jpg')
  })
})

describe('toBlob()', () => {
  it('returns null for falsy input', () => {
    expect(toBlob('')).to.be.null
    expect(toBlob(null)).to.be.null
  })

  it('returns a Blob with default image/png type', () => {
    const blob = toBlob('AAAA')
    expect(blob).to.be.instanceOf(Blob)
    expect(blob.type).to.equal('image/png')
  })

  it('accepts a custom mime type', () => {
    const blob = toBlob('AAAA', 'image/jpeg')
    expect(blob.type).to.equal('image/jpeg')
  })
})

describe('debounce()', () => {
  it('returns a function', () => {
    expect(debounce(() => {}, 100)).to.be.a('function')
  })

  it('delays execution by the specified delay', () => {
    let called = false
    const debounced = debounce(() => { called = true }, 50)
    debounced()
    expect(called).to.be.false
  })
})

describe('viewStack', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('pushes a view onto the stack', () => {
    const viewStack = useViewStack()
    const before = viewStack.stack.length
    viewStack.openView({ render() { return 'test' } })
    expect(viewStack.stack).to.have.length(before + 1)
  })

  it('does nothing when component is falsy', () => {
    const viewStack = useViewStack()
    const before = viewStack.stack.length
    viewStack.openView(null)
    expect(viewStack.stack).to.have.length(before)
  })

  it('closeView() pops the last view', () => {
    const viewStack = useViewStack()
    viewStack.openView({ render() { return 'a' } })
    viewStack.openView({ render() { return 'b' } })
    const len = viewStack.stack.length
    viewStack.closeView()
    expect(viewStack.stack).to.have.length(len - 1)
  })
})

describe('locales()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns an array of locale entries', () => {
    const list = locales()
    expect(list).to.be.an('array')
    expect(list.length).to.be.greaterThan(0)
    expect(list[0]).to.have.property('value')
    expect(list[0]).to.have.property('title')
  })

  it('prepends a None entry when none=true', () => {
    const list = locales(true)
    expect(list[0].value).to.be.null
    expect(list[0].title).to.be.a('string')
  })
})

describe('txlocales()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns an array', () => {
    expect(txlocales()).to.be.an('array')
  })

  it('excludes the current locale', () => {
    const list = txlocales('en')
    expect(list.every(item => item.code !== 'en')).to.be.true
  })
})

describe('write()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('denies without text:write permission', () => {
    const user = useUserStore()
    user.me = { permission: {} }
    write('prompt')
    const msgs = useMessageStore()
    expect(msgs.queue.some(m => m.color === 'error')).to.be.true
  })

  it('returns empty for blank prompt with permission', () => {
    const user = useUserStore()
    user.me = { permission: { 'text:write': true } }
    write('  ').then(result => {
      expect(result).to.equal('')
    })
  })
})

describe('translate()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('denies without text:translate permission', () => {
    const user = useUserStore()
    user.me = { permission: {} }
    translate(['hello'], 'de')
    const msgs = useMessageStore()
    expect(msgs.queue.some(m => m.color === 'error')).to.be.true
  })

  it('resolves empty array for empty texts', () => {
    const user = useUserStore()
    user.me = { permission: { 'text:translate': true } }
    translate([], 'de').then(result => {
      expect(result).to.deep.equal([])
    })
  })

  it('rejects when target language is missing', (done) => {
    const user = useUserStore()
    user.me = { permission: { 'text:translate': true } }
    translate(['hello'], '').catch(err => {
      expect(err.message).to.include('Target language is required')
      done()
    })
  })
})

describe('transcribe()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('denies without audio:transcribe permission', () => {
    const user = useUserStore()
    user.me = { permission: {} }
    transcribe('audio.mp3')
    const msgs = useMessageStore()
    expect(msgs.queue.some(m => m.color === 'error')).to.be.true
  })
})
