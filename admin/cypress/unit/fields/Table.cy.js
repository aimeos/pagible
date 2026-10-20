import TableField from '../../../js/fields/Table.vue'

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

  it('renders columns found after a shorter first row', () => {
    cy.mount(TableField, {
      props: { modelValue: [['heading'], ['a', 'b']], config: {} },
    })
    cy.get('tbody .v-textarea').should('have.length', 4)
    cy.get('tbody tr')
      .eq(1)
      .find('.v-textarea')
      .eq(1)
      .find('textarea:not(.v-textarea__sizer)')
      .should('have.value', 'b')
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
    cy.get('tbody .btn-actions button').should('have.length', 2)
  })

  it('hides row action menus in readonly mode', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a']], config: {}, readonly: true },
    })
    cy.get('tbody .btn-actions button').should('not.exist')
  })

  it('renders column action menus when not readonly', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {} },
    })
    cy.get('thead .btn-actions button').should('have.length', 2)
  })

  it('hides column action menus in readonly mode', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {}, readonly: true },
    })
    cy.get('thead .btn-actions button').should('not.exist')
  })

  it('validates immediately on load', () => {
    const onError = cy.spy().as('error')
    cy.mount(TableField, { props: { modelValue: [['a']], config: { min: 2 }, onError } })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('hides the drag handles and makes the cells readonly in readonly mode', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {}, readonly: true },
    })
    cy.get('.row-handle').should('not.exist')
    cy.get('.col-handle').should('not.exist')
    cy.get('tbody textarea:not(.v-textarea__sizer)').each(($el) => {
      cy.wrap($el).should('have.attr', 'readonly')
    })
  })

  it('removes the selected column and keeps the IDs of the others', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(TableField, {
      props: { modelValue: [['a', 'b', 'c'], ['d', 'e', 'f']], config: {}, 'onUpdate:modelValue': onUpdate },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(TableField).vm
      const [first, , third] = vm.cols

      vm.rmCol(1)

      expect(vm.cols).to.deep.equal([first, third])
      expect(vm.columns[0]).to.equal(null)
      expect(vm.columns[vm.columns.length - 1]).to.equal(null)
    })
    cy.get('@update').should('have.been.calledWith', [['a', 'c'], ['d', 'f']])
  })

  it('inserts columns with new IDs without changing the existing ones', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {} },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(TableField).vm
      const [first, second] = vm.cols

      vm.addCol(1)

      expect(vm.cols).to.have.length(3)
      expect(vm.cols[0]).to.equal(first)
      expect(vm.cols[2]).to.equal(second)
      expect(new Set(vm.cols).size).to.equal(3)
      expect(vm.table).to.deep.equal([['a', '', 'b']])
    })
  })

  it('keeps the row keys when rows are moved', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a'], ['b']], config: {} },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(TableField).vm
      const keys = vm.table.map((row) => vm.rowKey(row))

      vm.table.reverse()

      expect(vm.table.map((row) => vm.rowKey(row))).to.deep.equal([...keys].reverse())
      expect(keys[0]).not.to.equal(keys[1])
    })
  })

  it('keeps the placeholders at both ends when a column is dropped outside of them', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(TableField, {
      props: { modelValue: [['a', 'b', 'c']], config: {}, 'onUpdate:modelValue': onUpdate },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(TableField).vm
      const [first, second, third] = vm.cols

      // the sortable list has already moved the last column before the first placeholder
      vm.columns.splice(0, 0, vm.columns.splice(3, 1)[0])
      vm.move({ oldIndex: 3, newIndex: 0 })

      expect(vm.columns).to.deep.equal([null, third, first, second, null])
      expect(vm.table).to.deep.equal([['c', 'a', 'b']])

      // and the first column after the last placeholder
      vm.columns.push(vm.columns.splice(1, 1)[0])
      vm.move({ oldIndex: 1, newIndex: 4 })

      expect(vm.columns).to.deep.equal([null, first, second, third, null])
      expect(vm.table).to.deep.equal([['a', 'b', 'c']])
    })
  })

  it('prevents dropping columns onto the placeholder columns', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b']], config: {} },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(TableField).vm
      const cells = wrapper.element.querySelectorAll('.col-header > td')

      expect(vm.movable({ related: cells[0] })).to.equal(false)
      expect(vm.movable({ related: cells[1] })).to.equal(true)
      expect(vm.movable({ related: cells[cells.length - 1] })).to.equal(false)
    })
  })

  it('labels the cells by row and column', () => {
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b'], ['c', 'd']], config: {} },
    })
    cy.get('tbody textarea:not(.v-textarea__sizer)').eq(3).should('have.attr', 'aria-label', 'Row 2, column 2')
  })

  it('moves rows with Alt and the arrow keys', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(TableField, {
      props: { modelValue: [['a'], ['b']], config: {}, 'onUpdate:modelValue': onUpdate },
    })

    cy.get('.row-handle').first().focus().trigger('keydown', { key: 'ArrowDown', altKey: true })
    cy.get('@update').should('have.been.calledOnceWith', [['b'], ['a']])
    cy.get('.row-handle').last().should('have.focus')

    cy.get('.row-handle').last().trigger('keydown', { key: 'ArrowDown', altKey: true })
    cy.get('.row-handle').last().trigger('keydown', { key: 'ArrowUp' })
    cy.get('@update').should('have.been.calledOnce')
  })

  it('moves columns with Alt and the arrow keys', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(TableField, {
      props: { modelValue: [['a', 'b'], ['c', 'd']], config: {}, 'onUpdate:modelValue': onUpdate },
    })

    cy.get('.col-handle').last().focus().trigger('keydown', { key: 'ArrowLeft', altKey: true })
    cy.get('@update').should('have.been.calledOnceWith', [['b', 'a'], ['d', 'c']])
    cy.get('.col-handle').first().should('have.focus')
    cy.get('tbody textarea').first().should('have.value', 'b')

    cy.get('.col-handle').first().trigger('keydown', { key: 'ArrowLeft', altKey: true })
    cy.get('@update').should('have.been.calledOnce')
  })
})
