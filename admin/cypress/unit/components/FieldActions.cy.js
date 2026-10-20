import FieldActions from '../../../js/components/FieldActions.vue'
import { useUserStore } from '../../../js/stores'

function mountActions(provide = {}, props = {}, perms = { 'text:write': true, 'text:translate': true, 'audio:transcribe': true }) {
  return cy
    .mount(FieldActions, {
      props: { code: 'title', field: { type: 'string', label: 'Title' }, value: 'Hello', context: { title: 'Hello' }, ...props },
      global: {
        provide: {
          write: () => Promise.resolve('Generated'),
          translate: () => Promise.resolve(['Hallo']),
          ...provide
        },
        plugins: [{
          install() {
            useUserStore().me = { permission: perms }
          }
        }]
      }
    })
    .then(({ wrapper }) => wrapper.findComponent(FieldActions))
}

describe('FieldActions', () => {
  it('renders the AI buttons depending on the permissions', () => {
    mountActions()
    cy.get('.btn-translate button').should('exist')
    cy.get('button.btn-generate').should('exist')
    cy.get('button.btn-dictate').should('have.attr', 'aria-pressed', 'false')
  })

  it('hides the AI buttons without permissions', () => {
    mountActions({}, {}, {})
    cy.get('.btn-translate button').should('not.exist')
    cy.get('button.btn-generate').should('not.exist')
    cy.get('button.btn-dictate').should('not.exist')
  })

  it('appends dictated text to the field value', () => {
    mountActions().then((wrapper) => {
      cy.stub(wrapper.vm.dictation, 'toggle').callsFake((done) => done('world'))

      wrapper.vm.record()
      expect(wrapper.emitted('update')).to.deep.equal([['Hello world']])
    })
  })

  it('emits the generated text', () => {
    const write = cy.stub().resolves('Generated')

    mountActions({ write }).then((comp) => {
      cy.get('button.btn-generate').click()
      cy.wrap(write).should('have.been.calledWith', 'Hello')
      cy.wrap(comp).should(() => {
        expect(comp.emitted('update')).to.deep.equal([['Generated']])
      })
    })
  })

  it('uses the prompt if the value is empty', () => {
    const write = cy.stub().resolves('Generated')

    mountActions({ write }, { value: '', prompt: 'Fallback' })
    cy.get('button.btn-generate').click()
    cy.wrap(write).should('have.been.calledWith', 'Fallback')
  })

  it('ignores write results which are no strings', () => {
    mountActions({ write: () => Promise.resolve(false) }).then((comp) => {
      return comp.vm.generate().then(() => {
        expect(comp.emitted('update')).to.equal(undefined)
        expect(comp.vm.composing).to.equal(false)
      })
    })
  })

  it('emits the translated text', () => {
    mountActions().then((comp) => {
      return comp.vm.translateTo('de').then(() => {
        expect(comp.emitted('update')).to.deep.equal([['Hallo']])
      })
    })
  })

  it('ignores translate results which are no arrays', () => {
    mountActions({ translate: () => Promise.resolve(undefined) }).then((comp) => {
      return comp.vm.translateTo('de').then(() => {
        expect(comp.emitted('update')).to.equal(undefined)
        expect(comp.vm.translating).to.equal(false)
      })
    })
  })
})
