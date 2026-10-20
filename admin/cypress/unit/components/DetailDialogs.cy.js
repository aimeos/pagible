import { h } from 'vue'
import DetailDialogs from '../../../js/components/DetailDialogs.vue'

const HistoryDialog = {
  props: { modelValue: Boolean, readonly: Boolean, current: Object, load: Function },
  emits: ['update:modelValue', 'apply', 'use'],
  render() {
    return h('div', { class: 'history-dialog-stub' }, [
      h('button', { class: 'btn-apply', onClick: () => this.$emit('apply', { name: 'new' }, { id: 'v1' }) }),
      h('button', { class: 'btn-use', onClick: () => this.$emit('use', { id: 'v1' }, true) }),
      h('button', { class: 'btn-close', onClick: () => this.$emit('update:modelValue', false) }),
    ])
  },
}

const ChangesDialog = {
  props: { modelValue: Boolean, changed: Object },
  emits: ['update:modelValue', 'resolve', 'unresolve'],
  render() {
    return h('div', { class: 'changes-dialog-stub', 'data-open': String(this.modelValue) }, [
      h('button', { class: 'btn-resolve', onClick: () => this.$emit('resolve', { section: 'data', key: 'name', value: 'x' }) }),
      h('button', { class: 'btn-unresolve', onClick: () => this.$emit('unresolve', { section: 'data', key: 'name' }) }),
    ])
  },
}

function mountDialogs(props = {}) {
  return cy.mount(DetailDialogs, {
    props: { load: () => Promise.resolve([]), ...props },
    global: { stubs: { HistoryDialog, ChangesDialog } },
  })
}

describe('DetailDialogs', () => {
  it('renders the history dialog only when it is opened', () => {
    mountDialogs()
    cy.get('.history-dialog-stub').should('not.exist')
    cy.get('.changes-dialog-stub').should('have.attr', 'data-open', 'false')
  })

  it('passes the props to the dialogs', () => {
    const current = { data: { name: 'Test' } }
    const load = () => Promise.resolve([])

    mountDialogs({ history: true, changes: true, readonly: true, current, load }).then(({ wrapper }) => {
      const history = wrapper.findComponent(HistoryDialog)
      expect(history.props()).to.include({ modelValue: true, readonly: true, current, load })
    })
    cy.get('.changes-dialog-stub').should('have.attr', 'data-open', 'true')
  })

  it('forwards the events with all arguments', () => {
    const onApply = cy.stub()
    const onUse = cy.stub()
    const onResolve = cy.stub()
    const onUnresolve = cy.stub()
    const onHistory = cy.stub()

    mountDialogs({ history: true, onApply, onUse, onResolve, onUnresolve, 'onUpdate:history': onHistory })
    cy.get('.btn-apply').click()
    cy.get('.btn-use').click()
    cy.get('.btn-resolve').click()
    cy.get('.btn-unresolve').click()
    cy.get('.btn-close').click().then(() => {
      expect(onApply).to.have.been.calledWith({ name: 'new' }, { id: 'v1' })
      expect(onUse).to.have.been.calledWith({ id: 'v1' }, true)
      expect(onResolve).to.have.been.calledWith({ section: 'data', key: 'name', value: 'x' })
      expect(onUnresolve).to.have.been.calledWith({ section: 'data', key: 'name' })
      expect(onHistory).to.have.been.calledWith(false)
    })
  })

  it('renders the additional dialogs of the slot', () => {
    cy.mount({
      render: () => h(DetailDialogs, { load: () => Promise.resolve([]) }, () => h('div', { class: 'chat-stub' })),
    }, { global: { stubs: { HistoryDialog, ChangesDialog } } })
    cy.get('.chat-stub').should('exist')
  })
})
