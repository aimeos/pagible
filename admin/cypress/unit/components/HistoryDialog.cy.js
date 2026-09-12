import HistoryDialog from '../../../js/components/HistoryDialog.vue'
import HistoryField from '../../../js/components/HistoryField.vue'
import { useSchemaStore } from '../../../js/stores'
import '../../../js/assets/base.css'

const stubs = {
  // A transition stub inserts a wrapper that breaks Vuetify's direct-child overlay layout.
  transition: false,
  'transition-group': false,
}

const current = {
  data: { title: 'Current version' },
  files: {},
}
const imageFile = (id, values = {}) => ({ id, name: id + '.jpg', path: id + '.jpg', mime: 'image/jpeg', previews: {}, ...values })
const textBlock = (id, text = id, group = 'main') => ({ id, type: 'text', group, data: { text } })

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

const mountMedia = (before = [], after = []) => cy.mount(HistoryField, {
  props: { field: { media: { before, after } } }, global: { stubs }
})

describe('HistoryDialog', () => {
  it('shows changed file previews when their reference is unchanged inside an edited list item', () => {
    const file = { id: 'photo', name: 'Photo', mime: 'image/svg+xml', path: 'before.svg' }
    const data = title => ({ cards: [{ id: 'card', title, image: { type: 'file', id: 'photo' } }] })
    mountDialog({ current: { data: data('Current'), files: { photo: file } },
      versions: [{ data: data('Saved'), files: { photo: { ...file, path: 'after.svg' } } }] })
    cy.get('.media-zoom').should('have.length', 2)
    cy.get('.media-zoom').each(button => {
      cy.wrap(button).scrollIntoView().find('img').should('be.visible').and(img => expect(img[0].naturalWidth).to.be.greaterThan(0))
    })
  })

  it('preserves unchanged nested fields and blocks when restoring without a current snapshot', () => {
    const content = [textBlock('keep', 'Keep this block')]
    const latest = { id: 'latest', data: { content, meta: { seo: { title: 'Latest title', description: 'Keep description' } } } }
    const older = { id: 'older', data: { content: [...content, { id: 'add', type: 'text', group: 'main', data: { text: 'Restore this block' } }], meta: { seo: { title: 'Older title', description: 'Keep description' } } } }
    mountDialog({ current: null, versions: [latest, older], onApply: cy.spy().as('apply') })
    cy.contains('button', 'Revert selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0]).to.deep.equal(older.data))
  })

  it('shows raw field values without a rendered preview and restores only the selected field with its original markup', () => {
    cy.viewport(390, 844)
    const before = '<p>Plans</p><table><tr><th>Name</th><th>Price</th></tr><tr><td>Basic</td><td>10</td></tr></table>'
    const after = '<p>Plans</p><table><tr><th>Name</th><th>Region</th><th>Price</th></tr><tr><td>Basic</td><td>EU</td><td>15</td></tr></table>'
    mountDialog({ current: { data: { text: before, title: 'Current' } },
      versions: [{ data: { text: after, title: 'Saved' } }], onApply: cy.spy().as('apply') })
    cy.contains('.diff-group', 'text').within(() => {
      cy.get('.raw-value .diff-columns').should('be.visible')
      cy.get('details, summary').should('not.exist')
      cy.get('.change-new pre').should('have.text', before)
      cy.get('.change-old pre').should('have.text', after)
    })
    cy.get('.diff-group input[aria-label="title"]').uncheck()
    cy.get('.history-body, .version-diffs, .history-actions').each(node => expect(node[0].scrollWidth).to.be.at.most(node[0].clientWidth + 1))
    cy.contains('button', 'Revert selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => expect(spy.firstCall.args[0]).to.deep.equal({ text: after }))
  })

  it('shows shortened table previews with context without duplicate raw values', () => {
    const saved = Array.from({ length: 12 }, (_, index) => ['Row ' + (index + 1), 'Value ' + (index + 1)])
    const current = saved.map(row => [...row])
    current[5][1] = 'Current value'
    const block = table => ({ id: 'table', type: 'table', group: 'main', data: { table } })
    mountDialog({
      schemas: { content: { table: { label: 'Table', fields: { table: { type: 'table', label: 'Table data' } } } } },
      current: { data: { content: [block(current)] } },
      versions: [{ data: { content: [block(saved)] } }]
    })

    cy.get('.diff-group[aria-label="Table data"]').within(() => {
      cy.get('.table-preview table').should('have.length', 2)
      cy.get('.change-old').should('contain', 'Row 1').and('contain', 'Value 5').and('contain', 'Value 6')
        .and('contain', 'Value 7').and('contain', '3 rows omitted').and('contain', '5 rows omitted')
      cy.get('.change-new').should('contain', 'Current value')
      cy.get('.table-preview').should('not.contain', 'Row 2').and('not.contain', 'Row 12')
      cy.get('.change-old .table-cell.highlight').should('have.text', 'Value 6')
      cy.get('.change-new .table-cell.highlight').should('have.text', 'Current value')
      cy.get('.raw-value, details, summary').should('not.exist')
    })
  })

  it('explains identical displayed dates and references and retains their exact stored differences', () => {
    const before = '2026-09-09T10:00:00Z', after = '2026-09-09T12:00:00+02:00'
    const content = refid => [{ id: 'shared', type: 'reference', refid }]
    const onApply = cy.spy().as('apply')
    mountDialog({ current: { data: { publish_at: before, title: 'Current title', content: content('current-id') } },
      versions: [{ data: { publish_at: after, title: 'Saved title', content: content('saved-id') } }], onApply })
    cy.get('.diff-group[aria-label="publish at"] .raw-value .change-new pre').should('have.text', before)
    cy.get('.diff-group[aria-label="publish at"] .raw-value .change-old pre').should('have.text', after)
    cy.get('.diff-group[aria-label="title"] input').uncheck()
    cy.contains('button', 'Revert selected changes').click()
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
    cy.get('.diff-group[aria-label="release date"] .change-new pre').should('have.text', '2026-09-09T10:00:00Z')
    cy.get('.diff-group[aria-label="release date"] .change-old pre').should('have.text', '2026-09-09T12:00:00+02:00')
    cy.get('.diff-group[aria-label="value"] .raw-value .change-new pre').should('have.text', '1')
    cy.get('.diff-group[aria-label="value"] .raw-value .change-old pre').should('have.text', '"1"')
  })
  it('shows only previews and filenames when file metadata changes', () => {
    const file = { id: 'photo', name: 'Team.jpg', path: 'team.jpg', mime: 'image/jpeg', previews: { 320: 'small.jpg', 640: 'medium.jpg' } }
    const saved = { ...file, name: 'Team portrait.png', mime: 'image/png', previews: { 320: 'small.jpg', 960: 'large.jpg' } }
    mountDialog({ current: { data: {}, files: { photo: file } }, versions: [{ data: {}, files: { photo: saved } }] })
    cy.get('.media-row .file').should('have.length', 2)
    cy.get('.media-row figcaption').then(captions => {
      expect([...captions].map(caption => caption.textContent)).to.deep.equal(['Team portrait.png', 'Team.jpg'])
    })
    cy.get('.media-row').should('not.contain', 'image/jpeg').and('not.contain', 'image/png')
      .and('not.contain', 'team.jpg').and('not.contain', 'medium.jpg').and('not.contain', 'large.jpg')
  })

  it('shows changed whitespace and blank lines', () => {
    const before = 'SKU-1234\nA B\nTabs here\nLast', after = 'SKU-1235\nA  B\nTabs\there\n\nLast'
    mountDialog({ current: { data: { text: before } }, versions: [{ data: { text: after } }] })
    cy.get('.change-new pre').should('have.text', before).find('.highlight').should('contain', '4')
    cy.get('.change-old pre').should('have.text', after).find('.highlight').should('contain', '5')
    cy.get('.change-old .whitespace').should('exist')
  })
  it('loads and renders the history dialog and closes it through v-model', () => {
    const load = cy.stub().returns(Promise.resolve([])).as('load')
    const onUpdate = cy.spy().as('update')
    cy.mount(HistoryDialog, {
      props: {
        modelValue: true,
        current,
        readonly: false,
        load,
        'onUpdate:modelValue': onUpdate,
      },
      global: { stubs },
    })
    cy.get('.v-dialog').should('contain', 'History').find('.v-timeline').should('exist')
    cy.get('@load').should('have.been.calledOnce')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('uses the shared rounded tonal dialog surface', () => {
    mountDialog()
    cy.get('.v-dialog > .v-overlay__content > .v-card')
      .should('have.css', 'border-radius', '16px')
      .and('have.css', 'overflow', 'hidden')
    cy.get('.v-dialog > .v-overlay__content > .v-card > .v-toolbar')
      .should(toolbar => {
        const styles = getComputedStyle(toolbar[0])
        const primary = styles.getPropertyValue('--v-theme-primary').split(',').map(value => value.trim()).join(', ')
        const opacity = styles.getPropertyValue('--v-activated-opacity').trim()
        expect(styles.backgroundColor).to.equal(`rgba(${primary}, ${opacity})`)
      })
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

  it('omits an initial version without an earlier change to compare', () => {
    mountDialog({
      current: { data: { title: 'Current version' }, files: {} },
      versions: [
        { published: true, editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Current version' }, files: {} },
      ],
    })

    cy.get('.version-panel').should('not.exist')
    cy.contains('[role="status"]', 'No changes').should('exist')
  })

  it('shows current changes separately from saved version metadata', () => {
    mountDialog({
      current: { data: { title: 'Edited version' }, files: {} },
      versions: [
        { published: true, editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Saved version' }, files: {} },
      ],
    })

    cy.get('.version-panel').should('have.length', 1)
    cy.get('.version-heading').should('contain', 'Current changes')
      .and('not.contain', 'editor@example.com').and('not.contain', '2026')
    cy.get('.version-panel-title').then(title => {
      expect(title[0].lastElementChild).to.have.class('v-expansion-panel-title__icon')
    })
    cy.get('.v-timeline-item .v-timeline-divider__inner-dot.bg-success').should('not.exist')
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

  it('selects content blocks independently', () => {
    const a = textBlock('a', 'Saved A'), b = textBlock('b', 'Saved B')
    const editedA = { ...a, data: { text: 'Edited A' } }, editedB = { ...b, data: { text: 'Edited B' } }
    mountDialog({
      current: { data: { content: [editedA, editedB] } },
      versions: [{ data: { content: [a, b] } }]
    })
    cy.contains('.diff-block', 'Edited B').find('.block-check input[type="checkbox"]').uncheck()
    cy.contains('1 of 2 selected').should('exist')
    cy.contains('.diff-block', 'Edited B').find('.block-check input').should('not.be.checked')
    cy.contains('.diff-block', 'Edited A').find('.block-check input').should('be.checked')
  })

  it('shows a saved change against its previous version and preserves unchecked live fields', () => {
    const onApply = cy.spy().as('apply')
    mountDialog({
      current: { data: { meta: { seo: { title: 'Unsaved title', description: 'Unsaved description' } } } },
      versions: [
        { data: { meta: { seo: { title: 'Latest title', description: 'Latest description' } } } },
        { data: { meta: { seo: { title: 'Old title', description: 'Old description' } } } }
      ], onApply
    })
    cy.get('.version-panel-title').last().click()
    cy.get('.version-panel').last().within(() => {
      cy.get('.change-old').should('contain', 'Old title')
      cy.get('.change-new').should('contain', 'Latest title')
      cy.contains('.diff-group', 'description').find('input[type="checkbox"]').uncheck()
    })
    cy.contains('button', 'Revert selected changes').click()
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
    cy.get('.version-panel-title').first().should('have.attr', 'aria-expanded', 'true')
    cy.get('.version-panel').eq(1).find('.version-diffs .diff-section').should('not.exist')
    cy.get('.version-panel-title').eq(1).click()
    cy.get('.version-panel').eq(1).find('.version-diffs').should('contain', 'Oldest').and('contain', 'Previous')
    cy.get('.version-panel.v-expansion-panel--active').should('have.length', 1)
    cy.get('.version-panel').should('have.length', 2)
    cy.get('.history-actions').should('exist')
  })

  it('shows moves and simultaneous group and text changes', () => {
    const a = textBlock('a', 'A'), b = textBlock('b', 'B')
    mountDialog({
      current: { data: { content: [b, { ...a, group: 'aside', data: { text: 'Edited A' } }] } },
      versions: [{ data: { content: [a, b] } }]
    })
    cy.contains('Moved from position 2 to 1').should('exist')
    cy.contains('.diff-group', 'group').should('contain', 'main').and('contain', 'aside')
    cy.contains('.diff-block', 'Edited A').find('.block-summary').should('not.contain', '0 fields changed')
  })

  it('hides selections and restoration controls in read-only mode', () => {
    mountDialog({ readonly: true, versions: [{ data: { title: 'Saved title' } }] })
    cy.get('.version-diffs').should('exist')
    cy.get('input[type="checkbox"]').should('not.exist')
    cy.get('.history-actions').should('not.exist')
  })

  it('disables restoration when no changes are selected', () => {
    mountDialog({ versions: [{ data: { title: 'Saved title' } }] })
    cy.get('.select-all input[aria-label="Select all"]').uncheck()
    cy.contains('0 of 1 selected').should('exist')
    cy.contains('button', 'Revert selected changes').should('be.disabled')
  })

  it('shows rich text as inert raw values when no preview is rendered', () => {
    mountDialog({
      current: { data: { text: '<p>Hello <em>world</em></p><script>window.historyInjected=true</script>' } },
      versions: [{ data: { text: '<p>Hello <strong>world</strong></p>' } }]
    })
    cy.get('.raw-value').should('exist')
    cy.get('details, summary').should('not.exist')
    cy.get('.change-old pre').scrollIntoView().should('be.visible').and('have.text', '<p>Hello <strong>world</strong></p>')
    cy.get('.change-new pre').scrollIntoView().should('be.visible').and('contain', '<script>window.historyInjected=true</script>')
    cy.get('.version-diffs script, .version-diffs em, .version-diffs strong').should('not.exist')
    cy.window().its('historyInjected').should('not.exist')
  })
  it('keeps comparison columns evenly split on small screens without horizontal overflow', () => {
    cy.viewport(390, 844)
    mountDialog({ current: { data: { text: 'Updated paragraph. '.repeat(10) } }, versions: [{ data: { text: 'Saved paragraph. '.repeat(10) } }] })
    cy.get('.diff-columns').first().children().then(sides => {
      const before = sides[0].getBoundingClientRect(), after = sides[1].getBoundingClientRect()
      expect(after.top).to.be.closeTo(before.top, 1)
      expect(before.width).to.be.closeTo(after.width, 1)
    })
    cy.get('.v-dialog > .v-overlay__content').then(content => {
      expect(content[0].scrollWidth).to.be.at.most(content[0].clientWidth)
    })
  })

  it('reports a loading error and allows retry', () => {
    const load = cy.stub().onFirstCall().rejects(new Error('Unavailable')).onSecondCall().resolves([])
    mountDialog({ load })
    cy.get('[role="alert"]').should('contain', 'Error fetching versions')
    cy.contains('button.v-btn--variant-outlined', 'Retry').click()
    cy.contains('No changes').should('exist')
  })
  it('uses schema labels and rich text excerpts to identify blocks', () => {
    mountDialog({
      schemas: { content: { trio: { label: 'Text Trio', fields: { leading: { type: 'string', label: 'Leading text' } } } } },
      current: { data: { content: [{ id: 'a', type: 'trio', group: 'footer', data: { text: '<p>Useful <strong>context</strong></p>', leading: 'New lead' } }] } },
      versions: [{ data: { content: [{ id: 'a', type: 'trio', group: 'footer', data: { text: '<p>Useful <strong>context</strong></p>', leading: 'Old lead' } }] } }]
    })
    cy.contains('.diff-block .block-title', 'Text Trio: Useful context').should('exist')
    cy.contains('.block-location', 'Group: footer · Position 1').should('exist')
    cy.contains('.diff-group', 'Leading text').within(() => {
      cy.get('.change-new').should('contain', 'New lead')
      cy.get('.change-old').should('contain', 'Old lead')
    })
  })

  it('selects a single field independently of a block move', () => {
    const a = { id: 'a', type: 'text', group: 'main', data: { title: 'Old title', text: 'Old text' } }
    const b = { id: 'b', type: 'text', group: 'main', data: { text: 'B' } }
    const edited = { ...a, group: 'footer', data: { title: 'New title', text: 'New text' } }
    mountDialog({ current: { data: { content: [b, edited] } }, versions: [{ data: { content: [a, b] } }] })
    cy.get('.select-all input[aria-label="Select all"]').uncheck()
    cy.contains('.diff-block', 'New title').find('.diff-group[aria-label="title"] input').check()
    cy.contains('1 of 4 selected').should('exist')
    cy.contains('.diff-block', 'New title').find('.block-check [aria-checked="mixed"]').should('exist')
  })

  it('places media under the changed field without duplicate raw values', () => {
    const data = id => ({ type: 'hero', data: { image: { type: 'file', id } } })
    mountDialog({
      schemas: { content: { hero: { fields: { image: { type: 'image', label: 'Hero image' } } } } },
      current: { data: data('new'), files: { new: imageFile('new') } },
      versions: [{ data: data('old'), files: { old: imageFile('old') } }]
    })
    cy.contains('.diff-group', 'Hero image').within(() => {
      cy.get('.file.added').should('contain', 'new.jpg')
      cy.get('.file.removed').should('contain', 'old.jpg')
      cy.get('.raw-value, details, summary').should('not.exist')
    })
    cy.get('.version-diffs > .diff-section > .media-list').should('not.exist')
  })

  it('keeps the selected count and restore action visible when scrolling a long version', () => {
    cy.viewport(1000, 700)
    const content = Array.from({ length: 12 }, (_, i) => textBlock(String(i), 'Text ' + i))
    mountDialog({ current: { data: { content: content.map(block => ({ ...block, data: { text: 'Changed ' + block.id } })) } }, versions: [{ data: { content } }] })
    cy.get('.history-actions').should('be.visible').then(footer => {
      const top = footer[0].getBoundingClientRect().top
      cy.get('.history-body').scrollTo('bottom')
      cy.get('.history-actions').should('be.visible').then(current => expect(current[0].getBoundingClientRect().top).to.be.closeTo(top, 1))
    })
    cy.contains('.history-actions', '12 of 12 selected').should('be.visible')
    cy.contains('button', 'Revert selected changes').should('be.visible')
  })

  it('shows previous values on the left and new values on the right', () => {
    mountDialog({
      current: { data: { title: 'Unsaved', content: [textBlock('new')] } },
      versions: [{ data: { title: 'Saved', content: [textBlock('old')] } }]
    })
    cy.contains('.diff-group', 'title').within(() => {
      cy.get('.change-old').should('contain', 'Saved')
      cy.get('.change-new').should('contain', 'Unsaved')
    })
    cy.contains('.diff-block', 'new').should('contain', 'Block added').find('.v-expansion-panel-title').then(heading => {
      expect(heading.find('.v-chip').index()).to.be.lessThan(heading.find('.block-title').index())
    })
    cy.contains('.diff-block', 'old').should('contain', 'Block removed').find('.v-expansion-panel-title').then(heading => {
      expect(heading.find('.v-chip').index()).to.be.lessThan(heading.find('.block-title').index())
    })
  })
  it('preserves field and movement selections while collapsing blocks', () => {
    const a = textBlock('a', 'A')
    const b = { id: 'b', type: 'text', group: 'main', data: { title: 'Old B', text: 'Old body' } }
    const edited = { ...b, data: { title: 'New B', text: 'New body' } }
    mountDialog({ current: { data: { content: [edited, a] } }, versions: [{ data: { content: [a, b] } }] })
    cy.get('.diff-group[aria-label="Block position"] .diff-columns').children().then(sides => {
      const previous = sides[0].getBoundingClientRect(), current = sides[1].getBoundingClientRect()
      expect(previous.width).to.be.closeTo(current.width, 1)
    })
    cy.get('.diff-group[aria-label="text"] input').uncheck()
    cy.get('.diff-group[aria-label="Block position"] input').uncheck()
    cy.get('.diff-block .v-expansion-panel-title').click().should('have.attr', 'aria-expanded', 'false')
    cy.get('.block-details').should('not.exist')
    cy.get('.diff-block .block-title .block-summary').should('contain', '2 fields changed').and('contain', '1 of 3 selected')
    cy.get('.diff-block .v-expansion-panel-title').then(title => {
      expect(title[0].lastElementChild).to.have.class('v-expansion-panel-title__icon')
      expect(title.find('.block-title .block-summary')[0].compareDocumentPosition(title[0].lastElementChild) & Node.DOCUMENT_POSITION_FOLLOWING).to.not.equal(0)
    })
    cy.get('.diff-block .v-expansion-panel-title').click()
    cy.get('.diff-block .v-expansion-panel-title').should('have.attr', 'aria-expanded', 'true')
    cy.get('.diff-group[aria-label="text"] input').should('not.be.checked')
    cy.get('.diff-group[aria-label="Block position"] input').should('not.be.checked')
    cy.get('.diff-group[aria-label="title"] input').should('be.checked')
  })

  it('keeps selections for initially collapsed blocks', () => {
    const content = Array.from({ length: 7 }, (_, i) => textBlock(String(i), 'Saved ' + i))
    mountDialog({ current: { data: { content: content.map(block => ({ ...block, data: { text: 'Edited ' + block.id } })) } }, versions: [{ data: { content } }] })
    cy.get('.block-details').should('have.length', 1)
    cy.get('.diff-block').eq(1).find('.block-check input').uncheck()
    cy.contains('.history-actions', '6 of 7 selected').should('exist')
  })

  it('shows current and saved changes in the groups where they happened', () => {
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
    cy.contains('button', 'Changes in this save').should('not.exist')
    cy.contains('button', 'Restore changes').should('not.exist')
    cy.get('.change-new').should('contain', 'Unsaved title')
    cy.get('.change-old').should('contain', 'Newest title')
    cy.get('.version-panel-title').eq(1).click()
    cy.get('.change-new').should('contain', 'Newest title')
    cy.get('.change-old').should('contain', 'Middle title')
    cy.get('.version-panel-title').eq(2).click()
    cy.get('.change-new').should('contain', 'Middle title')
    cy.get('.change-old').should('contain', 'Oldest title')
    cy.get('.version-panel-title').first().click()
    cy.get('.diff-group[aria-label="name"] input').should('not.be.checked')
    cy.contains('button', 'Revert selected changes').click()
    cy.get('@apply').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0]).to.deep.equal({ title: 'Newest title' })
      expect(spy.firstCall.args[1].id).to.equal('newest')
    })
  })

  it('labels unchecked scalar changes without adding a message to atomic block panels', () => {
    mountDialog({ current: { data: { title: 'Keep title', content: [] } }, versions: [
      { data: { title: 'Saved title', content: [{ id: 'a', type: 'text', data: { text: 'Saved block' } }] } }
    ] })
    cy.get('input[aria-label="Select all"]').uncheck()
    cy.get('.diff-group[aria-label="title"]').should('have.class', 'is-unselected').and('contain', 'Keep current value')
    cy.get('.diff-group[aria-label="title"] .change-new .highlight').should('have.css', 'text-decoration-line', 'none')
    cy.get('.diff-block').should('have.class', 'is-unselected').and('not.contain', 'Keep current value')
    cy.get('.change-old').should('contain', 'Previous value')
    cy.get('.change-new').should('contain', 'New value')
    cy.get('.diff-group[aria-label="title"] input').check()
    cy.get('.diff-group[aria-label="title"]').should('not.have.class', 'is-unselected')
      .and('contain', 'Previous value').and('contain', 'New value')
  })

  it('shows shared element names with IDs in raw values', () => {
    mountDialog({
      current: { data: { content: [{ type: 'reference', refid: 'new-uuid' }] }, elements: [{ id: 'new-uuid', name: 'New campaign' }] },
      versions: [{ data: { content: [{ type: 'reference', refid: 'old-uuid' }] }, elements: [{ id: 'old-uuid', name: 'Original campaign' }] }]
    })
    cy.contains('.block-title', 'Shared element: New campaign').should('exist')
    cy.contains('.block-title', 'Shared element: Original campaign').should('exist')
    cy.get('.block-title').should('not.contain', 'uuid')
    cy.get('.diff-block .diff-group').should('have.length', 4)
    cy.contains('.diff-block', 'New campaign').find('.diff-group[aria-label="Shared element"] .raw-value').within(() => {
      cy.get('.diff-columns').scrollIntoView().should('be.visible').and('contain', 'new-uuid')
    })
  })

  it('keeps absent, null and empty raw values distinct', () => {
    mountDialog({ current: { data: { title: 'Removed', name: null, text: '<p><br></p>' } },
      versions: [{ data: { name: 'Empty', text: 'Visible text', added: 'New value' } }] })
    cy.get('.diff-group[aria-label="title"] .change-old pre').should('have.text', '---')
    cy.get('.diff-group[aria-label="title"] .change-new pre').should('have.text', '"Removed"')
    cy.get('.diff-group[aria-label="name"] .change-new pre').should('have.text', 'null')
    cy.get('.diff-group[aria-label="name"] .change-old pre').should('have.text', '"Empty"')
    cy.get('.diff-group[aria-label="text"] .change-new pre').should('have.text', '<p><br></p>')
    cy.get('.diff-group[aria-label="added"] .change-new pre').should('have.text', '---')
  })

  it('previews whole blocks once with one selection per block', () => {
    const old = textBlock('old', '<p>Saved block</p>')
    const current = textBlock('new', '<p>Keep this block</p>', 'aside')
    mountDialog({ current: { data: { content: [current] } }, versions: [{ data: { content: [old] } }] })
    cy.contains('.diff-block', 'Keep this block').within(() => {
      cy.get('.change-new').should('contain', 'Keep this block')
      cy.get('.block-check input').uncheck()
      cy.get('div.block-raw').should('exist').find('summary').should('not.exist')
      cy.contains('.block-raw pre', '"id": "new"').scrollIntoView().should('be.visible')
        .and('contain', '"id": "new"').and('contain', '"group": "aside"')
    })
    cy.contains('.diff-block', 'Saved block').find('.change-old').should('contain', 'Saved block')
    cy.get('.diff-block .diff-columns').should('have.length.at.least', 2).each(columns => {
      const [previous, current] = columns[0].children
      expect(previous.getBoundingClientRect().width).to.be.closeTo(current.getBoundingClientRect().width, 1)
    })
    cy.get('.diff-block input[type="checkbox"]').should('have.length', 2)
  })

  it('shows both media sides for a whole removed block', () => {
    const file = { id: 'photo', name: 'Campaign photo', mime: 'image/jpeg', path: 'photo.jpg', previews: {} }
    mountDialog({ current: { data: { content: [] } }, versions: [
      { data: { content: [{ id: 'a', type: 'image', data: { image: { type: 'file', id: 'photo' } } }] }, files: { photo: file } }
    ] })
    cy.get('.diff-block .media-pair').children().should('have.length', 2).then(sides => {
      const previous = sides[0].getBoundingClientRect(), current = sides[1].getBoundingClientRect()
      expect(current.top).to.be.closeTo(previous.top, 1)
      expect(previous.width).to.be.closeTo(current.width, 1)
    })
    cy.get('.diff-block .file.removed').should('contain', 'Campaign photo')
    cy.get('.diff-block .empty-media').should('contain', '---')
  })

  it('uses the outlined whole-version button on mobile', () => {
    cy.viewport(390, 844)
    const onUse = cy.spy().as('use')
    mountDialog({ current: { data: { title: 'Current' } }, onUse,
      versions: [{ id: 'latest', data: { title: 'Latest' } }, { id: 'old', data: { title: 'Old' } }] })
    cy.get('.history-actions').should('be.visible').then(footer => expect(footer[0].getBoundingClientRect().height).to.be.lessThan(115))
    cy.get('.restore-selected').should('be.visible')
    cy.contains('.history-actions', '1 of 1 selected').should('be.visible')
    cy.contains('button.restore-whole', 'Restore previous version').should('be.visible').click()
    cy.get('@use').should('have.been.calledOnce').then(spy => {
      expect(spy.firstCall.args[0].id).to.equal('latest')
      expect(spy.firstCall.args[1]).to.equal(true)
    })
    cy.get('.version-panel-title').eq(1).click()
    cy.contains('button.restore-whole', 'Restore previous version').should('be.visible').click()
    cy.get('@use').should('have.been.calledTwice').then(spy => {
      expect(spy.secondCall.args[0].id).to.equal('old')
      expect(spy.secondCall.args[1]).to.equal(false)
    })
    cy.get('.history-actions').then(footer => expect(footer[0].scrollWidth).to.be.at.most(footer[0].clientWidth))
  })

  for (const width of [390, 1280]) {
    it(`aligns the remaining checkboxes on the right at ${width}px`, () => {
      cy.viewport(width, 844)
      const block = text => ({ id: 'body', type: 'text', group: 'main', data: { text } })
      mountDialog({
        current: { data: { title: 'Current', content: [block('Current body')] } },
        versions: [
          { editor: 'editor@example.com', data: { title: 'Current', content: [block('Current body')] } },
          { data: { title: 'Saved', content: [block('Saved body')] } }
        ]
      })
      cy.get('.select-all .v-label').should('not.exist')
      cy.get('.diff-heading:not(.v-expansion-panel-title) > .v-checkbox').each(checkbox => {
        const row = checkbox[0].parentElement, padding = parseFloat(getComputedStyle(row).paddingRight)
        expect(checkbox[0].getBoundingClientRect().right).to.be.closeTo(row.getBoundingClientRect().right - padding, 2)
      })
      cy.get('.diff-block .v-expansion-panel-title').then(title => {
        const blockTitle = title[0].querySelector('.block-title')
        const checkbox = title[0].querySelector(':scope > .v-checkbox')
        const icon = title[0].lastElementChild
        expect(checkbox.compareDocumentPosition(icon) & Node.DOCUMENT_POSITION_FOLLOWING).to.not.equal(0)
        expect(icon).to.have.class('v-expansion-panel-title__icon')
        expect(blockTitle.getBoundingClientRect().right).to.be.at.most(checkbox.getBoundingClientRect().left + 1)
        expect(title[0].scrollWidth).to.be.at.most(title[0].clientWidth)
      })
      cy.get('.block-title').should('contain', '1 field changed').and('contain', '1 of 1 selected')
      cy.get('.block-check input').uncheck()
      cy.get('.diff-block .v-expansion-panel-title').should('have.attr', 'aria-expanded', 'true')
      cy.get('.block-title').should('contain', '0 of 1 selected')
      cy.get('.version-title').first().within(() => {
        cy.get('.version-date, .version-summary').then(entries => {
          const date = entries[0].getBoundingClientRect(), summary = entries[1].getBoundingClientRect()
          expect(date.right).to.be.at.most(summary.left + 1)
          expect(date.top + date.height / 2).to.be.closeTo(summary.top + summary.height / 2, 1)
        })
      })
      cy.get('.version-title, .version-editor').then(entries => {
        expect(entries[1].getBoundingClientRect().left).to.be.closeTo(entries[0].getBoundingClientRect().left, 1)
      })
      cy.get('.version-panel-title').first().then(title => {
        const iconNode = title[0].lastElementChild
        const checkboxNode = title[0].querySelector('.select-all .v-selection-control__wrapper')
        const icon = iconNode.getBoundingClientRect(), checkbox = checkboxNode.getBoundingClientRect()
        expect(iconNode).to.have.class('v-expansion-panel-title__icon')
        expect(checkboxNode.compareDocumentPosition(iconNode) & Node.DOCUMENT_POSITION_FOLLOWING).to.not.equal(0)
        expect(checkbox.top + checkbox.height / 2).to.be.closeTo(icon.top + icon.height / 2, 1)
        expect(checkbox.right).to.be.closeTo(icon.left, 1)
      })
      cy.contains('.version-panel-title', 'Select all').should('not.exist')
      cy.get('input[aria-label="Select all"]').should('be.enabled')
      cy.get('.version-panel-title').first().click().should('have.attr', 'aria-expanded', 'false')
      cy.get('.select-all').should(checkbox => {
        const rect = checkbox[0].getBoundingClientRect(), style = getComputedStyle(checkbox[0])
        expect(rect.width * rect.height).to.be.greaterThan(0)
        expect(style.display).to.not.equal('none')
        expect(style.visibility).to.not.equal('hidden')
      })
      cy.get('input[aria-label="Select all"]').should('be.disabled')
      cy.get('.version-panel-title').first().click().should('have.attr', 'aria-expanded', 'true')
      cy.get('input[aria-label="Select all"]').should('be.enabled')
      cy.get('input[aria-label="Selected only"]').should('not.exist')
      cy.get('.history-body').then(body => expect(body[0].scrollWidth).to.be.at.most(body[0].clientWidth))
    })
  }
})

describe('History media', () => {
  for (const type of ['audio', 'video']) it(`keeps native ${type} controls and preview error recovery`, () => {
    const file = { id: 'media', name: 'Recording', mime: type + '/mpeg', path: 'recording.' + (type === 'audio' ? 'mp3' : 'mp4') }
    mountMedia([file])
    cy.get('.file ' + type).should('have.prop', 'controls', true).and('have.attr', 'preload', 'none').and('have.attr', 'crossorigin', 'anonymous')
      .and('have.attr', 'src').and('include', file.path)
    cy.get('.file ' + type).scrollIntoView().trigger('error', { bubbles: false })
    cy.get('.file .media-error').should('contain', 'Preview unavailable')
    cy.get('.file ' + type).trigger('loadeddata')
    cy.get('.file .media-error').should('not.exist')
  })

  it('opens only the chosen image on desktop and mobile and returns focus when closed', () => {
    const image = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="400" height="200" fill="blue"/></svg>'
    cy.intercept('GET', '**/wide.svg', { headers: { 'content-type': 'image/svg+xml' }, body: image })
    cy.intercept('GET', '**/small.svg', { headers: { 'content-type': 'image/svg+xml' }, body: image })
    const current = imageFile('photo', { name: 'Wide image', path: 'wide.svg', mime: 'image/svg+xml' })
    const saved = { ...current, name: 'Small image', path: 'small.svg' }
    mountMedia([saved], [current])
    cy.get('[aria-label="Enlarge Wide image"]').click()
    cy.get('[aria-label="Image preview"]').should('be.visible').and('contain', 'New value').and('contain', 'Wide image')
      .find('img').should('have.length', 1).and('have.attr', 'alt', 'Wide image')
    cy.get('[aria-label="Close preview"]').click()
    cy.focused().should('have.attr', 'aria-label', 'Enlarge Wide image')
    cy.viewport(390, 844)
    cy.get('[aria-label="Enlarge Small image"]').scrollIntoView().click()
    cy.get('[aria-label="Image preview"] img').should('have.length', 1).and('have.attr', 'alt', 'Small image')
    cy.get('[aria-label="Image preview"] .v-card').then(card => expect(card[0].scrollWidth).to.be.at.most(card[0].clientWidth + 1))
    cy.get('[aria-label="Close preview"]').click()
  })

  it('shows paired images with their names and equal old and new columns', () => {
    const saved = imageFile('photo', { name: 'Old campaign', path: 'old.jpg' })
    const current = { ...saved, name: 'New campaign', path: 'new.jpg' }
    mountMedia([saved], [current])
    cy.get('.file.removed figcaption').should('have.text', 'Old campaign')
    cy.get('.file.added figcaption').should('have.text', 'New campaign')
    cy.get('.media-label').first().should('have.text', 'Previous value')
    cy.get('.media-label').last().should('have.text', 'New value')
    cy.get('.media-row').should('not.contain', 'image/jpeg').and('not.contain', 'old.jpg').and('not.contain', 'new.jpg')
    cy.get('.media-pair').children().then(sides => {
      const previous = sides[0].getBoundingClientRect(), current = sides[1].getBoundingClientRect()
      expect(current.top).to.be.closeTo(previous.top, 1)
      expect(previous.width).to.be.closeTo(current.width, 1)
    })
  })

  it('keeps unchanged media neutral and pairs images across insertions and moves', () => {
    const files = Object.fromEntries(['a', 'b', 'c', 'd'].map(id => [id, imageFile(id)]))
    mountMedia(['c', 'a', 'd', 'b'].map(id => files[id]), ['a', 'b', 'c'].map(id => files[id]))
    cy.get('.media-row').should('have.length', 4)
    cy.get('.media-row').eq(0).should('contain', 'a.jpg').find('.file').should('have.length', 1)
    cy.get('.media-row').eq(1).should('contain', 'd.jpg').find('.empty-media').should('contain', '---')
    cy.get('.media-row').eq(2).should('contain', 'b.jpg').find('.removed, .added').should('not.exist')
    cy.get('.media-row').eq(3).should('contain', 'c.jpg').find('.file').should('have.length', 2)
  })

  it('shows loading and failure states while preserving the media name and enlargement', () => {
    const pending = []
    cy.intercept('GET', '**/slow-preview.svg', req => new Promise(resolve => {
      pending.push(() => { req.reply({ statusCode: 404, body: '' }); resolve() })
    })).as('preview')
    const file = imageFile('photo', { name: 'Campaign image', mime: 'image/svg+xml', path: 'slow-preview.svg' })
    mountMedia([file])
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
})
