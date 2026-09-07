import PageList from '../../../js/views/PageList.vue'
import ElementList from '../../../js/views/ElementList.vue'
import FileList from '../../../js/views/FileList.vue'
import { h, KeepAlive } from 'vue'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import '../../../js/assets/base.css'

for (const [type, View] of [['page', PageList], ['element', ElementList], ['file', FileList]]) {
  describe(`${View.name} scroll`, () => {
    it('restores the list scroll position after returning from a detail view', () => {
      const list = {
        ...View,
        components: {
          ...View.components,
          ...Object.fromEntries(['Navigation', 'AsideList', 'User'].map(name => [name, { render: () => h('div') }])),
          [`${View.name}Items`]: {
            emits: ['select'],
            render() {
              return h('div', Array.from({ length: 100 }, (_, id) => h('button', {
                style: 'display: block; height: 48px',
                onClick: () => this.$emit('select', { id })
              }, `Item ${id}`)))
            }
          }
        }
      }
      const router = createRouter({
        history: createMemoryHistory(),
        routes: [
          { path: `/${type}s`, component: list },
          { path: `/${type}s/:id`, name: `${type}:detail`, component: {
            render: () => h('button', { class: 'back', onClick: () => router.back() }, 'Back')
          } }
        ]
      })
      const host = {
        render: () => h(RouterView, null, {
          default: ({ Component }) => h(KeepAlive, { include: [View.name] }, () => Component)
        })
      }

      cy.then(() => router.push(`/${type}s`))
      cy.mount(host, {
        global: {
          plugins: [router],
          stubs: { RouterView: false },
          provide: { locales: () => [] }
        }
      })
      cy.get(`.${type}-list .scroll`).scrollTo(0, 1200)
      cy.contains('button', 'Item 26').click({ scrollBehavior: false })
      cy.get(`.${type}-list`).should('not.exist')
      cy.get('.back').click()
      cy.get(`.${type}-list .scroll`).should($sheet => {
        expect($sheet[0].scrollTop).to.equal(1200)
      })
    })

  })
}
