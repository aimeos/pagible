import TranslateDialog from '../../../js/components/TranslateDialog.vue'
import { useLanguageStore } from '../../../js/stores'

function mountDialog(props = {}) {
  const onApply = cy.spy().as('apply')

  return cy.mount(TranslateDialog, {
    props: { modelValue: true, count: 2, langs: ['de'], onApply, ...props },
    global: {
      plugins: [{
        install() {
          useLanguageStore().available = ['en', 'de', 'fr']
        }
      }]
    }
  })
}

describe('TranslateDialog', () => {
  beforeEach(() => cy.viewport(1000, 1000))

  it('checks the passed languages and emits several target languages', () => {
    mountDialog()

    cy.get('.translate-lang[data-lang="de"] input').should('be.checked')
    cy.get('.translate-lang[data-lang="fr"] input').should('not.be.checked').check()
    cy.get('.btn-translate').click()
    cy.get('@apply').should('have.been.calledOnceWith', { langs: ['de', 'fr'] })
  })

  it('disables translating without a language', () => {
    mountDialog({ langs: [] })

    cy.get('.translate-lang input:checked').should('have.length', 0)
    cy.get('.btn-translate').should('be.disabled')
  })
})
