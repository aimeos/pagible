import { h } from 'vue'
import { VCheckboxBtn, VThemeProvider } from 'vuetify/components'
import ConfirmDialog from '../../../js/components/ConfirmDialog.vue'
import { useConfirmStore } from '../../../js/stores'
import '../../../js/assets/base.css'

describe('base.css', () => {
  it('shows the checkbox of draft items in the warning color with a status dot', () => {
    cy.mount({
      render: () => [h(VCheckboxBtn, { class: 'draft' }), h(VCheckboxBtn, { class: 'published' })]
    })

    cy.get('.v-selection-control.draft .v-icon').then(($icon) => {
      const style = getComputedStyle($icon[0])
      const warning = getComputedStyle(document.querySelector('.v-application')).getPropertyValue('--v-theme-warning')

      expect(style.color).to.equal(`rgb(${warning.split(',').map((v) => v.trim()).join(', ')})`)
      expect(style.opacity).to.equal('1')
      expect(style.filter).to.equal('none')
    })

    cy.get('.v-selection-control.draft .v-selection-control__input').then(($input) => {
      const dot = getComputedStyle($input[0], '::after')

      expect(dot.width).to.equal('8px')
      expect(dot.borderRadius).to.equal('50%')
    })

    cy.get('.v-selection-control.published .v-selection-control__input').then(($input) => {
      expect(getComputedStyle($input[0], '::after').content).to.equal('none')
    })
  })

  it('shows light header text in warning dialogs in dark mode', () => {
    cy.viewport(1000, 700)
    cy.mount({ render: () => h(VThemeProvider, { theme: 'dark', withBackground: true }, () => h(ConfirmDialog)) })
      .then(() => {
        useConfirmStore().purge([{ name: 'Home', info: '/home' }]) // resolves only after answering
      })

    cy.get('.v-dialog:visible .v-toolbar.bg-warning').then(($bar) => {
      const [r, g, b] = getComputedStyle($bar[0]).color.match(/\d+/g).map(Number)
      const title = getComputedStyle($bar.find('.v-toolbar-title')[0]).color

      expect(Math.min(r, g, b), 'light text').to.be.greaterThan(200)
      expect(title).to.equal(getComputedStyle($bar[0]).color)
    })
  })
})
