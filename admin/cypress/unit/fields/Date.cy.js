import DateField from '../../../js/fields/Date.vue'

describe('Date', () => {
  it('renders a date input', () => {
    cy.mount(DateField, { props: { config: {} } })
    cy.get('.v-date-input').should('exist')
  })

  it('shows a placeholder when configured', () => {
    cy.mount(DateField, { props: { config: { placeholder: 'YYYY-MM-DD' } } })
    cy.get('input').should('have.attr', 'placeholder', 'YYYY-MM-DD')
  })

  it('emits error:false when required and a value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(DateField, {
      props: { modelValue: '2025-01-15', config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(DateField, {
      props: { modelValue: null, config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when not required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(DateField, {
      props: { modelValue: null, config: { required: false }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(DateField, { props: { config: {}, readonly: true } })
    cy.get('input').should('have.attr', 'readonly')
  })

  it('hides the clear button when required', () => {
    cy.mount(DateField, { props: { modelValue: '2025-06-01', config: { required: true } } })
    cy.get('.v-field__clearable').should('not.exist')
  })

  it('emits picked dates as local YYYY-MM-DD strings', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(DateField, { props: { config: {}, 'onUpdate:modelValue': onUpdate } }).then(({ wrapper }) => {
      wrapper.findComponent(DateField).vm.update(new Date(2026, 0, 5, 23, 30))
    })
    cy.get('@update').should('have.been.calledWith', '2026-01-05')
  })

  it('emits an empty string when the date is cleared so the default is not shown again', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(DateField, { props: { modelValue: '2026-01-05', config: {}, 'onUpdate:modelValue': onUpdate } }).then(({ wrapper }) => {
      wrapper.findComponent(DateField).vm.update(null)
    })
    cy.get('@update').should('have.been.calledWith', '')
  })

  it('emits an array of strings for multiple dates', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(DateField, { props: { config: { multiple: true }, 'onUpdate:modelValue': onUpdate } }).then(({ wrapper }) => {
      wrapper.findComponent(DateField).vm.update([new Date(2026, 1, 1), new Date(2026, 11, 31)])
    })
    cy.get('@update').should('have.been.calledWith', ['2026-02-01', '2026-12-31'])
  })

  it('passes stored strings as local dates to the date input', () => {
    cy.mount(DateField, { props: { modelValue: '2026-03-09', config: {} } }).then(({ wrapper }) => {
      const date = wrapper.findComponent(DateField).vm.dates
      expect(date).to.be.instanceOf(Date)
      expect([date.getFullYear(), date.getMonth(), date.getDate()]).to.deep.equal([2026, 2, 9])
    })
  })

  it('uses the local date of legacy timestamps', () => {
    const stored = new Date(2026, 3, 20, 0, 30).toISOString()
    cy.mount(DateField, { props: { modelValue: stored, config: {} } }).then(({ wrapper }) => {
      const date = wrapper.findComponent(DateField).vm.dates
      expect([date.getFullYear(), date.getMonth(), date.getDate()]).to.deep.equal([2026, 3, 20])
    })
  })

  it('converts string defaults and multiple values', () => {
    cy.mount(DateField, { props: { config: { default: '2026-05-01' } } }).then(({ wrapper }) => {
      expect(wrapper.findComponent(DateField).vm.dates.getDate()).to.equal(1)
    })
    cy.mount(DateField, { props: { modelValue: ['2026-05-01', '2026-05-02'], config: { multiple: true } } }).then(({ wrapper }) => {
      expect(wrapper.findComponent(DateField).vm.dates.map((d) => d.getDate())).to.deep.equal([1, 2])
    })
  })
})
