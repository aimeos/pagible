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

  it('hides the source languages of the pages', () => {
    mountDialog({ langs: ['en', 'de'], sources: ['en'] })

    cy.get('.translate-lang[data-lang="en"]').should('not.exist')
    cy.get('.translate-lang[data-lang="fr"]').should('exist')
    cy.get('.btn-translate').click()
    cy.get('@apply').should('have.been.calledOnceWith', { langs: ['de'] })
  })

  it('enables all languages while the needed ones are unknown', () => {
    mountDialog({ langs: ['de'], pending: null })

    cy.get('.pending').should('exist')
    cy.get('.translate-lang input:disabled').should('have.length', 0)
    cy.get('.translate-lang[data-lang="de"] input').should('be.checked')
  })

  it('enables only the languages the pages need translations in', () => {
    mountDialog({ langs: ['de'], pending: ['fr'] })

    cy.get('.pending').should('not.exist')
    cy.get('.translate-lang[data-lang="de"] input').should('be.disabled').and('not.be.checked')
    cy.get('.translate-lang[data-lang="fr"] input').should('not.be.disabled').check()
    cy.get('.btn-translate').click()
    cy.get('@apply').should('have.been.calledOnceWith', { langs: ['fr'] })
  })

  it('reports if nothing needs to be translated', () => {
    mountDialog({ pending: [] })

    cy.get('.translate-lang input:disabled').should('have.length', 3)
    cy.get('.nothing').should('exist')
    cy.get('.btn-translate').should('be.disabled')
  })

  it('disables translating without a language', () => {
    mountDialog({ langs: [] })

    cy.get('.translate-lang input:checked').should('have.length', 0)
    cy.get('.btn-translate').should('be.disabled')
  })
})
