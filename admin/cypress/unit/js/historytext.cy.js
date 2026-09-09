import { assets, decorate, describe as text, formatting, images, paragraphs, structure, words } from '../../../js/historytext'

describe('History text presentation', () => {
  it('retains ordered markers, custom starts, reversed lists and nested list depth', () => {
    const value = text('<ol start="3" type="A"><li>First <b>bold</b><p>Continued</p><ul><li>Nested</li></ul></li><li value="8">Eighth</li><li>Ninth</li></ol><ol reversed><li>Two</li><li>One</li></ol>')
    const items = value.lists.filter(item => item.first).map(item => ({ text: value.text.slice(item.start, item.end), number: item.number, kind: item.kind, type: item.type, depth: item.depth }))
    expect(items).to.deep.equal([
      { text: 'First ', number: 3, kind: 'ol', type: 'A', depth: 1 },
      { text: 'Nested', number: undefined, kind: 'ul', type: '1', depth: 2 },
      { text: 'Eighth', number: 8, kind: 'ol', type: 'A', depth: 1 },
      { text: 'Ninth', number: 9, kind: 'ol', type: 'A', depth: 1 },
      { text: 'Two', number: 2, kind: 'ol', type: '1', depth: 1 },
      { text: 'One', number: 1, kind: 'ol', type: '1', depth: 1 }
    ])
    expect(value.lists.find(item => value.text.slice(item.start, item.end) === 'Continued')).to.include({ first: false, depth: 1 })
    expect(text('1. Literal list', 'plaintext').lists).to.deep.equal([])
    expect(text('3. First\n4. Second', 'markdown').lists.filter(item => item.first).map(item => item.number)).to.deep.equal([3, 4])
  })

  it('refines short replacements without splitting graphemes or fragmenting ordinary rewrites', () => {
    expect(words('SKU-1234', 'SKU-1235').filter(part => part.removed).map(part => part.value).join('')).to.equal('4')
    expect(words('SKU-1234', 'SKU-1235').filter(part => part.added).map(part => part.value).join('')).to.equal('5')
    expect(words('Current', 'Saved').filter(part => part.removed).map(part => part.value).join('')).to.equal('Current')
    for (const [before, after] of [['A 👍🏻', 'A 👍🏽'], ['cafe\u0301', 'cafe'], ['A B', 'A  B'], ['A\nB', 'A\n\nB'], ['**Team**', '__Team__']]) {
      const result = words(before, after)
      expect(result.filter(part => !part.added).map(part => part.value).join('')).to.equal(before)
      expect(result.filter(part => !part.removed).map(part => part.value).join('')).to.equal(after)
    }
    expect(words('👍🏻', '👍🏽').filter(part => part.removed).map(part => part.value)).to.deep.equal(['👍🏻'])
    expect(words('cafe\u0301', 'cafe').filter(part => part.removed).map(part => part.value)).to.deep.equal(['e\u0301'])
    let offset = 0
    const removed = words('€10.00', '€10.50').filter(part => !part.added).flatMap(part => {
      const start = offset
      offset += part.value.length
      return part.removed ? [[start, offset]] : []
    })
    expect(removed).to.deep.equal([[4, 5]])
  })

  it('describes embedded image changes and anchors unchanged images across insertions', () => {
    const before = text('<p>Old text</p><img src="/photo.jpg" alt="Team" title="Old title"><img src="/keep.jpg">')
    const after = text('<p>New text</p><img src="/new.jpg" alt="People" title="New title" onerror="alert(1)"><img src="/keep.jpg">')
    const attributes = changes => changes.map(({ before, after, text, ...change }) => change)
    expect(attributes(images(before, after))).to.deep.equal([{ number: 1, kind: 'changed', attributes: [
      { name: 'alt', before: 'Team', after: 'People' },
      { name: 'src', before: '/photo.jpg', after: '/new.jpg' },
      { name: 'title', before: 'Old title', after: 'New title' }
    ] }])
    const inserted = text('<img src="/insert.jpg"><img src="/photo.jpg" alt="Team" title="Old title"><img src="/keep.jpg">')
    expect(attributes(images(before, inserted))).to.deep.equal([{ number: 1, kind: 'added', attributes: [{ name: 'src', before: undefined, after: '/insert.jpg' }] }])
    expect(images(inserted, before)[0]).to.include({ number: 1, kind: 'removed' })
    expect(images(before, text('<img title="Old title" alt="Team" src="/photo.jpg"><img src="/keep.jpg">'))).to.deep.equal([])
    expect(images(before, after)[0]).to.include({ before: before.text.indexOf('Team'), after: after.text.indexOf('People'), text: 'People' })
    expect(text('![Team](/photo.jpg "Portrait")', 'markdown').images).to.deep.equal([{ start: 0, attributes: { alt: 'Team', src: '/photo.jpg', title: 'Portrait' } }])
  })

  it('preserves literal markup in plain fields and interprets Markdown only when requested', () => {
    for (const type of ['plaintext', 'string']) {
      const value = text('<b>Keep &amp; literal</b>', type)
      expect(value.text).to.equal('<b>Keep &amp; literal</b>')
      expect(value.html).to.equal(false)
      expect(value.marks).to.deep.equal([])
    }
    expect(text('**team**', 'plaintext').text).to.equal('**team**')
    const value = text('## Team\n\nHello **everyone**.\n\n[Guide](/guide)\n\n`<b>literal</b>`', 'markdown')
    expect(value.text).to.equal('Team\nHello everyone.\nGuide\n<b>literal</b>')
    expect(value.marks.map(mark => mark.kind)).to.deep.equal(['bold', 'link', 'code'])
    expect(value.structure[0].types).to.deep.equal(['H2'])
  })

  it('reads Markdown tables as text and sanitizes embedded HTML before inspection', () => {
    const value = text('| Plan | Price |\n| --- | --- |\n| A | **€10** |\n\n<script>alert(1)</script>', 'markdown')
    expect(value.text).to.equal('| Plan | Price |\n| A | €10 |')
    expect(value.text).not.to.contain('alert')
  })

  it('decorates exact formatting ranges without marking identical neighbouring words', () => {
    const value = text('<p>team and <b><i>team</i></b></p>')
    const tokens = decorate([{ value: value.text, highlight: false }], value.marks, value.marks)
    expect(tokens.map(token => token.value).join('')).to.equal('team and team')
    expect(tokens[0]).to.include({ value: 'team and ', formatting: false })
    expect(tokens[1]).to.include({ value: 'team', formatting: true })
    expect(tokens[1].kinds).to.have.members(['bold', 'italic'])
  })
  it('identifies formatting added to and removed from exact text', () => {
    const result = formatting(text('<p>Free <strong>delivery</strong></p>'), text('<p>Free <em>delivery</em></p>'))
    expect(result.map(change => [change.kind, change.action, change.text])).to.deep.equal([
      ['bold', 'removed', 'delivery'], ['italic', 'added', 'delivery']
    ])
  })

  it('shows a destination change for the same link text', () => {
    const result = formatting(text('<a href="/old">Delivery</a>'), text('<a href="/new">Delivery</a>'))
    expect(result).to.have.length(1)
    expect(result[0]).to.include({ kind: 'link', action: 'changed', before: '/old', after: '/new', text: 'Delivery' })
  })

  it('distinguishes formatting moved between identical words', () => {
    const result = formatting(text('<b>Word</b> Word'), text('Word <b>Word</b>'))
    expect(result.map(change => change.action)).to.deep.equal(['removed', 'added'])
  })

  it('does not report text-only edits as structural markup changes', () => {
    const before = text('<p>Hello <strong>world</strong></p>')
    const after = text('<p>Welcome to our <strong>world</strong></p>')
    expect(structure(before, after)).to.deep.equal([])
    expect(formatting(before, after)).to.deep.equal([])
  })

  it('recognizes inline styles and reads paragraphs without executing HTML', () => {
    const before = text('<p>One</p><p>Two</p>')
    const after = text('<p>One</p><p><span style="font-weight:700">Two</span></p><script>window.historyTextInjected=true</script>')
    expect(after.text).to.equal('One\nTwo')
    expect(formatting(before, after)[0]).to.include({ kind: 'bold', action: 'added', text: 'Two' })
    expect(window.historyTextInjected).to.equal(undefined)
    expect(text('<a href="javascript:alert(1)">Unsafe</a>').marks[0].value).to.equal('')
  })

  it('resolves nested file references without duplicating them', () => {
    const file = { id: 'image', path: 'image.jpg' }
    expect(assets([{ image: { type: 'file', id: 'image' } }, 'image'], { image: file })).to.deep.equal([file])
  })

  it('keeps formatting when the marked text is replaced or extended', () => {
    for (const [before, after] of [
      ['<b>Hello</b>', '<b>Welcome</b>'],
      ['<p>Meet <em>our team</em> today</p>', '<p>Meet <em>the people</em> today</p>'],
      ['<b>Hello</b>', '<b>Hello world</b>'],
      ['<b>world</b>', '<b>Hello world</b>'],
      ['<p>Hi<b>World</b></p>', '<p>Hey<b>Everyone</b></p>'],
      ['<p> <b> Hello </b> </p>', '<p> <b> Welcome </b> </p>']
    ]) expect(formatting(text(before), text(after))).to.deep.equal([])
  })

  it('matches links with edited text and still reports a changed destination', () => {
    const before = text('<a href="/help">Help</a>')
    expect(formatting(before, text('<a href="/help">Support</a>'))).to.deep.equal([])
    const changes = formatting(before, text('<a href="/support">Support</a>'))
    expect(changes).to.have.length(1)
    expect(changes[0]).to.include({ kind: 'link', action: 'changed', before: '/help', after: '/support' })
  })

  it('reports formatting moved to different text during an edit', () => {
    expect(formatting(text('<b>One</b> two'), text('One <b>three</b>')).map(change => change.action)).to.deep.equal(['removed', 'added'])
  })

  it('aligns inserted and removed paragraphs with empty cells', () => {
    const rows = paragraphs('First\nLast', 'First\nInserted\nLast')
    expect(rows).to.deep.equal([
      { before: 'First', after: 'First', equal: true },
      { before: null, after: 'Inserted', equal: false },
      { before: 'Last', after: 'Last', equal: true }
    ])
    expect(paragraphs('First\nRemoved\nLast', 'First\nLast')[1]).to.deep.equal({ before: 'Removed', after: null, equal: false })
  })

  it('aligns changed paragraphs between shared neighbours without losing text', () => {
    const before = 'First\nOld words\nLast', after = 'First\nNew words\nExtra\nLast'
    const rows = paragraphs(before, after)
    expect(rows[1]).to.include({ before: 'Old words', after: 'New words' })
    expect(rows.filter(row => row.before !== null).map(row => row.before).join('\n')).to.equal(before)
    expect(rows.filter(row => row.after !== null).map(row => row.after).join('\n')).to.equal(after)
  })

  it('matches edited paragraphs around simultaneous insertions and removals', () => {
    const before = 'First\nDelivery takes three days.\nLast'
    const after = 'First\nNew introduction.\nDelivery takes two days.\nLast'
    const rows = paragraphs(before, after)
    expect(rows[1]).to.include({ before: null, after: 'New introduction.' })
    expect(rows[2]).to.include({ before: 'Delivery takes three days.', after: 'Delivery takes two days.' })
    const reverse = paragraphs(after, before)
    expect(reverse[1]).to.include({ before: 'New introduction.', after: null })
    expect(reverse[2]).to.include({ before: 'Delivery takes two days.', after: 'Delivery takes three days.' })
    expect(formatting(text('<p><b>Delivery takes three days.</b></p>'), text('<p>New introduction.</p><p><b>Delivery takes two days.</b></p>'))).to.deep.equal([])
  })

  it('preserves all text and ordering for ambiguous and large paragraph rewrites', () => {
    for (const [before, after] of [
      ['Repeated old words\nRemoved\nRepeated other words', 'Introduction\nRepeated new words\nRepeated changed words'],
      [Array.from({ length: 110 }, (_, i) => `Old paragraph ${i}`).join('\n'), Array.from({ length: 111 }, (_, i) => `New paragraph ${i}`).join('\n')]
    ]) {
      const rows = paragraphs(before, after)
      expect(rows.filter(row => row.before !== null).map(row => row.before).join('\n')).to.equal(before)
      expect(rows.filter(row => row.after !== null).map(row => row.after).join('\n')).to.equal(after)
    }
  })

  it('identifies heading conversions while the text and inline formatting also change', () => {
    const before = text('<p>Old <b>title</b></p>'), after = text('<h2>New <em>title</em></h2>')
    const changes = structure(before, after)
    expect(changes.map(({ spans, ...change }) => change)).to.deep.equal([{ before: ['P'], after: ['H2'], text: 'New title' }])
    expect(before.text.slice(...changes[0].spans[0].before)).to.equal('Old title')
    expect(after.text.slice(...changes[0].spans[0].after)).to.equal('New title')
    expect(formatting(before, after).map(change => change.kind)).to.deep.equal(['bold', 'italic'])
  })

  it('describes list conversions once for consecutive items and detects nesting changes', () => {
    expect(structure(text('<ul><li>Old first</li><li>Second</li></ul>'), text('<ol><li>New first</li><li>Second</li></ol>')).map(({ spans, ...change }) => change)).to.deep.equal([
      { before: ['UL'], after: ['OL'], text: 'New first' }
    ])
    expect(structure(text('<ul><li>First<ul><li>Nested</li></ul></li></ul>'), text('<ul><li>First</li><li>Nested</li></ul>')).map(({ spans, ...change }) => change)).to.deep.equal([
      { before: ['UL', 'UL'], after: ['UL'], text: 'Nested' }
    ])
  })

  it('keeps structural descriptions aligned through paragraph insertion and whitespace', () => {
    expect(structure(text(' <p> Delivery takes three days. </p>'), text('<p>Introduction.</p><h3>Delivery takes two days.</h3>')).map(({ spans, ...change }) => change)).to.deep.equal([
      { before: ['P'], after: ['H3'], text: 'Delivery takes two days.' }
    ])
    expect(structure(text('<p>First</p><h2>Heading</h2>'), text('<h3>Inserted heading</h3><p>First</p><h2>Heading</h2>'))).to.deep.equal([])
    expect(structure(text('<p>Hello <b>world</b></p>'), text('<div>Welcome <em>world</em></div>'))).to.deep.equal([])
  })

  it('preserves table cell boundaries, empty cells and surrounding text', () => {
    const before = text('<p>Intro</p><table><tr><td>AB</td><td>C</td></tr><tr><td></td><td></td></tr></table><p>End</p>')
    const after = text('<p>Intro</p><table><tr><td>A</td><td>BC</td></tr><tr><td></td><td></td></tr></table><p>End</p>')
    expect(before.text).to.not.equal(after.text)
    expect(before.text).to.equal('Intro\n| AB | C |\n|  |  |\nEnd')
  })

  it('reads merged cells as safe text without rendering historical markup', () => {
    const parsed = text('<table><caption>Prices</caption><tr><th rowspan="2">Plan</th><td colspan="2"><b>€10</b></td></tr><tr><td>R&amp;D &lt;b&gt;</td><td></td></tr></table>')
    expect(parsed.text).to.equal('Prices\n| Plan | €10 |\n| R&D <b> |  |')
  })
})
