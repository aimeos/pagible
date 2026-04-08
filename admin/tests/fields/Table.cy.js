import TableField from '../../js/fields/Table.vue'

describe('Table', () => {
  it('renders a table', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b'], ['c', 'd']], config: {} },
    })
    cy.get('table').should('exist')
  })

  it('renders textareas for each cell', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b'], ['c', 'd']], config: {} },
    })
    cy.get('tbody .v-textarea').should('have.length', 4)
  })

  it('displays cell values in textareas', () => {
    cy.mount(TableField, {
      props: { modelValue: [['hello', 'world']], config: {} },
    })
    cy.get('tbody textarea').first().should('have.value', 'hello')
    cy.get('tbody textarea').last().should('have.value', 'world')
  })

  it('renders rows matching the modelValue length', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a'], ['b'], ['c']], config: {} },
    })
    cy.get('tbody tr').should('have.length', 3)
  })

  it('renders column headers matching column count', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b', 'c']], config: {} },
    })
    // column header row has: empty td + col tds + empty td
    // col-handle buttons count matches real columns
    cy.get('.col-handle').should('have.length', 3)
  })

  it('emits update:modelValue with default when modelValue is empty', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(TableField, {
      props: { modelValue: [], config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('@update').should('have.been.calledWith', [['']])
  })

  it('emits update:modelValue with config.default when modelValue is empty', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(TableField, {
      props: {
        modelValue: [],
        config: { default: [['x', 'y']] },
        'onUpdate:modelValue': onUpdate,
      },
    })
    cy.get('@update').should('have.been.calledWith', [['x', 'y']])
  })

  it('renders row drag handles', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a']], config: {} },
    })
    cy.get('.row-handle').should('have.length', 1)
  })

  it('renders column drag handles', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {} },
    })
    cy.get('.col-handle').should('have.length', 2)
  })

  it('emits update:modelValue when typing in a cell', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(TableField, {
      props: { modelValue: [['a']], config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('tbody textarea').first().type('x')
    cy.get('@update').should('have.been.called')
  })

  it('renders row action menus when not readonly', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a'], ['b']], config: {} },
    })
    // Each row has an action button (mdi-dots-vertical)
    cy.get('tbody button[title="Actions"]').should('have.length', 2)
  })

  it('hides row action menus in readonly mode', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a']], config: {}, readonly: true },
    })
    cy.get('tbody button[title="Actions"]').should('not.exist')
  })

  it('renders column action menus when not readonly', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {} },
    })
    cy.get('thead button[title="Actions"]').should('have.length', 2)
  })

  it('hides column action menus in readonly mode', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {}, readonly: true },
    })
    cy.get('thead button[title="Actions"]').should('not.exist')
  })
})
