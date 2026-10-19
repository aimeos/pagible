import { focusInvalid } from '../../../js/detail'

describe('focusInvalid()', () => {
  afterEach(() => {
    document.getElementById('focus-test')?.remove()
  })

  it('focuses the first visible invalid input', () => {
    const root = document.createElement('div')
    root.id = 'focus-test'
    root.innerHTML = '<div class="v-input"><input id="hidden" style="display:none"></div>'
      + '<div class="v-input"><input id="visible"></div>'
    document.body.appendChild(root)

    const form = { validate: () => Promise.resolve({ valid: false, errors: [{ id: 'hidden' }, { id: 'visible' }] }) }

    return focusInvalid(form).then((result) => {
      expect(result).to.equal(true)
      expect(document.activeElement.id).to.equal('visible')
    })
  })

  it('returns false without errors or form', () => {
    return focusInvalid(null).then((result) => {
      expect(result).to.equal(false)
      return focusInvalid({ validate: () => Promise.resolve({ valid: true, errors: [] }) })
    }).then((result) => {
      expect(result).to.equal(false)
    })
  })
})
