import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '../../../js/stores'
import { pageUrl } from '../../../js/urls'

describe('pageUrl()', () => {
  let app

  beforeEach(() => {
    setActivePinia(createPinia())
    app = useAppStore()
  })

  it('replaces domain and path', () => {
    app.urlpage = 'https://_domain_/_path_'
    expect(pageUrl({ domain: 'example.com', path: 'blog/article' })).to.equal('https://example.com/blog/article')
  })

  it('removes the domain placeholder including the scheme without domain', () => {
    app.urlpage = 'https://_domain_/_path_'
    expect(pageUrl({ path: 'blog' })).to.equal('/blog')
  })

  it('removes the domain path segment without domain', () => {
    app.urlpage = '/cms/_domain_/_path_'
    expect(pageUrl({ path: 'blog' })).to.equal('/cms/blog')
  })

  it('removes trailing slashes but keeps the root path', () => {
    app.urlpage = '/_path_'
    expect(pageUrl({ path: '' })).to.equal('/')

    app.urlpage = 'https://_domain_/_path_/'
    expect(pageUrl({ domain: 'example.com', path: '' })).to.equal('https://example.com')
  })

  it('handles missing pages', () => {
    app.urlpage = '/_path_'
    expect(pageUrl(null)).to.equal('/')
  })

  it('returns absolute URLs using the current location', () => {
    app.urlpage = '/_path_'
    expect(pageUrl({ path: 'blog' }, true)).to.equal(window.location.origin + '/blog')
  })

  it('uses the current host if the page has no domain', () => {
    app.urlpage = 'http://_domain_/_path_'
    const url = new URL(pageUrl({ path: 'blog' }, true))

    expect(url.hostname).to.equal(window.location.hostname)
    expect(url.protocol).to.equal(window.location.protocol)
    expect(url.port).to.equal(window.location.port)
    expect(url.pathname).to.equal('/blog')
  })

  it('uses the root path for absolute URLs without path', () => {
    app.urlpage = '/_path_'
    expect(pageUrl({}, true)).to.equal(window.location.origin + '/')
  })
})
