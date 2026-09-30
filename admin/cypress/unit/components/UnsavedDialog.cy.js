import UnsavedDialog from '../../../js/components/UnsavedDialog.vue'
import { useDirtyStore } from '../../../js/stores'

describe('UnsavedDialog', () => {
  const open = () => {
    cy.mount(UnsavedDialog).then(() => {
      useDirtyStore().show = true
    })
  }

  it('shows icon and text side by side if wide enough', () => {
    cy.viewport(1000, 700)
    open()

    cy.get('.unsaved-body .v-icon').then(($icon) => {
      const icon = $icon[0].getBoundingClientRect()
      const text = document.querySelector('#unsaved-description').getBoundingClientRect()

      expect(text.left).to.be.greaterThan(icon.right)
      expect(text.top).to.be.lessThan(icon.bottom)
    })
  })

  it('stacks icon and text if too narrow', () => {
    cy.viewport(1000, 700)
    open()

    cy.get('.unsaved-body').invoke('css', 'width', '200px')
    cy.get('.unsaved-body .v-icon').then(($icon) => {
      const icon = $icon[0].getBoundingClientRect()
      const text = document.querySelector('#unsaved-description').getBoundingClientRect()

      expect(text.top).to.be.at.least(icon.bottom)
    })
  })
})
