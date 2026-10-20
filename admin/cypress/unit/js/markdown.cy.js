import { fromMarkdown, toMarkdown } from '../../../js/markdown'

describe('markdown', () => {
  it('converts text, code and heading elements to Markdown', () => {
    expect(toMarkdown({ type: 'text', data: { text: 'Hello' } })).to.equal('Hello')
    expect(toMarkdown({ type: 'code', data: { lang: 'js', text: 'x()' } })).to.equal('```js\nx()\n```')
    expect(toMarkdown({ type: 'heading', data: { level: '2', title: 'Title' } })).to.equal('## Title')
    expect(toMarkdown({ type: 'image', data: {} })).to.equal('')
  })

  it('splits Markdown into element types and data', () => {
    return fromMarkdown('## Title\n\nSome *text*\n\n```js\nx()\n```').then((list) => {
      expect(list).to.deep.equal([
        { type: 'heading', data: { title: 'Title', level: '2' } },
        { type: 'text', data: { text: 'Some *text*' } },
        { type: 'code', data: { lang: 'js', text: 'x()' } }
      ])
    })
  })
})
