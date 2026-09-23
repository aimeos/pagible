import { h } from 'vue'
import PluginPanel from '../../../js/views/PluginPanel.vue'
import { useDrawerStore, usePluginStore, useUserStore } from '../../../js/stores'

const Body = {
  props: ['panel'],
  inject: ['messages'],
  render() {
    return h('div', { class: 'plugin-body', 'data-messages': this.messages ? 'yes' : 'no' }, `${this.panel.label} body`)
  }
}

describe('PluginPanel', () => {
  function mountPanel(perms = { 'config:webhook': true }, translate = (context, value) => value, component = Body, settings = {}) {
    const panel = {
      key: 'webhooks',
      label: 'Webhooks',
      i18n: 'webhooks',
      icon: '<svg width="1em" height="1em" viewBox="0 0 24 24"><path d="M1 1h22v22H1z" /></svg>',
      permission: 'config:webhook',
      component
    }

    return cy.mount(PluginPanel, {
      props: { panel },
      global: {
        provide: {
          messages: { add() {} }
        },
        plugins: [{
          install(app) {
            app.config.globalProperties.$gettext = (value) => translate('', value)
            app.config.globalProperties.$pgettext = translate
            useUserStore().me = { permission: perms, settings }
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
    cy.get('.v-navigation-drawer .icon svg').should('exist')
    cy.get('.plugin-body').should('contain', 'Webhooks body')
    cy.get('.plugin-body').should('have.attr', 'data-messages', 'yes')
    cy.get('.user-stub').should('exist')
  })

  it('filters the plugin navigation entry by permission', () => {
    mountPanel({})

    cy.get('.v-navigation-drawer').contains('Webhooks').should('not.exist')
    cy.get('.plugin-body').should('exist')
  })

  it('translates the registered panel label', () => {
    mountPanel(
      { 'config:webhook': true },
      (context, value) => context === 'webhooks' && value === 'Webhooks' ? 'Web-Haken' : value
    )

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

  it('shows no side menu toggle for panels without filter sidebar', () => {
    mountPanel()

    cy.get('.plugin-body').should('exist')
    cy.get('.btn-sidemenu').should('not.exist')
    cy.get('aside.v-navigation-drawer').should('not.exist')
  })

  it('renders the plugin filter sidebar and stores the filter per user', () => {
    const Filtered = {
      inject: ['pluginAside'],
      data() {
        const content = () => [{ key: 'status', title: 'Status', items: [{ title: 'Active', value: { status: true } }] }]
        return { filter: this.pluginAside(content, { status: null }) }
      },
      render() {
        return h('div', { class: 'plugin-body' }, `status:${this.filter.status}`)
      }
    }
    const settings = { 'plugin:webhooks': { filter: { status: false } } }
    let initial

    // the sidebar is shown next to the content on wide screens only
    cy.viewport(1280, 800)

    mountPanel(undefined, undefined, Filtered, settings).then(() => {
      initial = useDrawerStore().aside
    })

    // restores the stored filter
    cy.get('.plugin-body').should('have.text', 'status:false')

    cy.get('aside.v-navigation-drawer').contains('.v-btn', 'Active').click()
    cy.get('.plugin-body').should('have.text', 'status:true').then(() => {
      expect(useUserStore().me.settings['plugin:webhooks'].filter).to.deep.equal({ status: true })
    })

    cy.get('aside.v-navigation-drawer .reset').click()
    cy.get('.plugin-body').should('have.text', 'status:null')

    cy.get('.btn-sidemenu').click().then(() => {
      expect(useDrawerStore().aside).to.equal(!initial)
    })
  })
})
