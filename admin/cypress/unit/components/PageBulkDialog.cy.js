import PageBulkDialog from '../../../js/components/PageBulkDialog.vue'
import { useSchemaStore } from '../../../js/stores'

describe('PageBulkDialog', () => {
  it('opens the status dropdown and shows options on a real click', () => {
    // the dialog is taller than the default component-test viewport; enlarge it
    // so the status field (the realClick target) is within the viewport
    cy.viewport(1000, 800)
    cy.mount(PageBulkDialog, {
      props: { modelValue: true, count: 2 },
      global: {
        // created() calls schemas.load() which would fire a real GraphQL
        // query; stub it so the component test stays offline.
        plugins: [{
          install() {
            useSchemaStore().load = () => Promise.resolve()
          }
        }],
      },
    })
    cy.get('.v-dialog').should('exist')
    cy.get('.prop').first().find('.v-field').realClick()
    cy.get('.v-overlay-container [role="option"]').should('have.length.gte', 1)
  })

  it('limits selected and recursive page changes to one thousand pages', () => {
    const mount = (count, descendants) => cy.mount(PageBulkDialog, {
      props: { modelValue: true, count, descendants },
      global: {
        plugins: [{
          install() {
            useSchemaStore().load = () => Promise.resolve()
          }
        }],
      },
    })

    mount(1, 1000)
    cy.get('.prop').first().find('input[type="checkbox"]').check({ force: true })
    cy.get('.btn-apply').should('be.enabled')
    cy.get('.btn-apply-recursive').should('be.disabled')
    cy.contains('Page bulk changes are limited to 1,000 pages').should('exist')

    mount(1001, 0)
    cy.get('.prop').first().find('input[type="checkbox"]').check({ force: true })
    cy.get('.btn-apply').should('be.disabled')
  })
})
