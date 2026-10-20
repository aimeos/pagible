import { schemaFields } from '../../../js/field'

describe('field', () => {
  describe('schemaFields', () => {
    const schemas = {
      content: { text: { fields: { body: { type: 'text' } } } },
      meta: { seo: { fields: {} } },
    }

    it('returns the fields of the schema type', () => {
      const vm = { $log: cy.stub() }

      expect(schemaFields(vm, schemas, 'content', 'text')).to.deep.equal({ body: { type: 'text' } })
      expect(vm.$log).not.to.have.been.called
    })

    it('returns an empty list without type', () => {
      const vm = { $log: cy.stub() }

      expect(schemaFields(vm, schemas, 'content', '')).to.deep.equal([])
      expect(vm.$log).not.to.have.been.called
    })

    it('reports each missing type only once', () => {
      const vm = { $log: cy.stub() }

      expect(schemaFields(vm, schemas, 'content', 'unknown-once')).to.deep.equal([])
      expect(schemaFields(vm, schemas, 'content', 'unknown-once')).to.deep.equal([])
      expect(vm.$log).to.have.been.calledOnce

      schemaFields(vm, schemas, 'meta', 'unknown-once')
      expect(vm.$log).to.have.been.calledTwice
    })

    it('reports missing types only after the schemas are loaded', () => {
      const vm = { $log: cy.stub() }

      expect(schemaFields(vm, { content: {} }, 'content', 'unknown-later')).to.deep.equal([])
      expect(vm.$log).not.to.have.been.called

      schemaFields(vm, schemas, 'content', 'unknown-later')
      expect(vm.$log).to.have.been.calledOnce
    })
  })
})
