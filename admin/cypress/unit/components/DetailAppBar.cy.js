import { h, ref } from 'vue'
import { VLayout, VTab, VTabs } from 'vuetify/components'
import DetailAppBar from '../../../js/components/DetailAppBar.vue'
import { useDrawerStore, useUserStore, useViewStack } from '../../../js/stores'
import { keydown } from '../../../js/shortcuts'

describe('DetailAppBar', () => {
  function mount(props = {}, children = () => []) {
    const onSave = cy.stub().as('save')
    const attrs = { type: 'page', label: 'Page', name: 'Home', stacked: true, onSave, ...props }

    cy.mount(
      { render: () => h(VLayout, () => [h(DetailAppBar, attrs), ...children()]) },
      {
        global: {
          plugins: [{
            install() {
              useUserStore().me = { permission: { 'page:save': true, 'page:publish': true } }
            }
          }]
        }
      }
    )
  }

  const press = (key, opts = {}) => cy.window().then(() => {
    keydown(new KeyboardEvent('keydown', { key, cancelable: true, ...opts }))
  })

  it('saves on Ctrl+S when there are unsaved changes', () => {
    mount({ dirty: true })
    cy.get('.menu-save').should('have.attr', 'aria-keyshortcuts')
    press('s', { ctrlKey: true })
    cy.get('@save').should('have.been.calledOnce')
  })

  it('does nothing on Ctrl+S without changes', () => {
    mount({ dirty: false })
    cy.get('.menu-save').should('be.disabled')
    press('s', { ctrlKey: true })
    cy.get('@save').should('not.have.been.called')
  })

  it('does nothing on Ctrl+S while saving', () => {
    mount({ dirty: true, saving: true })
    cy.get('.menu-save').should('exist')
    press('s', { ctrlKey: true })
    cy.get('@save').should('not.have.been.called')
  })

  it('opens the publish menu with the publish button focused on Ctrl+Shift+S', () => {
    mount({ dirty: true })
    cy.get('.menu-publish').should('exist')
    press('S', { ctrlKey: true, shiftKey: true })
    cy.get('.menu-publish-now').should('be.visible').and('have.focus')
    cy.get('@save').should('not.have.been.called')
  })

  it('does not open the publish menu when nothing can be published', () => {
    mount({ published: true, dirty: false })
    cy.get('.menu-publish').should('be.disabled')
    press('S', { ctrlKey: true, shiftKey: true })
    cy.get('.menu-publish-now').should('not.exist')
  })

  it('goes back on Esc', () => {
    mount()
    cy.get('.btn-back').should('exist')
    cy.then(() => cy.stub(useViewStack(), 'closeView').as('close'))
    press('Escape')
    cy.get('@close').should('have.been.calledOnce')
  })

  it('switches to the previous and next tab with [ and ]', () => {
    const tab = ref('a')
    const tabs = () => [
      h(
        VTabs,
        { class: 'detail-tabs', modelValue: tab.value, 'onUpdate:modelValue': (v) => (tab.value = v) },
        () => ['a', 'b', 'c'].map((v) => h(VTab, { value: v }, () => v))
      )
    ]

    mount({}, tabs)
    cy.get('.detail-tabs .v-tab--selected').should('contain', 'a')

    press(']')
    cy.get('.detail-tabs .v-tab--selected').should('contain', 'b')
    press(']')
    press(']') // stays at the last tab
    cy.get('.detail-tabs .v-tab--selected').should('contain', 'c')
    press('[')
    cy.get('.detail-tabs .v-tab--selected').should('contain', 'b').then(() => {
      expect(tab.value).to.equal('b')
    })
  })

  it('toggles the side panel with \\', () => {
    mount()
    cy.get('.btn-sidemenu').should('exist').then(() => {
      const drawer = useDrawerStore()
      const before = drawer.aside

      keydown(new KeyboardEvent('keydown', { key: '\\', cancelable: true }))
      expect(drawer.aside).to.equal(!before)
    })
  })
})
