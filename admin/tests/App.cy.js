import App from '../src/App.vue'
import { useUserStore, useMessageStore } from '../src/stores'

describe('App', () => {
  function mountApp(perms = {}) {
    return cy.mount(App, {
      global: {
        plugins: [{
          install() {
            const user = useUserStore()
            user.me = { permission: perms }
          }
        }],
      },
    })
  }

  function getVm() {
    return Cypress.vueWrapper.findComponent(App).vm
  }

  it('renders v-application', () => {
    mountApp()
    cy.get('.v-application').should('exist')
  })

  describe('slugify()', () => {
    it('converts spaces to hyphens and lowercases', () => {
      mountApp().then(() => {
        expect(getVm().slugify('Hello World')).to.equal('hello-world')
      })
    })

    it('replaces special characters with hyphens', () => {
      mountApp().then(() => {
        expect(getVm().slugify('Foo & Bar')).to.equal('foo-bar')
        expect(getVm().slugify('a@b#c')).to.equal('a-b-c')
      })
    })

    it('collapses consecutive hyphens', () => {
      mountApp().then(() => {
        expect(getVm().slugify('a  &  b')).to.equal('a-b')
      })
    })

    it('trims leading and trailing hyphens', () => {
      mountApp().then(() => {
        expect(getVm().slugify(' Hello ')).to.equal('hello')
      })
    })

    it('returns empty string for falsy input', () => {
      mountApp().then(() => {
        expect(getVm().slugify('')).to.equal('')
        expect(getVm().slugify(null)).to.equal('')
        expect(getVm().slugify(undefined)).to.equal('')
      })
    })
  })

  describe('url()', () => {
    it('returns empty string for falsy path', () => {
      mountApp().then(() => {
        expect(getVm().url('')).to.equal('')
        expect(getVm().url(null)).to.equal('')
      })
    })

    it('returns non-string values as-is', () => {
      mountApp().then(() => {
        const obj = { key: 'val' }
        expect(getVm().url(obj)).to.equal(obj)
      })
    })

    it('returns absolute http URLs unchanged', () => {
      mountApp().then(() => {
        expect(getVm().url('http://example.com/img.jpg')).to.equal('http://example.com/img.jpg')
        expect(getVm().url('https://cdn.example.com/a.png')).to.equal('https://cdn.example.com/a.png')
      })
    })

    it('returns blob URLs unchanged', () => {
      mountApp().then(() => {
        expect(getVm().url('blob:http://localhost/abc')).to.equal('blob:http://localhost/abc')
      })
    })

    it('prepends the storage URL for relative paths', () => {
      mountApp().then(() => {
        expect(getVm().url('images/photo.jpg')).to.equal('/storage/images/photo.jpg')
      })
    })

    it('proxies http URLs when proxy flag is true', () => {
      mountApp().then(() => {
        const result = getVm().url('http://example.com/img.jpg', true)
        expect(result).to.include(encodeURIComponent('http://example.com/img.jpg'))
      })
    })

    it('does not proxy relative paths even when proxy is true', () => {
      mountApp().then(() => {
        expect(getVm().url('images/photo.jpg', true)).to.equal('/storage/images/photo.jpg')
      })
    })
  })

  describe('srcset()', () => {
    it('returns empty string for null or undefined', () => {
      mountApp().then(() => {
        expect(getVm().srcset(null)).to.equal('')
        expect(getVm().srcset(undefined)).to.equal('')
      })
    })

    it('returns empty string for empty object', () => {
      mountApp().then(() => {
        expect(getVm().srcset({})).to.equal('')
      })
    })

    it('builds srcset from width-path map', () => {
      mountApp().then(() => {
        const result = getVm().srcset({ 400: 'img-400.jpg', 800: 'img-800.jpg' })
        expect(result).to.include('400w')
        expect(result).to.include('800w')
        expect(result).to.include('/storage/img-400.jpg')
      })
    })
  })

  describe('base64ToBlob()', () => {
    it('returns null for falsy input', () => {
      mountApp().then(() => {
        expect(getVm().base64ToBlob('')).to.be.null
        expect(getVm().base64ToBlob(null)).to.be.null
      })
    })

    it('returns a Blob with default image/png type', () => {
      mountApp().then(() => {
        const blob = getVm().base64ToBlob('AAAA')
        expect(blob).to.be.instanceOf(Blob)
        expect(blob.type).to.equal('image/png')
      })
    })

    it('accepts a custom mime type', () => {
      mountApp().then(() => {
        const blob = getVm().base64ToBlob('AAAA', 'image/jpeg')
        expect(blob.type).to.equal('image/jpeg')
      })
    })
  })

  describe('debounce()', () => {
    it('returns a function', () => {
      mountApp().then(() => {
        expect(getVm().debounce(() => {}, 100)).to.be.a('function')
      })
    })

    it('delays execution by the specified delay', () => {
      mountApp().then(() => {
        let called = false
        const debounced = getVm().debounce(() => { called = true }, 50)
        debounced()
        expect(called).to.be.false
      })
    })
  })

  describe('open() / close()', () => {
    it('pushes a view onto the stack', () => {
      mountApp().then(() => {
        const vm = getVm()
        const before = vm.viewStack.length
        vm.open({ render() { return 'test' } })
        expect(vm.viewStack).to.have.length(before + 1)
      })
    })

    it('does nothing when component is falsy', () => {
      mountApp().then(() => {
        const vm = getVm()
        const before = vm.viewStack.length
        vm.open(null)
        expect(vm.viewStack).to.have.length(before)
      })
    })

    it('close() pops the last view', () => {
      mountApp().then(() => {
        const vm = getVm()
        vm.open({ render() { return 'a' } })
        vm.open({ render() { return 'b' } })
        const len = vm.viewStack.length
        vm.close()
        expect(vm.viewStack).to.have.length(len - 1)
      })
    })
  })

  describe('locales()', () => {
    it('returns an array of locale entries', () => {
      mountApp().then(() => {
        const list = getVm().locales()
        expect(list).to.be.an('array')
        expect(list.length).to.be.greaterThan(0)
        expect(list[0]).to.have.property('value')
        expect(list[0]).to.have.property('title')
      })
    })

    it('prepends a None entry when none=true', () => {
      mountApp().then(() => {
        const list = getVm().locales(true)
        expect(list[0].value).to.be.null
        expect(list[0].title).to.be.a('string')
      })
    })
  })

  describe('txlocales()', () => {
    it('returns an array', () => {
      mountApp().then(() => {
        expect(getVm().txlocales()).to.be.an('array')
      })
    })

    it('excludes the current locale', () => {
      mountApp().then(() => {
        const list = getVm().txlocales('en')
        expect(list.every(item => item.code !== 'en')).to.be.true
      })
    })
  })

  describe('write()', () => {
    it('denies without text:write permission', () => {
      mountApp({}).then(() => {
        getVm().write('prompt')
        const msgs = useMessageStore()
        expect(msgs.queue.some(m => m.color === 'error')).to.be.true
      })
    })

    it('returns empty for blank prompt with permission', () => {
      mountApp({ 'text:write': true }).then(() => {
        getVm().write('  ').then(result => {
          expect(result).to.equal('')
        })
      })
    })
  })

  describe('translate()', () => {
    it('denies without text:translate permission', () => {
      mountApp({}).then(() => {
        getVm().translate(['hello'], 'de')
        const msgs = useMessageStore()
        expect(msgs.queue.some(m => m.color === 'error')).to.be.true
      })
    })

    it('resolves empty array for empty texts', () => {
      mountApp({ 'text:translate': true }).then(() => {
        getVm().translate([], 'de').then(result => {
          expect(result).to.deep.equal([])
        })
      })
    })

    it('rejects when target language is missing', (done) => {
      mountApp({ 'text:translate': true }).then(() => {
        getVm().translate(['hello'], '').catch(err => {
          expect(err.message).to.include('Target language is required')
          done()
        })
      })
    })
  })

  describe('transcribe()', () => {
    it('denies without audio:transcribe permission', () => {
      mountApp({}).then(() => {
        getVm().transcribe('audio.mp3')
        const msgs = useMessageStore()
        expect(msgs.queue.some(m => m.color === 'error')).to.be.true
      })
    })
  })
})
