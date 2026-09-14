import { h } from 'vue'
import PluginPanel from '../../../js/views/PluginPanel.vue'
import { useDrawerStore, usePluginStore, useUserStore } from '../../../js/stores'

const Body = {
  props: ['panel'],
  render() {
    return h('div', { class: 'plugin-body' }, `${this.panel.label} body`)
  }
}

describe('PluginPanel', () => {
  function mountPanel(perms = { 'config:webhook': true }, translate = (value) => value) {
    const panel = {
      label: 'Webhooks',
      permission: 'config:webhook',
      component: Body
    }

    return cy.mount(PluginPanel, {
      props: { panel },
      global: {
        plugins: [{
          install(app) {
            app.config.globalProperties.$gettext = translate
            useUserStore().me = { permission: perms }
            usePluginStore().panels = { webhooks: panel }
          }
        }],
        stubs: {
          User: { template: '<div class="user-stub" />' }
        }
      }
    })
  }

  it('renders the host navigation and plugin body', () => {
    mountPanel()

    cy.get('.v-app-bar').contains('Webhooks').should('exist')
    cy.get('.v-navigation-drawer').contains('Webhooks').should('exist')
    cy.get('.plugin-body').should('contain', 'Webhooks body')
    cy.get('.user-stub').should('exist')
  })

  it('filters the plugin navigation entry by permission', () => {
    mountPanel({})

    cy.get('.v-navigation-drawer').contains('Webhooks').should('not.exist')
    cy.get('.plugin-body').should('exist')
  })

  it('translates the registered panel label', () => {
    mountPanel({ 'config:webhook': true }, (value) => value === 'Webhooks' ? 'Web-Haken' : value)

    cy.get('.v-app-bar').contains('Web-Haken').should('exist')
    cy.get('.v-navigation-drawer').contains('Web-Haken').should('exist')
    cy.get('.plugin-body').should('contain', 'Webhooks body')
  })

  it('toggles the navigation drawer from the app bar', () => {
    let initial

    mountPanel().then(() => {
      initial = useDrawerStore().nav
    })

    cy.get('.v-app-bar button').first().click().then(() => {
      expect(useDrawerStore().nav).to.equal(!initial)
    })
  })
})
