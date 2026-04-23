import ChangesDialog from '../../../js/components/ChangesDialog.vue'

const changedWithConflicts = {
  editor: 'other@example.com',
  latest: { data: {} },
  data: {
    title: {
      previous: 'Old Title',
      current: 'Their Title',
      overwritten: 'My Title',
    },
    name: {
      previous: 'old-name',
      current: 'their-name',
    },
  },
  content: {
    abc123: {
      previous: { type: 'text', data: { text: 'old' } },
      current: { type: 'text', data: { text: 'theirs' } },
      overwritten: { type: 'text', data: { text: 'mine' } },
    },
  },
}

const changedNoConflicts = {
  editor: 'other@example.com',
  latest: { data: {} },
  data: {
    title: {
      previous: 'Old',
      current: 'New',
    },
  },
}

function mountDialog(props = {}) {
  return cy.mount(ChangesDialog, {
    props: {
      modelValue: true,
      changed: changedWithConflicts,
      targets: {},
      ...props,
    },
  })
}

describe('ChangesDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('does not render when modelValue is false', () => {
    mountDialog({ modelValue: false })
    cy.get('.v-dialog').should('not.exist')
  })

  it('shows editor name in the toolbar title', () => {
    mountDialog()
    cy.get('.v-toolbar-title').should('contain', 'other@example.com')
  })

  it('renders a close button with aria-label', () => {
    mountDialog()
    cy.get('button[aria-label="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(ChangesDialog, {
      props: {
        modelValue: true,
        changed: changedWithConflicts,
        targets: {},
        'onUpdate:modelValue': onUpdate,
      },
    })
    cy.get('button[aria-label="Close"]').click({ force: true })
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('shows only entries with overwritten key', () => {
    mountDialog()
    cy.get('.conflict-card').should('have.length', 2)
  })

  it('does not show entries without overwritten key', () => {
    mountDialog({ changed: changedNoConflicts })
    cy.get('.conflict-card').should('not.exist')
  })

  it('renders section headers as chips', () => {
    mountDialog()
    cy.get('h3.section-header .v-chip').should('have.length.at.least', 1)
  })

  it('shows conflict counter in toolbar', () => {
    mountDialog()
    cy.get('.toolbar-counter').should('contain', '0 / 2')
  })

  it('shows Use theirs and Keep mine buttons for unresolved conflicts', () => {
    mountDialog()
    cy.contains('.v-btn', 'Use theirs').should('exist')
    cy.contains('.v-btn', 'Keep mine').should('exist')
  })

  it('does not show Revert button for unresolved conflicts', () => {
    mountDialog()
    cy.contains('.v-btn', 'Revert').should('not.exist')
  })

  it('has diff region with role group', () => {
    mountDialog()
    cy.get('.conflict-diff[role="group"]').should('exist')
  })

  it('has aria-live on toolbar counter', () => {
    mountDialog()
    cy.get('.toolbar-counter[aria-live="polite"]').should('exist')
  })

  describe('conflicts computed', () => {
    it('returns empty when changed is null', () => {
      mountDialog({ changed: null }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.conflicts).to.deep.equal({})
      })
    })

    it('filters to only overwritten entries', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.conflicts.data).to.have.property('title')
        expect(vm.conflicts.data).to.not.have.property('name')
      })
    })

    it('omits sections with no conflicts', () => {
      mountDialog({ changed: changedNoConflicts }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.conflicts).to.deep.equal({})
      })
    })
  })

  describe('totalConflicts computed', () => {
    it('counts all conflicting entries', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.totalConflicts).to.equal(2)
      })
    })

    it('returns 0 when no conflicts', () => {
      mountDialog({ changed: changedNoConflicts }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.totalConflicts).to.equal(0)
      })
    })
  })

  describe('resolve()', () => {
    it('marks conflict as resolved and emits resolve event', () => {
      const onResolve = cy.spy().as('resolve')
      cy.mount(ChangesDialog, {
        props: {
          modelValue: true,
          changed: changedWithConflicts,
          targets: {},
          onResolve,
        },
      }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.resolve('data', 'title', 'My Title')
        expect(vm.resolved.has('data.title')).to.be.true
        expect(vm.changed.data.title.resolved).to.equal('My Title')
        cy.get('@resolve').should('have.been.called')
      })
    })

    it('updates target object when provided', () => {
      const target = { title: 'old' }
      mountDialog({ targets: { data: target } }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.resolve('data', 'title', 'My Title')
        expect(target.title).to.equal('My Title')
      })
    })

    it('updates target array by id match', () => {
      const target = [
        { id: 'abc123', type: 'text', data: { text: 'old' } },
      ]
      const replacement = { id: 'abc123', type: 'text', data: { text: 'mine' } }
      mountDialog({ targets: { content: target } }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.resolve('content', 'abc123', replacement)
        expect(target[0].data.text).to.equal('mine')
      })
    })
  })

  describe('unresolve()', () => {
    it('removes resolved state and updates set', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.resolve('data', 'title', 'My Title')
        expect(vm.resolved.has('data.title')).to.be.true

        vm.unresolve('data', 'title')
        expect(vm.resolved.has('data.title')).to.be.false
        expect(vm.changed.data.title).to.not.have.property('resolved')
      })
    })

    it('restores original target value on unresolve', () => {
      const target = { title: 'original' }
      mountDialog({ targets: { data: target } }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.resolve('data', 'title', 'Their Title')
        expect(target.title).to.equal('Their Title')

        vm.unresolve('data', 'title')
        expect(target.title).to.equal('original')
      })
    })
  })

  describe('format()', () => {
    it('returns empty string for null', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.format(null)).to.equal('')
      })
    })

    it('stringifies objects as JSON', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.format({ a: 1 })).to.contain('"a": 1')
      })
    })

    it('converts primitives to string', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.format(42)).to.equal('42')
      })
    })
  })

  describe('label()', () => {
    it('returns translated key for scalar fields', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        const info = { current: 'value' }
        const result = vm.label('data', 'title', info)
        expect(result).to.equal('title')
      })
    })

    it('returns type + title for content blocks', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        const info = { current: { type: 'text', data: { title: 'Hello' } } }
        const result = vm.label('content', 'abc', info)
        expect(result).to.contain('text')
        expect(result).to.contain('Hello')
      })
    })
  })

  describe('UI interactions', () => {
    it('shows Revert button and hides action buttons after resolving', () => {
      mountDialog()
      cy.contains('.v-btn', 'Keep mine').first().click({ force: true })
      cy.contains('.v-btn', 'Revert').should('exist')
    })

    it('updates counter after resolving a conflict', () => {
      mountDialog()
      cy.contains('.v-btn', 'Keep mine').first().click({ force: true })
      cy.get('.toolbar-counter').should('contain', '1 / 2')
    })

    it('adds solved class to resolved conflict card', () => {
      mountDialog()
      cy.contains('.v-btn', 'Keep mine').first().click({ force: true })
      cy.get('.conflict-card.solved').should('have.length', 1)
    })

    it('restores action buttons after clicking Revert', () => {
      mountDialog()
      cy.contains('.v-btn', 'Keep mine').first().click({ force: true })
      cy.contains('.v-btn', 'Revert').click({ force: true })
      cy.get('.conflict-card.solved').should('not.exist')
      cy.get('.toolbar-counter').should('contain', '0 / 2')
    })
  })

  describe('merges computed', () => {
    const mergeablePrev = 'Line one\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven\n'

    it('returns merged string when patches do not conflict', () => {
      const mergeableChanged = {
        editor: 'other@example.com',
        data: {
          description: {
            previous: mergeablePrev,
            current: 'Line one changed\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven\n',
            overwritten: 'Line one\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven changed\n',
            merged: 'Line one changed\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven changed\n',
          },
        },
      }
      mountDialog({ changed: mergeableChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        const merged = vm.changes['data.description'].merge
        expect(merged).to.be.a('string')
        expect(merged).to.contain('Line one changed')
        expect(merged).to.contain('Line seven changed')
      })
    })

    it('returns merged object for non-overlapping field changes', () => {
      const objectMergeChanged = {
        editor: 'other@example.com',
        content: {
          abc: {
            previous: { type: 'text', data: { text: 'test 2', title: 'Hello' } },
            current: { type: 'text', data: { text: 'test 2', title: 'World' } },
            overwritten: { type: 'text', data: { text: 'new test 3', title: 'Hello' } },
            merged: { type: 'text', data: { text: 'new test 3', title: 'World' } },
          },
        },
      }
      mountDialog({ changed: objectMergeChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        const merged = vm.changes['content.abc'].merge
        expect(merged).to.be.an('object')
        expect(merged.data.text).to.equal('new test 3')
        expect(merged.data.title).to.equal('World')
      })
    })

    it('returns merged object from backend merged field', () => {
      const wordMergeChanged = {
        editor: 'other@example.com',
        content: {
          abc: {
            previous: { type: 'text', data: { text: 'new test 3' } },
            current: { type: 'text', data: { text: 'new test 2' } },
            overwritten: { type: 'text', data: { text: 'test 3' } },
            merged: { type: 'text', data: { text: 'test 2' } },
          },
        },
      }
      mountDialog({ changed: wordMergeChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        const merged = vm.changes['content.abc'].merge
        expect(merged).to.be.an('object')
        expect(merged.data.text).to.equal('test 2')
      })
    })

    it('returns null when both changed the same word', () => {
      const bothChangedChanged = {
        editor: 'other@example.com',
        content: {
          abc: {
            previous: { type: 'text', data: { text: 'original' } },
            current: { type: 'text', data: { text: 'theirs' } },
            overwritten: { type: 'text', data: { text: 'mine' } },
          },
        },
      }
      mountDialog({ changed: bothChangedChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.changes['content.abc'].merge).to.be.null
      })
    })

    it('uses backend merged value for scalar strings', () => {
      const wordMergeChanged = {
        editor: 'other@example.com',
        data: {
          title: {
            previous: 'Hello World',
            current: 'Hi World',
            overwritten: 'Hello Earth',
            merged: 'Hi Earth',
          },
        },
      }
      mountDialog({ changed: wordMergeChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.changes['data.title'].merge).to.equal('Hi Earth')
      })
    })

    it('returns null when both changed the same scalar word', () => {
      const nonMergeableChanged = {
        editor: 'other@example.com',
        data: {
          title: {
            previous: 'hello',
            current: 'hi',
            overwritten: 'hey',
          },
        },
      }
      mountDialog({ changed: nonMergeableChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.changes['data.title'].merge).to.be.null
      })
    })

    it('returns null when previous is empty', () => {
      const noPreviousChanged = {
        editor: 'other@example.com',
        data: {
          title: {
            previous: null,
            current: 'Current',
            overwritten: 'Theirs',
          },
        },
      }
      mountDialog({ changed: noPreviousChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        expect(vm.changes['data.title'].merge).to.be.null
      })
    })
  })

  describe('merge()', () => {
    it('resolves with the merged value', () => {
      const mergeableChanged = {
        editor: 'other@example.com',
        data: {
          description: {
            previous: 'Line one\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven\n',
            current: 'Line one changed\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven\n',
            overwritten: 'Line one\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven changed\n',
            merged: 'Line one changed\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven changed\n',
          },
        },
      }
      const onResolve = cy.spy().as('resolve')
      cy.mount(ChangesDialog, {
        props: {
          modelValue: true,
          changed: mergeableChanged,
          targets: {},
          onResolve,
        },
      }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.merge('data', 'description')
        expect(vm.resolved.has('data.description')).to.be.true
        expect(vm.changed.data.description.resolved).to.contain('Line one changed')
        expect(vm.changed.data.description.resolved).to.contain('Line seven changed')
        cy.get('@resolve').should('have.been.called')
      })
    })

    it('does nothing when merge is not possible', () => {
      const nonMergeableChanged = {
        editor: 'other@example.com',
        data: {
          title: {
            previous: 'hello',
            current: 'hi',
            overwritten: 'hey',
          },
        },
      }
      mountDialog({ changed: nonMergeableChanged }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.merge('data', 'title')
        expect(vm.resolved.has('data.title')).to.be.false
      })
    })
  })

  describe('Merge both button', () => {
    it('shows Merge both button when merge is possible', () => {
      const mergeableChanged = {
        editor: 'other@example.com',
        data: {
          description: {
            previous: 'Line one\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven\n',
            current: 'Line one changed\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven\n',
            overwritten: 'Line one\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven changed\n',
            merged: 'Line one changed\nLine two\nLine three\nLine four\nLine five\nLine six\nLine seven changed\n',
          },
        },
      }
      mountDialog({ changed: mergeableChanged })
      cy.contains('.v-btn', 'Merge both').should('exist')
    })

    it('hides Merge both button when merge is not possible', () => {
      const nonMergeableChanged = {
        editor: 'other@example.com',
        data: {
          title: {
            previous: 'hello',
            current: 'hi',
            overwritten: 'hey',
          },
        },
      }
      mountDialog({ changed: nonMergeableChanged })
      cy.contains('.v-btn', 'Merge both').should('not.exist')
    })
  })

  describe('watch: changed', () => {
    it('resets resolved set when changed prop updates', () => {
      mountDialog().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ChangesDialog).vm
        vm.resolve('data', 'title', 'My Title')
        expect(vm.resolved.size).to.equal(1)

        // Simulate changed prop update
        vm.$options.watch.changed.call(vm)
        expect(vm.resolved.size).to.equal(0)
      })
    })
  })
})
