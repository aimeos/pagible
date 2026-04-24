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
})
