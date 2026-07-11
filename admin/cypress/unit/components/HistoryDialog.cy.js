import HistoryDialog from '../../../js/components/HistoryDialog.vue'

const stubs = {
}

const current = {
  data: { title: 'Current version' },
  files: {},
}

function mountDialog(props = {}) {
  return cy.mount(HistoryDialog, {
    props: {
      modelValue: true,
      current,
      readonly: false,
      load: () => Promise.resolve([]),
      ...props,
    },
    global: { stubs },
  })
}

describe('HistoryDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
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
      load: () => Promise.resolve([]),
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
      load: () => Promise.resolve([
        { data: { mime: 'image/jpeg', path: 'files/old.jpg' }, files: { 'files/old.jpg': oldImage }, created_at: '2026-01-01T00:00:00Z' },
      ]),
    })

    cy.get('.media-list .file.added .v-img').should('exist')
    cy.get('.media-list .file.removed .v-img').should('exist')
  })

  it('shows a green published dot for the latest published version without changes', () => {
    mountDialog({
      current: { data: { title: 'Current version' }, files: {} },
      load: () => Promise.resolve([
        { published: true, editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Current version' }, files: {} },
      ]),
    })

    cy.get('.v-timeline-item .v-timeline-divider__inner-dot.bg-success').should('exist')
    cy.contains('.v-timeline-item', 'editor@example.com').should('exist')
  })

  it('shows the published dot alongside the Current card when there are unsaved changes', () => {
    mountDialog({
      current: { data: { title: 'Edited version' }, files: {} },
      load: () => Promise.resolve([
        { published: true, editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Saved version' }, files: {} },
      ]),
    })

    cy.contains('.v-timeline-item', 'Current').should('exist')
    cy.get('.v-timeline-item .v-timeline-divider__inner-dot.bg-success').should('exist')
  })

  it('marks a scheduled latest version with the publish class', () => {
    mountDialog({
      current: { data: { title: 'Current version' }, files: {} },
      load: () => Promise.resolve([
        { published: false, publish_at: '2099-01-01T00:00:00Z', editor: 'editor@example.com', created_at: '2026-01-01T00:00:00Z', data: { title: 'Current version' }, files: {} },
      ]),
    })

    cy.get('.v-timeline-item.publish').should('exist')
  })

  it('does not show a previews diff when the file changed', () => {
    mountDialog({
      current: {
        data: { name: 'new.jpg', path: 'files/new.jpg', previews: { 200: 'files/new-200.webp' } },
        files: {},
      },
      load: () => Promise.resolve([
        { data: { name: 'old.jpg', path: 'files/old.jpg', previews: { 200: 'files/old-200.webp' } }, files: {}, created_at: '2026-01-01T00:00:00Z' },
      ]),
    })

    cy.contains('.version-diffs', 'new.jpg').should('exist')
    cy.get('.version-diffs').should('not.contain', 'new-200.webp')
    cy.get('.version-diffs').should('not.contain', 'old-200.webp')
  })
})
