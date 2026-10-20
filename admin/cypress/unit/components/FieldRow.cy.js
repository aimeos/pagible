import { h } from 'vue'
import FieldRow from '../../../js/components/FieldRow.vue'

const stubs = {
  String: {
    emits: ['update:modelValue'],
    render() { return h('input', { class: 'field-string', onInput: () => this.$emit('update:modelValue', 'changed') }) }
  },
  Hidden: { render() { return h('input', { class: 'field-hidden', type: 'hidden' }) } },
  File: { props: ['label'], render() { return h('div', { class: 'field-file' }, [this.label, this.$slots.label?.()]) } },
}

function mountRow(props = {}) {
  return cy.mount(FieldRow, {
    props: { field: { type: 'string' }, code: 'title', label: 'Title', ...props },
    global: { stubs },
  })
}

describe('FieldRow', () => {
  it('renders the label and names the input', () => {
    mountRow()
    cy.get('.label').should('contain', 'Title')
    cy.get('.field-string').should('have.attr', 'aria-label', 'Title')
  })

  it('does not render a label for hidden fields', () => {
    mountRow({ field: { type: 'hidden', hint: 'Never shown' } })
    cy.get('.label').should('not.exist')
    cy.get('.hint').should('not.exist')
  })

  it('passes the label to fields rendering it themselves', () => {
    mountRow({ field: { type: 'file' }, code: 'file', label: 'File' })
    cy.get('.label').should('not.exist')
    cy.get('.field-file').should('contain', 'File')
  })

  it('renders the hint and the slot content', () => {
    // string fields show their hint themselves and cy.mount() doesn't pass slots to the component
    const props = { field: { type: 'file', hint: 'Short title' }, code: 'file', label: 'File' }
    const Host = { render: () => h(FieldRow, props, { default: () => h('div', { class: 'note' }, 'Updated') }) }

    cy.mount(Host, { global: { stubs } })
    cy.get('.hint').should('contain', 'Short title')
    cy.get('.note').should('contain', 'Updated')
  })

  it('emits revert from the revert button of dirty fields', () => {
    const onRevert = cy.spy().as('revert')

    mountRow({ field: { type: 'number' }, dirty: true, onRevert })
    cy.get('.label .actions button').click()
    cy.get('@revert').should('have.been.calledOnce')
  })

  it('shows no revert button when readonly', () => {
    mountRow({ field: { type: 'file' }, code: 'file', label: 'File', dirty: true, readonly: true })
    cy.get('.field-file button').should('not.exist')
  })

  it('forwards value updates', () => {
    const onUpdate = cy.spy().as('update')

    mountRow({ 'onUpdate:modelValue': onUpdate })
    cy.get('.field-string').trigger('input')
    cy.get('@update').should('have.been.calledOnceWith', 'changed')
  })
})
