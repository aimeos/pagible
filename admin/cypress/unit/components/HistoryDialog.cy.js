import HistoryDialog from '../../../js/components/HistoryDialog.vue'
import { useSchemaStore } from '../../../js/stores'

const stubs = {
  // A transition stub inserts a wrapper that breaks Vuetify's direct-child overlay layout.
  transition: false,
  'transition-group': false,
}

const current = {
  data: { title: 'Current version' },
  files: {},
}

function mountDialog(props = {}) {
  const { schemas = {}, versions = [], ...values } = props
  return cy.mount(HistoryDialog, {
    props: {
      modelValue: true,
      current,
      readonly: false,
      load: () => Promise.resolve(versions),
      ...values,
    },
    global: { stubs, plugins: [{ install() { Object.assign(useSchemaStore(), schemas) } }] },
  })
}

describe('HistoryDialog', () => {
  it('preserves unchanged nested fields and blocks when restoring without a current snapshot', () => {
    const content = [{ id: 'keep', type: 'text', group: 'main', data: { text: 'Keep this block' } }]
    const latest = { id: 'latest', data: { content, meta: { seo: { title: 'Latest title', description: 'Keep description' } } } }
    const older = { id: 'older', data: { content: [...content, { id: 'add', type: 'text', group: 'main', data: { text: 'Restore this block' } }], meta: { seo: { title: 'Older title', description: 'Keep description' } } } }
    mountDialog({ current: null, versions: [latest, older], onApply: cy.spy().as('apply') })
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0]).to.deep.equal(older.data))
  })

  for (const type of ['audio', 'video']) it(`keeps native ${type} controls and preview error recovery`, () => {
    const file = { id: 'media', name: 'Recording', mime: type + '/mpeg', path: 'recording.' + (type === 'audio' ? 'mp3' : 'mp4') }
    mountDialog({ current: { data: { recording: null } }, versions: [{ data: { recording: { type: 'file', id: file.id } }, files: { [file.id]: file } }] })
    cy.get('.file ' + type).should('have.prop', 'controls', true).and('have.attr', 'preload', 'none').and('have.attr', 'crossorigin', 'anonymous')
    cy.get('.file ' + type).should('have.attr', 'src').and('include', file.path)
    cy.get('.file ' + type).scrollIntoView().trigger('error', { bubbles: false })
    cy.get('.file .media-error').should('contain', 'Preview unavailable')
    cy.get('.file ' + type).trigger('loadeddata')
    cy.get('.file .media-error').should('not.exist')
  })

  it('shows table rows as ordinary text and restores only the selected field with its original markup', () => {
    cy.viewport(390, 844)
    const before = '<p>Plans</p><table><tr><th>Name</th><th>Price</th></tr><tr><td>Basic</td><td>10</td></tr></table>'
    const after = '<p>Plans</p><table><tr><th>Name</th><th>Region</th><th>Price</th></tr><tr><td>Basic</td><td>EU</td><td>15</td></tr></table>'
    mountDialog({ current: { data: { text: before, title: 'Current' } },
      versions: [{ data: { text: after, title: 'Saved' } }], onApply: cy.spy().as('apply') })
    cy.get('.table-comparison').should('not.exist')
    cy.get('.change-old .diff-text').should('contain', '| Name | Price |').and('contain', '| Basic | 10 |')
    cy.get('.change-new .diff-text').should('contain', '| Name | Region | Price |').and('contain', '| Basic | EU | 15 |')
    cy.contains('.diff-group', 'text').find('.raw-details summary').click()
    cy.contains('.diff-group', 'text').find('.raw-details').should('contain', after)
    cy.get('.diff-group input[aria-label="title"]').uncheck()
    cy.get('.history-body, .version-diffs, .history-navigation, .history-actions').each(node => expect(node[0].scrollWidth).to.be.at.most(node[0].clientWidth + 1))
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0]).to.deep.equal({ text: after }))
  })

  it('exposes structural table changes in raw details when the displayed text is identical', () => {
    const before = '<table><tr><td>A</td></tr></table>', after = '<table><tr><th colspan="2">A</th></tr></table>'
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    cy.get('.source-note').should('contain', 'Source changed; displayed text is unchanged')
    cy.get('.raw-details summary').click()
    cy.get('.raw-details').should('contain', before).and('contain', after)
  })

  it('navigates once per field and keeps the chosen field highlighted when scrolling', () => {
    const before = 'Current start\nContext\nCurrent end', after = before.replaceAll('Current', 'Saved')
    mountDialog({ current: { data: { text: before, title: 'Current title' } },
      versions: [{ data: { text: after, title: 'Saved title' } }], onApply: cy.spy().as('apply') })
    cy.get('.navigation-count').should('have.text', 'Change 1 of 2')
    cy.contains('button', 'Next change').click()
    cy.focused().should('have.class', 'diff-group').and('contain', 'Saved title').and('have.attr', 'data-current-change')
    cy.get('.history-body').scrollTo('top', { ensureScrollable: false })
    cy.get('.navigation-count').should('have.text', 'Change 2 of 2')
    cy.contains('button', 'Previous change').click()
    cy.focused().should('contain', 'Saved start').and('contain', 'Saved end')
    cy.get('[data-current-change]').should('have.length', 1).and('have.attr', 'aria-current', 'true')
    cy.get('.diff-group input[aria-label="title"]').uncheck()
    cy.get('input[aria-label="Selected only"]').check()
    cy.get('.history-navigation').should('not.exist')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0]).to.deep.equal({ text: after }))
  })

  it('groups file changes into one gallery field and preserves gallery restoration', () => {
    const file = id => ({ id, name: id + '.jpg', path: id + '.jpg', mime: 'image/jpeg' })
    const files = Object.fromEntries(['a', 'b', 'c'].map(id => [id, file(id)]))
    const images = ids => ids.map(id => ({ type: 'file', id }))
    mountDialog({ current: { data: { images: images(['a', 'b']), title: 'Current' }, files },
      versions: [{ data: { images: images(['b', 'c']), title: 'Saved' }, files }], onApply: cy.spy().as('apply') })
    cy.get('.navigation-count').should('have.text', 'Change 1 of 2')
    cy.get('.diff-group input[aria-label="title"]').uncheck()
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0]).to.deep.equal({ images: images(['b', 'c']) }))
  })

  it('opens only the chosen image on desktop and mobile and returns focus when closed', () => {
    const image = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="400" height="200" fill="blue"/></svg>'
    cy.intercept('GET', '**/wide.svg', { headers: { 'content-type': 'image/svg+xml' }, body: image })
    cy.intercept('GET', '**/small.svg', { headers: { 'content-type': 'image/svg+xml' }, body: image })
    const before = { id: 'photo', name: 'Wide image', path: 'wide.svg', mime: 'image/svg+xml' }, after = { ...before, name: 'Small image', path: 'small.svg' }
    mountDialog({ current: { data: { image: 'photo' }, files: { photo: before } }, versions: [{ data: { image: 'photo' }, files: { photo: after } }] })
    cy.get('[aria-label="Enlarge Wide image"]').click()
    cy.get('[aria-label="Image preview"]').should('be.visible').and('contain', 'Current value').and('contain', 'Wide image')
    cy.get('[aria-label="Image preview"] img').should('have.length', 1).and('have.attr', 'alt', 'Wide image')
    cy.get('[aria-label="Close preview"]').click()
    cy.focused().should('have.attr', 'aria-label', 'Enlarge Wide image')
    cy.viewport(390, 844)
    cy.get('[aria-label="Enlarge Small image"]').scrollIntoView().click()
    cy.get('[aria-label="Image preview"] img').should('have.length', 1).and('have.attr', 'alt', 'Small image')
    cy.get('[aria-label="Image preview"] .v-card').then(card => expect(card[0].scrollWidth).to.be.at.most(card[0].clientWidth + 1))
    cy.get('[aria-label="Close preview"]').click()
  })

  it('explains identical displayed dates and references and retains their exact stored differences', () => {
    const before = '2026-09-09T10:00:00Z', after = '2026-09-09T12:00:00+02:00'
    const content = refid => [{ id: 'shared', type: 'reference', refid }]
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { publish_at: before, title: 'Current title', content: content('current-id') } },
      versions: [{ data: { publish_at: after, title: 'Saved title', content: content('saved-id') } }], onApply })
    cy.get('.source-note').should('have.length', 2).each(note => expect(note.text()).to.equal('Stored value changed; displayed value is unchanged'))
    cy.get('.diff-group[aria-label="publish at"] .raw-details summary').click()
    cy.get('.diff-group[aria-label="publish at"] .raw-details .change-old pre').should('have.text', before)
    cy.get('.diff-group[aria-label="publish at"] .raw-details .change-new pre').should('have.text', after)
    cy.get('.diff-group[aria-label="title"] input').uncheck()
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0].publish_at).to.equal(after)
      expect(spy.firstCall.args[0].content).to.deep.equal(content('saved-id'))
      expect(spy.firstCall.args[0].title).to.equal(undefined)
    })
  })

  it('honors explicit text schemas for date-like field names and distinguishes stored scalar types', () => {
    const block = date => ({ id: 'body', type: 'prose', group: 'main', data: { release_date: date } })
    mountDialog({ schemas: { content: { prose: { fields: { release_date: { type: 'plaintext' } } } } },
      current: { data: { content: [block('2026-09-09T10:00:00Z')], value: 1 } },
      versions: [{ data: { content: [block('2026-09-09T12:00:00+02:00')], value: '1' } }] })
    cy.get('.diff-group[aria-label="release date"] .change-old .diff-text').should('have.text', '2026-09-09T10:00:00Z')
    cy.get('.diff-group[aria-label="release date"] .change-new .diff-text').should('have.text', '2026-09-09T12:00:00+02:00')
    cy.get('.diff-group[aria-label="release date"] .source-note').should('not.exist')
    cy.get('.diff-group[aria-label="value"] .raw-details summary').click()
    cy.get('.diff-group[aria-label="value"] .raw-details .change-old pre').should('have.text', '1')
    cy.get('.diff-group[aria-label="value"] .raw-details .change-new pre').should('have.text', '"1"')
  })

  it('renders list numbering and nesting in comparisons and restores the original markup', () => {
    cy.viewport(390, 844)
    const before = '<ol start="3"><li>Prepare<ul><li>Check equipment</li></ul><p>Continue reviewing</p></li><li>Launch</li></ol>'
    const after = '<ol start="5"><li>Prepare<ul><li>Check supplies</li></ul><p>Continue reviewing</p></li><li>Launch</li></ol>'
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }], onApply })
    cy.contains('.change-old ol.history-line', 'Prepare').should('have.attr', 'start', '3')
    cy.contains('.change-new ol.history-line', 'Prepare').should('have.attr', 'start', '5').find('li').should('have.attr', 'aria-level', '1')
    cy.contains('.change-new ul.history-line', 'Check supplies').find('li').should('have.attr', 'aria-level', '2')
    cy.contains('.change-new ol.history-line', 'Launch').should('have.attr', 'start', '6')
    cy.contains('.shared-context .history-line', 'Continue reviewing').should('exist')
    cy.get('.source-note').should('not.exist')
    cy.get('.history-body, .version-diffs').each(node => expect(node[0].scrollWidth).to.be.at.most(node[0].clientWidth + 1))
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0].text).to.equal(after))
  })

  it('preserves list markers in whole-block snapshots', () => {
    const text = '<ol start="7" type="A"><li><b>First</b><p>Continuation</p></li><li>Second</li></ol>'
    mountDialog({ current: { data: { content: [] } }, versions: [{ data: { content: [{ id: 'list', type: 'text', group: 'main', data: { text } }] } }] })
    cy.get('ol.snapshot-value').first().should('have.attr', 'start', '7').and('have.attr', 'type', 'A').find('.format-bold').should('have.text', 'First')
    cy.contains('.snapshot-value', 'Continuation').should('have.prop', 'tagName', 'DIV').find('li').should('not.exist')
    cy.get('ol.snapshot-value').last().should('have.attr', 'start', '8')
  })

  it('preserves markers in unchanged context without treating bulleted insertions as renumbering', () => {
    const before = '<p>Old introduction</p><ul><li>First</li><li>Second</li><li>Third</li></ul>'
    const after = '<p>New introduction</p><ul><li>First</li><li>Inserted</li><li>Second</li><li>Third</li></ul>'
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    cy.contains('.shared-context ul.history-line', 'Second').find('li').should('have.attr', 'aria-level', '1')
    cy.contains('.shared-context ul.history-line', 'Third').should('exist')
  })

  it('explains file metadata and preview changes even when the image stays the same', () => {
    const file = { id: 'photo', name: 'Team.jpg', path: 'team.jpg', mime: 'image/jpeg', previews: { 320: 'small.jpg', 640: 'medium.jpg' } }
    const saved = { ...file, name: 'Team portrait.png', mime: 'image/png', previews: { 320: 'small.jpg', 960: 'large.jpg' } }
    mountDialog({ current: { data: {}, files: { photo: file } }, versions: [{ data: {}, files: { photo: saved } }] })
    cy.get('.media-row').should('contain', 'File changed')
    cy.get('.media-changes').should('contain', 'Filename').and('contain', 'Team.jpg').and('contain', 'Team portrait.png')
      .and('contain', 'File type').and('contain', 'image/jpeg').and('contain', 'image/png').and('not.contain', 'File path').and('not.contain', 'Preview (320)')
    cy.contains('.media-changes > div', 'Preview (640)').should('contain', 'medium.jpg').and('contain', 'Not present')
    cy.contains('.media-changes > div', 'Preview (960)').should('contain', 'Not present').and('contain', 'large.jpg')
    cy.get('.media-row .file').should('have.length', 2)
  })

  it('identifies a replacement even when both files have identical names and paths', () => {
    const file = { id: 'a', name: 'Team.jpg', path: 'team.jpg', mime: 'image/jpeg' }
    mountDialog({ current: { data: { image: { type: 'file', id: 'a' } }, files: { a: file } },
      versions: [{ data: { image: { type: 'file', id: 'b' } }, files: { b: { ...file, id: 'b' } } }] })
    cy.get('.media-row').should('contain', 'File replaced').find('.file').should('have.length', 2)
    cy.get('.media-changes').should('not.exist')
  })

  it('shows changed whitespace and blank lines while restoring the exact original text', () => {
    const before = 'SKU-1234\nA B\nTabs here\nLast', after = 'SKU-1235\nA  B\nTabs\there\n\nLast'
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }], onApply })
    cy.contains('.change-passage', 'SKU-1234').find('.change-old .highlight').should('have.text', '4')
    cy.contains('.change-passage', 'SKU-1235').find('.change-new .highlight').should('have.text', '5')
    cy.get('.change-new .whitespace[data-space="·"]').should('have.attr', 'aria-label', 'Space × 1')
    cy.get('.change-new .whitespace[data-space="⇥"]').should('have.attr', 'aria-label', 'Tab × 1')
    cy.get('.change-new [aria-label="Line break added"]').should('have.attr', 'data-space', '↵')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0].text).to.equal(after))
  })

  it('anchors named image changes between surrounding paragraphs and keeps them out of collapsed context', () => {
    const middle = Array.from({ length: 8 }, (_, i) => `<p>Context ${i}</p>`).join('')
    const before = '<p>Introduction</p><img src="/old.jpg" alt="Team portrait">' + middle + '<p>Ending</p>'
    const after = '<p>Introduction</p><img src="/new.jpg" alt="Team portrait">' + middle + '<p>Ending</p>'
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    cy.get('.image-change').scrollIntoView().should('be.visible').and('contain', 'Image 1 changed: Team portrait').and('contain', 'Image source')
    cy.get('.context-gap').should('exist').click()
    cy.get('.field-comparison').first().then(field => {
      const nodes = [...field[0].children]
      const image = nodes.findIndex(node => node.classList.contains('image-change'))
      expect(nodes.slice(0, image).some(node => node.textContent.includes('Introduction'))).to.equal(true)
      expect(nodes.slice(image + 1).some(node => node.textContent.includes('Context 0'))).to.equal(true)
    })
  })

  it('anchors removed images without alternative text before the following paragraph', () => {
    const before = '<p>Introduction</p><img src="/old.jpg"><p>Following paragraph</p>', after = '<p>Introduction</p><p>Following paragraph</p>'
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    cy.get('.image-change').should('contain', 'Image 1 removed').and('contain', '/old.jpg')
    cy.get('.image-change').next().should('contain', 'Following paragraph')
  })

  it('explains equivalent Markdown and highlights the original source only when raw details open', () => {
    const block = text => ({ id: 'body', type: 'prose', group: 'main', data: { text } })
    const before = '**Team**\n\nMeet us.', after = '__Team__\n\nMeet us.'
    const onApply = cy.spy().as('apply')
    mountDialog({ schemas: { content: { prose: { fields: { text: { type: 'markdown' } } } } },
      current: { data: { content: [block(before)] } }, versions: [{ data: { content: [block(after)] } }], onApply })
    cy.get('.source-note').should('have.text', 'Source changed; displayed text is unchanged')
    cy.get('.shared-context .format-bold').should('have.length', 1).and('have.text', 'Team')
    cy.get('.raw-details .highlight').should('not.exist')
    cy.get('.raw-details summary').click()
    cy.get('.raw-details .change-old pre').should('have.text', before)
    cy.get('.raw-details .change-new pre').should('have.text', after)
    cy.get('.raw-details .change-old .highlight').should('contain', '**')
    cy.get('.raw-details .change-new .highlight').should('contain', '__')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0].content).to.deep.equal([block(after)]))
  })

  it('identifies image attributes even when neighbouring text also changes without loading historical images', () => {
    const before = '<p>Current introduction</p><img src="/old.jpg" alt="Team" title="Portrait">'
    const after = '<p>Saved introduction</p><img src="/saved.jpg" alt="Team" title="New portrait" onerror="alert(1)">'
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    cy.get('.image-change').should('have.length', 1).and('contain', 'Image 1 changed').and('contain', 'Image source').and('contain', '/old.jpg').and('contain', '/saved.jpg').and('contain', 'Title').and('contain', 'New portrait').and('not.contain', 'onerror')
    cy.get('.version-diffs img, .image-change a').should('not.exist')
    cy.get('.raw-details summary').click()
    cy.get('.raw-details pre').last().should('have.text', after)
  })

  it('shares unchanged paragraph context once with formatting on desktop and mobile', () => {
    const before = '<p>Current opening</p><p>Keep <b>this context</b>.</p><p>Current ending</p>'
    const after = before.replaceAll('Current', 'Saved')
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    for (const width of [1100, 390]) {
      cy.viewport(width, 844)
      cy.get('.shared-context').should('have.length', 1).and('contain', 'Unchanged')
      cy.get('.shared-context .diff-text').should('have.text', 'Keep this context.')
      cy.get('.shared-context .format-bold').should('have.text', 'this context')
      cy.get('.paragraph-row').should('have.length', 2).and('not.contain', 'this context')
    }
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "History" as the title', () => {
    mountDialog()
    cy.contains('History').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[aria-label="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(HistoryDialog, {
      props: {
        modelValue: true,
        current,
        readonly: false,
        load: () => Promise.resolve([]),
        'onUpdate:modelValue': onUpdate,
      },
      global: { stubs },
    })
    cy.get('button[aria-label="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('shows "No changes" when load returns empty list', () => {
    mountDialog({
      versions: [],
    })
    cy.contains('No changes').should('exist')
  })

  it('shows loading state while fetching versions', () => {
    mountDialog({
      load: () => new Promise(() => {}),
    })
    cy.contains('Loading').should('exist')
  })

  it('calls load function when dialog opens', () => {
    const load = cy.stub().returns(Promise.resolve([])).as('load')
    mountDialog({ load })
    cy.get('@load').should('have.been.called')
  })

  it('renders timeline component', () => {
    mountDialog()
    cy.get('.v-timeline').should('exist')
  })

  it('shows added and removed images when the file changed', () => {
    const newImage = { id: 'files/new.jpg', name: 'new.jpg', mime: 'image/jpeg', path: 'files/new.jpg', previews: {} }
    const oldImage = { id: 'files/old.jpg', name: 'old.jpg', mime: 'image/jpeg', path: 'files/old.jpg', previews: {} }

    mountDialog({
      current: {
        data: { mime: 'image/jpeg', path: 'files/new.jpg' },
        files: { 'files/new.jpg': newImage },
      },
      versions: [
        { data: { mime: 'image/jpeg', path: 'files/old.jpg' }, files: { 'files/old.jpg': oldImage }, created_at: '2026-01-01T00:00:00Z' },
      ],
    })

    cy.get('.media-list .file.added .v-img').should('exist')
    cy.get('.media-list .file.removed .v-img').should('exist')
  })

  it('shows a green published dot for the latest published version without changes', () => {
    mountDialog({
      current: { data: { title: 'Current version' }, files: {} },
      versions: [
        { published: true, editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Current version' }, files: {} },
      ],
    })

    cy.get('.v-timeline-item .v-timeline-divider__inner-dot.bg-success').should('exist')
    cy.contains('.v-timeline-item', 'editor@example.com').should('exist')
  })

  it('shows unsaved changes once alongside the latest saved date, editor and publication status', () => {
    mountDialog({
      current: { data: { title: 'Edited version' }, files: {} },
      versions: [
        { published: true, editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Saved version' }, files: {} },
      ],
    })

    cy.get('.version-card').should('have.length', 1)
    cy.get('.version-heading').should('contain', 'Unsaved changes').and('contain', 'Latest saved').and('contain', 'Published').and('contain', 'editor@example.com')
    cy.get('.version-title').should('contain', '2026')
    cy.get('.v-timeline-item .v-timeline-divider__inner-dot.bg-success').should('exist')
  })

  it('marks a scheduled latest version with the publish class', () => {
    mountDialog({
      current: { data: { title: 'Current version' }, files: {} },
      versions: [
        { published: false, publish_at: '2099-01-01T00:00:00Z', editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Current version' }, files: {} },
      ],
    })

    cy.get('.v-timeline-item.publish').should('exist')
  })

  it('does not show a previews diff when the file changed', () => {
    mountDialog({
      current: {
        data: { name: 'new.jpg', path: 'files/new.jpg', previews: { 200: 'files/new-200.webp' } },
        files: {},
      },
      versions: [
        { data: { name: 'old.jpg', path: 'files/old.jpg', previews: { 200: 'files/old-200.webp' } }, files: {}, created_at: '2026-01-01T00:00:00Z' },
      ],
    })

    cy.contains('.version-diffs', 'new.jpg').should('exist')
    cy.get('.version-diffs').should('not.contain', 'new-200.webp')
    cy.get('.version-diffs').should('not.contain', 'old-200.webp')
  })

  it('restores only the checked content block', () => {
    const a = { id: 'a', type: 'text', group: 'main', data: { text: 'Saved A' } }
    const b = { id: 'b', type: 'text', group: 'main', data: { text: 'Saved B' } }
    const editedA = { ...a, data: { text: 'Edited A' } }, editedB = { ...b, data: { text: 'Edited B' } }
    const onApply = cy.spy().as('apply')
    mountDialog({
      current: { data: { content: [editedA, editedB] } },
      versions: [{ data: { content: [a, b] } }], onApply
    })
    cy.contains('.diff-block', 'Edited B').find('.block-check input[type="checkbox"]').uncheck()
    cy.contains('1 of 2 selected').should('exist')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0]).to.deep.equal({ content: [a, editedB] })
    })
  })

  it('compares historical values against the current editor and preserves unchecked nested fields', () => {
    const onApply = cy.spy().as('apply')
    mountDialog({
      current: { data: { meta: { seo: { title: 'Unsaved title', description: 'Unsaved description' } } } },
      versions: [
        { data: { meta: { seo: { title: 'Latest title', description: 'Latest description' } } } },
        { data: { meta: { seo: { title: 'Old title', description: 'Old description' } } } }
      ], onApply
    })
    cy.get('.version-heading').last().click()
    cy.get('.version-card').last().within(() => {
      cy.contains('Current value → Saved value').should('exist')
      cy.get('.change-old').should('contain', 'Unsaved title')
      cy.get('.change-new').should('contain', 'Old title')
      cy.contains('.diff-group', 'description').find('input[type="checkbox"]').uncheck()
    })
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0]).to.deep.equal({ meta: { seo: { title: 'Old title', description: 'Unsaved description' } } })
    })
  })

  it('expands the newest changes and mounts older comparisons only when opened', () => {
    mountDialog({
      versions: [
        { data: { title: 'Current version' } }, { data: { title: 'Previous' } }, { data: { title: 'Oldest' } }
      ]
    })
    cy.get('.version-heading').eq(1).should('have.attr', 'aria-expanded', 'true')
    cy.get('.version-card').last().find('.version-diffs').should('not.exist')
    cy.get('.version-heading').last().click()
    cy.get('.version-card').last().find('.version-diffs').should('contain', 'Oldest')
    cy.get('.version-diffs').should('have.length', 1)
    cy.get('.version-heading').last().click()
    cy.get('.version-card').last().find('.version-diffs').should('not.exist')
  })

  it('shows moves and simultaneous group and text changes', () => {
    const a = { id: 'a', type: 'text', group: 'main', data: { text: 'A' } }
    const b = { id: 'b', type: 'text', group: 'main', data: { text: 'B' } }
    mountDialog({
      current: { data: { content: [b, { ...a, group: 'aside', data: { text: 'Edited A' } }] } },
      versions: [{ data: { content: [a, b] } }]
    })
    cy.contains('Moved from position 2 to 1').should('exist')
    cy.contains('.diff-group', 'group').should('contain', 'main').and('contain', 'aside')
    cy.contains('.diff-block', 'Edited A').should('exist')
  })

  it('hides selections and restoration controls in read-only mode', () => {
    mountDialog({ readonly: true, versions: [{ data: { title: 'Saved title' } }] })
    cy.get('.version-diffs').should('exist')
    cy.get('.version-diffs input[type="checkbox"]').should('not.exist')
    cy.get('.diff-actions').should('not.exist')
  })

  it('disables restoration when no changes are selected', () => {
    mountDialog({ versions: [{ data: { title: 'Saved title' } }] })
    cy.get('.selection-bar input[aria-label="Select all"]').uncheck()
    cy.contains('0 of 1 selected').should('exist')
    cy.contains('button', 'Restore selected changes').should('be.disabled')
  })

  it('shows readable rich text, identifies formatting changes and safely exposes raw details', () => {
    mountDialog({
      current: { data: { text: '<p>Hello <em>world</em></p><script>window.historyInjected=true</script>' } },
      versions: [{ data: { text: '<p>Hello <strong>world</strong></p>' } }]
    })
    cy.get('.diff-text').should('contain', 'Hello world').and('not.contain', '<p>')
    cy.contains('Bold added to “world”').should('exist')
    cy.contains('Italic removed from “world”').should('exist')
    cy.contains('summary', 'Show raw details').click()
    cy.get('.raw-details pre').should('contain', '<strong>')
    cy.get('.version-diffs script, .version-diffs em, .version-diffs strong').should('not.exist')
    cy.window().its('historyInjected').should('not.exist')
  })

  it('formats statuses, booleans and empty values', () => {
    mountDialog({
      current: { data: { status: 2, active: true, title: '' } },
      versions: [{ data: { status: 0, active: false, title: 'Old' } }]
    })
    cy.contains('.diff-group', 'status').should('contain', 'Hidden in navigation').and('contain', 'Disabled')
    cy.contains('.diff-group', 'active').should('contain', 'Enabled').and('contain', 'Disabled')
    cy.contains('.diff-group', 'title').should('contain', 'Empty')
  })

  it('collapses long unchanged context and expands it on request', () => {
    const prefix = 'A long unchanged introduction. '.repeat(30)
    mountDialog({
      current: { data: { title: prefix + 'New ending' } },
      versions: [{ data: { title: prefix + 'Old ending' } }]
    })
    cy.get('.diff-text').first().invoke('text').should('have.length.lessThan', 200)
    cy.contains('button', 'Show unchanged text').click()
    cy.get('.diff-text').first().invoke('text').should('contain', prefix)
    cy.contains('button', 'Hide unchanged text').should('exist')
  })

  it('shows media captions and filenames for a replacement with the same ID', () => {
    const file = { id: 'file', path: 'old.jpg', name: 'old.jpg', mime: 'image/jpeg', previews: {} }
    mountDialog({
      current: { data: { title: 'Current version' }, files: { file: { ...file, name: 'new.jpg', path: 'new.jpg' } } },
      versions: [{ data: { title: 'Current version' }, files: { file } }]
    })
    cy.get('.file.removed figcaption').should('have.text', 'new.jpg')
    cy.get('.file.added figcaption').should('have.text', 'old.jpg')
    cy.get('.media-label').first().should('have.text', 'Current value')
    cy.get('.media-label').last().should('have.text', 'Saved value')
    cy.contains('.media-changes > div', 'File path').should('contain', 'new.jpg').and('contain', 'old.jpg')
  })

  it('stacks comparison columns on small screens without horizontal overflow', () => {
    cy.viewport(390, 844)
    mountDialog({ current: { data: { text: 'Updated paragraph. '.repeat(10) } }, versions: [{ data: { text: 'Saved paragraph. '.repeat(10) } }] })
    cy.get('.diff-columns').first().children().then(sides => {
      const before = sides[0].getBoundingClientRect(), after = sides[1].getBoundingClientRect()
      expect(after.top).to.be.greaterThan(before.bottom)
      expect(after.left).to.be.closeTo(before.left, 1)
    })
    cy.get('.v-dialog > .v-overlay__content').then(content => {
      expect(content[0].scrollWidth).to.be.at.most(content[0].clientWidth)
    })
    cy.screenshot('history-mobile', { capture: 'viewport' })
  })

  it('reports a loading error and allows retry', () => {
    const load = cy.stub().onFirstCall().rejects(new Error('Unavailable')).onSecondCall().resolves([])
    mountDialog({ load })
    cy.get('[role="alert"]').should('contain', 'Error fetching versions')
    cy.contains('button', 'Retry').click()
    cy.contains('No changes').should('exist')
  })
  it('uses schema labels and rich text excerpts to identify blocks', () => {
    mountDialog({
      schemas: { content: { trio: { label: 'Text Trio', fields: { leading: { type: 'string', label: 'Leading text' } } } } },
      current: { data: { content: [{ id: 'a', type: 'trio', group: 'footer', data: { text: '<p>Useful <strong>context</strong></p>', leading: 'New lead' } }] } },
      versions: [{ data: { content: [{ id: 'a', type: 'trio', group: 'footer', data: { text: '<p>Useful <strong>context</strong></p>', leading: 'Old lead' } }] } }]
    })
    cy.contains('.diff-block h4', 'Text Trio: Useful context').should('exist')
    cy.contains('.block-location', 'Group: footer · Position 1').should('exist')
    cy.contains('.diff-group', 'Leading text').should('contain', 'New lead')
    cy.contains('.diff-group', 'Leading text').find('.scalar-values').should('exist')
  })

  it('restores a single field within a moved block and leaves other edits and movement intact', () => {
    const a = { id: 'a', type: 'text', group: 'main', data: { title: 'Old title', text: 'Old text' } }
    const b = { id: 'b', type: 'text', group: 'main', data: { text: 'B' } }
    const edited = { ...a, group: 'footer', data: { title: 'New title', text: 'New text' } }
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { content: [b, edited] } }, versions: [{ data: { content: [a, b] } }], onApply })
    cy.get('.selection-bar input[aria-label="Select all"]').uncheck()
    cy.contains('.diff-block', 'New title').find('.diff-group[aria-label="title"] input').check()
    cy.contains('1 of 4 selected').should('exist')
    cy.contains('.diff-block', 'New title').find('.block-check [aria-checked="mixed"]').should('exist')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0].content).to.deep.equal([b, { ...edited, data: { title: 'Old title', text: 'New text' } }])
    })
  })

  it('filters to selected changes without losing selections when versions are collapsed', () => {
    mountDialog({ current: { data: { title: 'New', name: 'Keep' } }, versions: [{ data: { title: 'Old', name: 'Old name' } }] })
    cy.get('.diff-group[aria-label="name"] input').uncheck()
    cy.get('input[aria-label="Selected only"]').check()
    cy.get('.diff-group[aria-label="name"]').should('not.exist')
    cy.get('.diff-group[aria-label="title"]').should('exist')
    cy.get('.version-heading').first().click().click()
    cy.get('.diff-group[aria-label="name"] input').should('not.be.checked')
    cy.contains('1 of 2 selected').should('exist')
  })

  it('shows precise link destination changes without rendering historical links', () => {
    mountDialog({ current: { data: { text: '<p><a href="/new">Delivery</a></p>' } }, versions: [{ data: { text: '<p><a href="/old">Delivery</a></p>' } }] })
    cy.contains('Link destination for “Delivery”').should('exist')
    cy.get('.link-change').should('contain', '/old').and('contain', '/new')
    cy.get('.version-diffs a').should('not.exist')
  })

  it('displays repeated items individually using schema identities and labels', () => {
    const data = items => ({ type: 'links', data: { links: items } })
    mountDialog({
      schemas: { content: { links: { fields: { links: { type: 'items', identity: '_key', label: 'Quick links', item: { title: { type: 'string', label: 'Link title' } } } } } } },
      current: { data: data([{ _key: 'a', title: 'Changed link' }, { _key: 'c', title: 'Added link' }]) },
      versions: [{ data: data([{ _key: 'a', title: 'Old link' }, { _key: 'b', title: 'Removed link' }]) }]
    })
    cy.contains('.diff-group', 'Quick links').find('.array-item').should('have.length', 3)
    cy.contains('.array-item', 'Added link').should('contain', 'Item removed')
    cy.contains('.array-item', 'Removed link').should('contain', 'Item added')
    cy.contains('.array-item', 'Changed link').should('contain', 'Link title')
  })

  it('places media under the changed field and opens an enlarged preview', () => {
    const file = id => ({ id, name: id + '.jpg', path: id + '.jpg', mime: 'image/jpeg', previews: {} })
    const data = id => ({ type: 'hero', data: { image: { type: 'file', id } } })
    mountDialog({
      schemas: { content: { hero: { fields: { image: { type: 'image', label: 'Hero image' } } } } },
      current: { data: data('new'), files: { new: file('new') } },
      versions: [{ data: data('old'), files: { old: file('old') } }]
    })
    cy.contains('.diff-group', 'Hero image').within(() => {
      cy.get('.file.removed').should('contain', 'new.jpg')
      cy.get('.file.added').should('contain', 'old.jpg')
      cy.get('button[aria-label="Enlarge new.jpg"]').click()
    })
    cy.get('[aria-label="Image preview"]').should('be.visible')
    cy.get('button[aria-label="Close preview"]').click()
    cy.get('.version-diffs > .diff-section > .media-list').should('not.exist')
  })

  it('keeps the selected count and restore action visible when scrolling a long version', () => {
    cy.viewport(1000, 700)
    const content = Array.from({ length: 12 }, (_, i) => ({ id: String(i), type: 'text', group: 'main', data: { text: 'Text ' + i } }))
    mountDialog({ current: { data: { content: content.map(block => ({ ...block, data: { text: 'Changed ' + block.id } })) } }, versions: [{ data: { content } }] })
    cy.get('.history-actions').should('be.visible').then(footer => {
      const top = footer[0].getBoundingClientRect().top
      cy.get('.history-body').scrollTo('bottom')
      cy.get('.history-actions').should('be.visible').then(current => expect(current[0].getBoundingClientRect().top).to.be.closeTo(top, 1))
    })
    cy.contains('.history-actions', '12 of 12 selected').should('be.visible')
    cy.contains('button', 'Restore selected changes').should('be.visible')
    cy.screenshot('history-desktop-controls', { capture: 'viewport' })
  })

  it('shows current values on the left and the values that will be restored on the right for unsaved changes', () => {
    const block = id => ({ id, type: 'text', group: 'main', data: { text: id } })
    const onApply = cy.spy().as('apply')
    mountDialog({
      current: { data: { title: 'Unsaved', content: [block('new')] } },
      versions: [{ data: { title: 'Saved', content: [block('old')] } }], onApply
    })
    cy.contains('.diff-group', 'title').within(() => {
      cy.get('.change-old').should('contain', 'Unsaved')
      cy.get('.change-new').should('contain', 'Saved')
    })
    cy.contains('.diff-block', 'new').should('contain', 'Block removed')
    cy.contains('.diff-block', 'old').should('contain', 'Block added')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0]).to.deep.equal({ title: 'Saved', content: [block('old')] })
    })
  })

  it('aligns paragraphs and expands each unchanged gap independently', () => {
    cy.viewport(1100, 900)
    const first = Array.from({ length: 6 }, (_, i) => 'First context ' + i)
    const last = Array.from({ length: 6 }, (_, i) => 'Last context ' + i)
    const current = ['Start now', ...first, 'Middle now', ...last, 'End now'].join('\n')
    const saved = ['Start then', ...first, 'Middle then', 'Inserted paragraph', ...last, 'End then'].join('\n')
    mountDialog({ current: { data: { text: current } }, versions: [{ data: { text: saved } }] })
    cy.get('.context-gap').should('have.length', 2).and('contain', '4 unchanged paragraphs')
    cy.get('.context-gap').first().click()
    cy.get('.shared-context').should('contain', 'First context 2').and('not.contain', 'Last context 2')
    cy.get('.context-gap').last().should('have.attr', 'aria-expanded', 'false')
    cy.contains('.paragraph-row', 'Inserted paragraph').within(() => {
      cy.get('.change-old').should('have.class', 'empty-paragraph')
      cy.get('.diff-text').first().should('be.empty')
    })
    cy.get('.paragraph-row').each(row => {
      const [a, b] = row[0].children
      expect(a.getBoundingClientRect().top).to.be.closeTo(b.getBoundingClientRect().top, 1)
    })
    cy.screenshot('history-paragraphs', { capture: 'viewport' })
  })

  it('preserves field and movement selections while collapsing and navigating blocks', () => {
    const a = { id: 'a', type: 'text', group: 'main', data: { title: 'Old A', text: 'Old body' } }
    const b = { id: 'b', type: 'text', group: 'main', data: { text: 'Old B' } }
    const edited = { ...a, data: { title: 'New A', text: 'New body' } }
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { content: [b, edited] } }, versions: [{ data: { content: [a, b] } }], onApply })
    cy.get('.diff-group[aria-label="text"] input').uncheck()
    cy.get('.block-move input').uncheck()
    cy.get('.block-toggle').click().should('have.attr', 'aria-expanded', 'false')
    cy.get('.block-details').should('not.exist')
    cy.get('.block-summary').should('contain', '2 fields changed').and('contain', '1 of 3 selected')
    cy.contains('button', 'Next change').click()
    cy.focused().should('have.attr', 'aria-label', 'title')
    cy.get('.block-toggle').should('have.attr', 'aria-expanded', 'true')
    cy.get('.diff-group[aria-label="text"] input').should('not.be.checked')
    cy.get('.block-move input').should('not.be.checked')
    cy.contains('button', 'Previous change').click()
    cy.focused().should('have.class', 'block-move')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0].content).to.deep.equal([b, { ...edited, data: { title: 'Old A', text: 'New body' } }])
    })
  })

  it('opens collapsed blocks when navigating a long version and skips changes hidden by the selection filter', () => {
    const content = Array.from({ length: 7 }, (_, i) => ({ id: String(i), type: 'text', group: 'main', data: { text: 'Saved ' + i } }))
    mountDialog({ current: { data: { content: content.map(block => ({ ...block, data: { text: 'Edited ' + block.id } })) } }, versions: [{ data: { content } }] })
    cy.get('.block-details').should('have.length', 1)
    cy.get('.diff-block').eq(1).find('.block-check input').uncheck()
    cy.get('input[aria-label="Selected only"]').check()
    cy.contains('button', 'Next change').click()
    cy.focused().should('contain', 'Saved 2')
    cy.get('.history-navigation').should('contain', 'Change 2 of 6')
    cy.contains('.history-actions', '6 of 7 selected').should('exist')
  })

  it('keeps unchanged media neutral and pairs images across insertions and moves', () => {
    const file = id => ({ id, name: id + '.jpg', path: id + '.jpg', mime: 'image/jpeg', previews: {} })
    const files = Object.fromEntries(['a', 'b', 'c', 'd'].map(id => [id, file(id)]))
    const data = ids => ({ images: ids.map(id => ({ type: 'file', id })) })
    mountDialog({ current: { data: data(['a', 'b', 'c']), files }, versions: [{ data: data(['c', 'a', 'd', 'b']), files }] })
    cy.get('.media-row').should('have.length', 4)
    cy.get('.media-row').eq(0).should('contain', 'Moved from position 3 to 1').find('.file').should('have.length', 1)
    cy.get('.media-row').eq(1).should('contain', 'Unchanged').find('.file').should('have.length', 1)
    cy.get('.media-row').eq(1).find('.removed, .added').should('not.exist')
    cy.get('.media-row').eq(2).should('contain', 'File added').and('contain', 'd.jpg')
    cy.get('.media-row').eq(2).find('.empty-media').should('exist')
    cy.get('.media-row').eq(3).find('figcaption').should('have.length', 1).and('have.text', 'b.jpg')
  })

  it('supports change navigation in read-only mode on small screens', () => {
    cy.viewport(390, 844)
    mountDialog({ readonly: true, current: { data: { title: 'New title', name: 'New name' } }, versions: [{ data: { title: 'Old title', name: 'Old name' } }] })
    cy.contains('button', 'Next change').click()
    cy.focused().should('have.attr', 'aria-label', 'name')
    cy.contains('button', 'Next change').should('be.disabled')
    cy.get('.history-navigation').then(nav => expect(nav[0].scrollWidth).to.be.at.most(nav[0].clientWidth))
    cy.get('input[type="checkbox"], .history-actions').should('not.exist')
  })

  it('shows text edits without misleading formatting notices when bold is retained', () => {
    mountDialog({ current: { data: { text: '<p><b>Hello</b></p>' } }, versions: [{ data: { text: '<p><b>Welcome</b></p><p>Additional information</p>' } }] })
    cy.get('.diff-text').should('contain', 'Hello').and('contain', 'Welcome').and('contain', 'Additional information')
    cy.get('.formatting-changes, .markup-note').should('not.exist')
  })

  it('compares consecutive saves without applying them and preserves partial restoration selections', () => {
    const onApply = cy.spy().as('apply')
    mountDialog({
      current: { data: { title: 'Unsaved title', name: 'Unsaved name' } }, onApply,
      versions: [
        { id: 'newest', created_at: '2026-09-09T10:00:00Z', data: { title: 'Newest title', name: 'Newest name' } },
        { id: 'middle', created_at: '2026-09-08T10:00:00Z', data: { title: 'Middle title', name: 'Middle name' } },
        { id: 'oldest', created_at: '2026-09-07T10:00:00Z', data: { title: 'Oldest title', name: 'Oldest name' } }
      ]
    })
    cy.get('.diff-group[aria-label="name"] input').uncheck()
    cy.get('input[aria-label="Selected only"]').check()
    cy.contains('button', 'Changes in this save').click()
    cy.get('.version-heading').first().should('be.visible')
    cy.get('.change-old').should('contain', 'Middle title').and('not.contain', 'Unsaved title')
    cy.get('.change-new').should('contain', 'Newest title')
    cy.get('.comparison-source').should('contain', '2026')
    cy.get('.selection-bar, .diff-actions, .is-unselected').should('not.exist')
    cy.contains('button', 'Restore selected changes').should('not.exist')
    cy.get('.version-heading').eq(1).click()
    cy.get('.change-old').should('contain', 'Oldest title')
    cy.get('.change-new').should('contain', 'Middle title')
    cy.get('.version-heading').first().click()
    cy.contains('button', 'Select changes to restore').click()
    cy.get('.diff-group[aria-label="name"] input').should('not.be.checked')
    cy.get('.change-old').should('contain', 'Unsaved title')
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0]).to.deep.equal({ title: 'Newest title' })
      expect(spy.firstCall.args[1].id).to.equal('newest')
    })
  })

  it('explains missing earlier history and permits inspection in read-only mode', () => {
    mountDialog({ readonly: true, versions: [{ data: { title: 'Saved title' } }] })
    cy.contains('button', 'Changes in this save').click()
    cy.get('.version-diffs').should('contain', 'No earlier version available')
    cy.get('.diff-group, .history-actions, input[type="checkbox"]').should('not.exist')
    cy.contains('button', 'Restore changes').click()
    cy.get('.diff-text').should('contain', 'Saved title')
  })

  it('labels unchecked scalar and atomic block changes without promising restoration', () => {
    mountDialog({ current: { data: { title: 'Keep title', content: [] } }, versions: [
      { data: { title: 'Saved title', content: [{ id: 'a', type: 'text', data: { text: 'Saved block' } }] } }
    ] })
    cy.get('input[aria-label="Select all"]').uncheck()
    cy.get('.diff-group[aria-label="title"]').should('have.class', 'is-unselected').and('contain', 'Keep current value')
    cy.get('.diff-group[aria-label="title"] .change-old .highlight').should('have.css', 'text-decoration-line', 'none')
    cy.get('.diff-block').should('have.class', 'is-unselected').and('contain', 'Keep current value')
    cy.get('.change-new').should('contain', 'Saved value').and('not.contain', 'Value after restoration')
    cy.get('.diff-group[aria-label="title"] input').check()
    cy.get('.diff-group[aria-label="title"]').should('not.have.class', 'is-unselected').and('contain', 'Value after restoration')
  })

  it('shows shared element names with IDs available only in raw details', () => {
    mountDialog({
      current: { data: { content: [{ type: 'reference', refid: 'new-uuid' }] }, elements: [{ id: 'new-uuid', name: 'New campaign' }] },
      versions: [{ data: { content: [{ type: 'reference', refid: 'old-uuid' }] }, elements: [{ id: 'old-uuid', name: 'Original campaign' }] }]
    })
    cy.contains('.block-title', 'Shared element: New campaign').should('exist')
    cy.contains('.block-title', 'Shared element: Original campaign').should('exist')
    cy.get('.block-title').should('not.contain', 'uuid')
    cy.get('.diff-block .diff-group').should('not.exist')
    cy.get('.raw-details pre').should('not.be.visible')
    cy.get('.raw-details summary').first().click()
    cy.get('.raw-details pre').first().should('be.visible').and('contain', 'new-uuid')
  })

  it('describes a movement in the collapsed summary without zero changed fields', () => {
    const a = { id: 'a', type: 'text', data: { text: 'A' } }, b = { id: 'b', type: 'text', data: { text: 'B' } }
    mountDialog({ current: { data: { content: [a, b] } }, versions: [{ data: { content: [b, a] } }] })
    cy.get('.block-toggle').click()
    cy.get('.block-details').should('not.exist')
    cy.get('.block-summary').should('contain', 'Moved from position').and('not.contain', '0 fields changed')
  })

  it('describes headings and lists alongside simultaneous text edits', () => {
    mountDialog({ current: { data: { text: '<p>Old title</p><ul><li>First old item</li><li>Second item</li></ul>' } },
      versions: [{ data: { text: '<h2>New title</h2><ol><li>First new item</li><li>Second item</li></ol>' } }] })
    cy.get('.structure-changes').should('contain', 'Paragraph → Heading 2').and('contain', 'Bulleted list → Numbered list')
    cy.get('.structure-changes li').should('have.length', 2)
    cy.get('.diff-text').should('contain', 'Old title').and('contain', 'New title')
    cy.get('.markup-note, .version-diffs h2').should('not.exist')
  })

  it('keeps absence labels separate from literal values and word highlights', () => {
    mountDialog({ current: { data: { title: 'Removed', name: null, text: '<p><br></p>' } },
      versions: [{ data: { name: 'Empty', text: 'Visible text', added: 'New value' } }] })
    cy.get('.diff-group[aria-label="title"] .change-old .highlight').should('have.text', 'Removed')
    cy.get('.diff-group[aria-label="title"] .change-new .value-placeholder').should('have.text', 'Removed')
    cy.get('.diff-group[aria-label="name"] .change-old .value-placeholder').should('have.text', 'Empty')
    cy.get('.diff-group[aria-label="name"] .change-new .highlight').should('have.text', 'Empty')
    cy.get('.diff-group[aria-label="text"] .change-old .value-placeholder').should('have.text', 'Empty')
    cy.get('.diff-group[aria-label="added"] .change-old .value-placeholder').should('have.text', 'Not present')
    cy.get('.is-placeholder .highlight').should('not.exist')
    cy.get('.is-placeholder').should('have.css', 'background-color', 'rgba(0, 0, 0, 0)')
  })

  it('previews whole blocks once and restores an addition without removing an unchecked block', () => {
    const old = { id: 'old', type: 'text', group: 'main', data: { text: '<p>Saved block</p>' } }
    const current = { id: 'new', type: 'text', group: 'aside', data: { text: '<p>Keep this block</p>' } }
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { content: [current] } }, versions: [{ data: { content: [old] } }], onApply })
    cy.contains('.diff-block', 'Keep this block').within(() => {
      cy.get('.snapshot-value').should('have.text', 'Keep this block')
      cy.get('.block-check input').uncheck()
      cy.get('.block-raw summary').click()
      cy.get('.block-raw pre').should('contain', '"id": "new"').and('contain', '"group": "aside"')
    })
    cy.contains('.diff-block', 'Saved block').find('.snapshot-value').should('have.text', 'Saved block')
    cy.get('.diff-block .highlight, .diff-block .change-old, .diff-block .change-new, .diff-block .diff-group[aria-label="type"]').should('not.exist')
    cy.get('.diff-block input[type="checkbox"]').should('have.length', 2)
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0].content).to.have.length(2)
      expect(spy.firstCall.args[0].content).to.deep.include(old).and.deep.include(current)
    })
  })

  it('shows a single media preview for a whole added block and keeps enlargement available', () => {
    const file = { id: 'photo', name: 'Campaign photo', mime: 'image/jpeg', path: 'photo.jpg', previews: {} }
    mountDialog({ current: { data: { content: [] } }, versions: [
      { data: { content: [{ id: 'a', type: 'image', data: { image: { type: 'file', id: 'photo' } } }] }, files: { photo: file } }
    ] })
    cy.get('.diff-block .media-side').should('have.length', 1)
    cy.get('.diff-block .file').should('contain', 'Campaign photo').and('not.have.class', 'added')
    cy.get('.diff-block .media-status, .diff-block .empty-media').should('not.exist')
    cy.get('.media-zoom').click()
    cy.get('[aria-label="Image preview"]').should('be.visible')
  })

  it('keeps mobile controls compact and exposes both whole-version actions through a menu', () => {
    cy.viewport(390, 844)
    const onRevert = cy.spy().as('revert'), onUse = cy.spy().as('use')
    mountDialog({ current: { data: { title: 'Current' } }, onRevert, onUse,
      versions: [{ id: 'latest', data: { title: 'Latest' } }, { id: 'old', data: { title: 'Old' } }] })
    cy.get('.history-actions').should('be.visible').then(footer => expect(footer[0].getBoundingClientRect().height).to.be.lessThan(115))
    cy.get('.restore-selected').should('be.visible')
    cy.contains('.history-actions', '1 of 1 selected').should('be.visible')
    cy.contains('button', 'Discard all changes').should('not.exist')
    cy.get('button[aria-label="More restoration options"]').click()
    cy.contains('.v-list-item', 'Discard all changes').click()
    cy.get('@revert').should('have.been.calledOnce')
    cy.get('.version-heading').last().click()
    cy.get('button[aria-label="More restoration options"]').click()
    cy.contains('.v-list-item', 'Restore version').click()
    cy.get('@use').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0].id).to.equal('old'))
    cy.get('.history-actions').then(footer => expect(footer[0].scrollWidth).to.be.at.most(footer[0].clientWidth))
  })

  it('keeps formatting-only paragraphs visible and puts their notices next to them', () => {
    const lines = Array.from({ length: 16 }, (_, i) => '<p>Paragraph ' + i + '</p>')
    const saved = [...lines]
    saved[0] = '<p>Edited start</p>'
    saved[7] = '<p><b>Paragraph 7</b></p>'
    saved[10] = '<h2>Paragraph 10</h2>'
    saved[15] = '<p>Edited end</p>'
    mountDialog({ current: { data: { text: lines.join('') } }, versions: [{ data: { text: saved.join('') } }] })
    cy.contains('.change-passage', 'Bold added to “Paragraph 7”').should('contain', 'Paragraph 7').find('.paragraph-row').should('not.have.class', 'unchanged')
    cy.contains('.change-passage', 'Paragraph → Heading 2').should('contain', 'Paragraph 10').find('.paragraph-row').should('not.have.class', 'unchanged')
    cy.get('.context-gap').should('exist')
    cy.get('.diff-text').should('not.contain', 'Paragraph 3')
    cy.get('.diff-text').should('contain', 'Paragraph 7').and('contain', 'Paragraph 10')
  })

  it('shows loading and failure states while preserving media names and enlargement', () => {
    cy.viewport(1100, 900)
    const pending = []
    cy.intercept('GET', '**/slow-preview.svg', req => new Promise(resolve => {
      pending.push(() => { req.reply({ statusCode: 404, body: '' }); resolve() })
    })).as('preview')
    const file = { id: 'photo', name: 'Campaign image', mime: 'image/svg+xml', path: 'slow-preview.svg', previews: {} }
    mountDialog({ current: { data: { image: null } }, versions: [{ data: { image: { type: 'file', id: file.id } }, files: { photo: file } }] })
    cy.get('.media-zoom').scrollIntoView()
    cy.contains('.media-loading', 'Loading preview').should('be.visible')
    cy.wrap(pending).should('have.length', 1).then(queue => queue.shift()())
    cy.wait('@preview')
    cy.contains('.media-error', 'Preview unavailable').should('be.visible')
    cy.get('.file').should('contain', 'Campaign image')
    cy.get('.media-zoom').click()
    cy.get('[aria-label="Image preview"]').should('contain', 'Campaign image').and('contain', 'Loading preview')
    cy.wrap(pending).should('have.length', 1).then(queue => queue.shift()())
    cy.get('[aria-label="Image preview"] .media-error').should('be.visible')
    cy.get('button[aria-label="Close preview"]').click()
  })

  it('decodes table entities without interpreting escaped tags and preserves empty cells', () => {
    mountDialog({ current: { data: { text: '<table><tr><td>R&amp;D &lt;b&gt;</td><td></td></tr></table>' } },
      versions: [{ data: { text: '<table><tr><td>Design &amp; R&amp;D &lt;b&gt;</td><td>New</td></tr></table>' } }] })
    cy.get('.change-old .diff-text').should('have.text', '| R&D <b> |  |')
    cy.get('.change-new .diff-text').should('have.text', '| Design & R&D <b> | New |')
    cy.get('.field-comparison b').should('not.exist')
  })

  it('uses field schemas for literal text and readable Markdown while restoring the original values', () => {
    const before = { literal: '<b>Keep &amp; literal</b>', markdown: '# Old title\n\nHello **team**.\n\n[Guide](/old)' }
    const after = { literal: '<b>Replace &amp; literal</b>', markdown: '## New title\n\nHello *team*.\n\n[Guide](/new)' }
    const block = data => ({ id: 'text', type: 'text', data })
    const onApply = cy.spy().as('apply')
    mountDialog({ schemas: { content: { text: { fields: { literal: { type: 'plaintext' }, markdown: { type: 'markdown' } } } } },
      current: { data: { content: [block(before)] } }, versions: [{ data: { content: [block(after)] } }], onApply })
    cy.get('.diff-group[aria-label="literal"] .change-old .diff-text').should('have.text', before.literal)
    cy.get('.diff-group[aria-label="literal"] .formatting-changes').should('not.exist')
    cy.get('.diff-group[aria-label="markdown"]').within(() => {
      cy.get('.structure-changes').should('contain', 'Heading 1 → Heading 2')
      cy.get('.formatting-changes').should('contain', 'Bold removed').and('contain', 'Italic added').and('contain', '/new')
      cy.get('.diff-text').should('not.contain', '**').and('not.contain', '[Guide]')
      cy.get('.change-new .format-italic').should('have.text', 'team').and('have.css', 'font-style', 'italic')
      cy.get('a').should('not.exist')
    })
    cy.get('.diff-group[aria-label="literal"] input').uncheck()
    cy.contains('button', 'Restore selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0].content[0].data).to.deep.equal({ ...before, markdown: after.markdown }))
  })

  it('styles retained formatting and marks only the exact words whose formatting changed', () => {
    mountDialog({ current: { data: { text: '<p>Say <b>Hello</b> to team and team.</p>' } }, versions: [
      { data: { text: '<p>Say <b>Welcome</b> to team and <i><u>team</u></i>.</p>' } }
    ] })
    cy.get('.change-old .format-bold').should('have.text', 'Hello').and('have.css', 'font-weight', '700')
    cy.get('.change-new .format-bold').should('have.text', 'Welcome')
    cy.get('.change-new .format-change').should('have.length', 1).and('have.text', 'team')
    cy.get('.change-new .format-italic.format-underline').should('have.css', 'text-decoration-line', 'underline')
    cy.get('.formatting-changes').should('not.contain', 'Bold added').and('not.contain', 'Bold removed')
  })
})
