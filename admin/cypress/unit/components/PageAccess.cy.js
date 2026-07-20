import PageAccess from '../../../js/components/PageAccess.vue'

function mountAccess(access, props = {}) {
  const query = cy.stub().resolves({ data: { access: ['alpha', 'member'] } }).as('query')
  const mutate = cy.stub().resolves({ data: { setPageAccess: 1 } }).as('mutate')
  const input = {
    ids: ['page-1'],
    descendants: 0,
    ...props,
  }

  if (access !== undefined) input.access = access

  return cy.mount(PageAccess, {
    props: input,
    global: {
      mocks: {
        $apollo: { query, mutate },
      },
    },
  })
}

describe('PageAccess', () => {
  it('maps the three access states to one form', () => {
    mountAccess(null).then(({ wrapper }) => {
      expect(wrapper.findComponent(PageAccess).vm.mode).to.equal('public')
    })

    mountAccess([]).then(({ wrapper }) => {
      expect(wrapper.findComponent(PageAccess).vm.mode).to.equal('authenticated')
    })

    mountAccess(['member']).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageAccess).vm
      expect(vm.mode).to.equal('restricted')
      expect(vm.values).to.deep.equal(['member'])
    })
  })

  it('requires bulk users to select an access state', () => {
    mountAccess(undefined)
    cy.get('.btn-apply-access').should('be.disabled')
    cy.contains('.v-radio', 'Public').find('input').check({ force: true })
    cy.get('.btn-apply-access').should('be.enabled')
  })

  it('sends an explicit public value', () => {
    mountAccess(null).then(async ({ wrapper }) => {
      const component = wrapper.findComponent(PageAccess)
      await component.vm.apply()
      expect(component.emitted('applied')).to.deep.equal([[null, false]])
    })

    cy.get('@mutate').should('have.been.calledOnce')
    cy.get('@mutate').its('firstCall.args.0.variables').should('deep.equal', {
      id: ['page-1'],
      access: null,
      descendants: false,
    })
  })

  it('supports descendants only for one selected root', () => {
    mountAccess([], { descendants: 3 }).then(({ wrapper }) => {
      const component = wrapper.findComponent(PageAccess)
      return component.vm.apply(true).then(() => {
        expect(component.emitted('applied')).to.deep.equal([[[], true]])
      })
    })

    cy.get('@mutate').should((mutate) => {
      expect(mutate.firstCall.args[0].variables.descendants).to.equal(true)
    })
  })

  it('disables recursive changes above the page bulk limit', () => {
    mountAccess([], { descendants: 1000 })

    cy.get('.btn-apply-access-recursive').should('be.disabled')
    cy.contains('Page bulk changes are limited to 1,000 pages').should('exist')
    cy.get('.btn-apply-access').should('be.enabled').click()
    cy.get('@mutate').its('firstCall.args.0.variables.descendants').should('equal', false)
  })

  it('allows recursive changes for exactly one thousand affected pages', () => {
    mountAccess([], { descendants: 999 })

    cy.get('.btn-apply-access-recursive').should('be.enabled').and('contain', '(1000)')
    cy.contains('Page bulk changes are limited to 1,000 pages').should('not.exist')
  })

  it('disables selected page changes above the bulk limit', () => {
    mountAccess([], { ids: Array.from({ length: 1001 }, (_, idx) => `page-${idx}`) })

    cy.get('.btn-apply-access').should('be.disabled')
    cy.contains('Page bulk changes are limited to 1,000 pages').should('exist')
  })

  it('hides descendants for multiple selected roots', () => {
    mountAccess([], { ids: ['page-1', 'page-2'], descendants: 3 })
    cy.get('.btn-apply-access-recursive').should('not.exist')
  })
})
