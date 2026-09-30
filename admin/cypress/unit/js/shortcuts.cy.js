import { computed } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useUserStore } from '../../../js/stores'
import { available, command, commands, execute, has, hint, keydown, register, routes, run, setNavigate, shortcuts, trigger } from '../../../js/shortcuts'

describe('shortcuts', () => {
  const key = (key, opts = {}) => {
    const e = new KeyboardEvent('keydown', { key, cancelable: true, ...opts })
    keydown(e)
    return e
  }

  const overlay = (cls, fn) => {
    const el = document.createElement('div')
    el.className = cls
    document.body.appendChild(el)
    fn()
    el.remove()
  }

  const all = Object.fromEntries(
    [...Object.values(commands).flatMap((cmd) => cmd.permission || []), ...Object.keys(routes)].map((perm) => [perm, true])
  )

  beforeEach(() => {
    setActivePinia(createPinia())
    useUserStore().me = { permission: all }
    shortcuts.palette = false
    shortcuts.sheet = false
  })

  it('ignores commands the user has no permission for', () => {
    const save = cy.stub()
    const off = register({ save, back: cy.stub() })
    useUserStore().me = { permission: { 'page:view': true } }

    expect(available('save')).to.equal(false)
    expect(available('back')).to.equal(true)
    key('s', { ctrlKey: true })
    expect(save).not.to.have.been.called
    expect(command(new KeyboardEvent('keydown', { key: 'Delete' }), 'list')).to.equal(undefined)

    useUserStore().me = { permission: { 'file:save': true } }
    expect(available('save')).to.equal(true)

    off()
  })

  it('navigates only to the routes the user has access to', () => {
    const navigate = cy.stub()
    setNavigate(navigate)
    useUserStore().me = { permission: { 'file:view': true } }

    key('g')
    key('p')
    key('g')
    key('m')

    expect(navigate.args.map((args) => args[0])).to.deep.equal(['file:view'])
    setNavigate(null)
  })

  it('calls only the actions of the most recently registered view', () => {
    const first = cy.stub()
    const second = cy.stub()
    const offFirst = register({ save: first })
    const offSecond = register({ save: second })

    expect(key('s', { ctrlKey: true }).defaultPrevented).to.equal(true)
    expect(second).to.have.been.calledOnce
    expect(first).not.to.have.been.called

    offSecond()
    key('S', { metaKey: true })
    expect(first).to.have.been.calledOnce

    offFirst()
  })

  it('does not fall through to lower views for missing actions', () => {
    const search = cy.stub()
    const offList = register({ search })
    const offDetail = register({ save: cy.stub() })

    expect(trigger('search')).to.equal(false)
    expect(search).not.to.have.been.called

    offDetail()
    offList()
  })

  it('triggers publish with Ctrl/Cmd+Shift+S', () => {
    const actions = { save: cy.stub(), publish: cy.stub() }
    const off = register(actions)

    expect(key('S', { ctrlKey: true, shiftKey: true }).defaultPrevented).to.equal(true)
    expect(actions.publish).to.have.been.calledOnce
    expect(actions.save).not.to.have.been.called
    off()
  })

  it('ignores auto-repeat and Alt variants of Ctrl+S', () => {
    const fn = cy.stub()
    const off = register({ save: fn })

    expect(key('s', { ctrlKey: true, repeat: true }).defaultPrevented).to.equal(true)
    key('s', { ctrlKey: true, altKey: true })
    key('s')

    expect(fn).not.to.have.been.called
    off()
  })

  it('does not save while a modal dialog is open', () => {
    const fn = cy.stub()
    const off = register({ save: fn })

    overlay('v-overlay v-dialog v-overlay--active', () => trigger('save'))
    expect(fn).not.to.have.been.called

    trigger('save')
    expect(fn).to.have.been.calledOnce
    off()
  })

  it('maps /, n and Esc to search, create and back', () => {
    const actions = { search: cy.stub(), create: cy.stub(), back: cy.stub() }
    const off = register(actions)

    expect(key('/').defaultPrevented).to.equal(true)
    key('n')
    key('Escape')
    key('N', { shiftKey: true })
    key('n', { ctrlKey: true })

    expect(actions.search).to.have.been.calledOnce
    expect(actions.create).to.have.been.calledOnce
    expect(actions.back).to.have.been.calledOnce
    off()
  })

  it('ignores single keys while a menu is open but not for tooltips or snackbars', () => {
    const back = cy.stub()
    const off = register({ back })

    overlay('v-overlay v-menu v-overlay--active', () => key('Escape'))
    expect(back).not.to.have.been.called

    overlay('v-overlay v-tooltip v-overlay--active', () => key('Escape'))
    overlay('v-overlay v-snackbar v-overlay--active', () => key('Escape'))
    expect(back).to.have.been.calledTwice
    off()
  })

  it('ignores single keys while typing', () => {
    const search = cy.stub()
    const off = register({ search })
    const input = document.createElement('input')

    document.body.appendChild(input)
    input.addEventListener('keydown', keydown)
    input.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }))
    input.dispatchEvent(new KeyboardEvent('keydown', { key: '?', bubbles: true }))
    input.remove()

    expect(search).not.to.have.been.called
    expect(shortcuts.sheet).to.equal(false)
    off()
  })

  it('toggles the shortcut sheet with ?', () => {
    key('?')
    expect(shortcuts.sheet).to.equal(true)
    key('?')
    expect(shortcuts.sheet).to.equal(false)
  })

  it('navigates with g followed by p, m, e or u', () => {
    const navigate = cy.stub()
    setNavigate(navigate)

    key('g')
    expect(key('p').defaultPrevented).to.equal(true)
    key('g')
    key('m')
    key('g')
    key('e')
    key('g')
    key('u')

    expect(navigate.args.map((args) => args[0])).to.deep.equal([
      'page:view',
      'file:view',
      'element:view',
      'access:view'
    ])
    setNavigate(null)
  })

  it('swallows the key after g and ignores late or unknown keys', () => {
    const navigate = cy.stub()
    const create = cy.stub()
    const off = register({ create })
    setNavigate(navigate)

    key('g')
    key('n') // consumed as second key of the sequence
    expect(create).not.to.have.been.called
    expect(navigate).not.to.have.been.called

    cy.clock(Date.now())
    cy.then(() => key('g'))
    cy.tick(2000)
    cy.then(() => {
      key('p')
      expect(navigate).not.to.have.been.called
      setNavigate(null)
      off()
    })
  })

  it('does not navigate while a menu is open', () => {
    const navigate = cy.stub()
    setNavigate(navigate)

    overlay('v-overlay v-menu v-overlay--active', () => {
      key('g')
      key('p')
    })

    expect(navigate).not.to.have.been.called
    setNavigate(null)
  })

  it('maps [ and ] to the previous and next tab and \\ to the side panel', () => {
    const actions = { prevTab: cy.stub(), nextTab: cy.stub(), aside: cy.stub() }
    const off = register(actions)

    expect(key('[').defaultPrevented).to.equal(true)
    key(']')
    key(']')

    expect(key('\\').defaultPrevented).to.equal(true)
    expect(actions.aside).to.have.been.calledOnce
    expect(actions.prevTab).to.have.been.calledOnce
    expect(actions.nextTab).to.have.been.calledTwice
    off()
  })

  it('clicks the enabled confirm button of the topmost dialog with Ctrl/Cmd+Enter', () => {
    const lower = document.createElement('div')
    const upper = document.createElement('div')
    const lowerBtn = document.createElement('button')
    const upperBtn = document.createElement('button')
    const lowerClick = cy.stub()
    const upperClick = cy.stub()

    lower.className = upper.className = 'v-overlay v-dialog v-overlay--active'
    lowerBtn.dataset.confirm = upperBtn.dataset.confirm = ''
    lowerBtn.addEventListener('click', lowerClick)
    upperBtn.addEventListener('click', upperClick)
    lower.appendChild(lowerBtn)
    upper.appendChild(upperBtn)
    document.body.append(lower, upper)

    expect(key('Enter', { ctrlKey: true }).defaultPrevented).to.equal(true)
    key('Enter', { metaKey: true, repeat: true })
    expect(upperClick).to.have.been.calledOnce
    expect(lowerClick).not.to.have.been.called

    upperBtn.disabled = true
    expect(key('Enter', { metaKey: true }).defaultPrevented).to.equal(false)
    expect(upperClick).to.have.been.calledOnce

    lower.remove()
    upper.remove()
  })

  it('confirms dialogs from within text fields', () => {
    const dialog = document.createElement('div')
    const input = document.createElement('textarea')
    const btn = document.createElement('button')
    const click = cy.stub()

    dialog.className = 'v-overlay v-dialog v-overlay--active'
    btn.dataset.confirm = ''
    btn.addEventListener('click', click)
    dialog.append(input, btn)
    document.body.appendChild(dialog)

    input.addEventListener('keydown', keydown)
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }))
    expect(click).to.have.been.calledOnce

    // plain Enter keeps its normal behavior
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
    expect(click).to.have.been.calledOnce
    dialog.remove()
  })

  it('toggles the command palette with Ctrl/Cmd+K', () => {
    expect(key('k', { ctrlKey: true }).defaultPrevented).to.equal(true)
    expect(shortcuts.palette).to.equal(true)
    key('K', { metaKey: true })
    expect(shortcuts.palette).to.equal(false)

    // not above another dialog
    overlay('v-overlay v-dialog v-overlay--active', () => key('k', { ctrlKey: true }))
    expect(shortcuts.palette).to.equal(false)
  })

  it('leaves Ctrl/Cmd+K to the rich text editor', () => {
    const editor = document.createElement('div')
    editor.contentEditable = 'true'
    document.body.appendChild(editor)

    const e = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true, cancelable: true })
    editor.addEventListener('keydown', keydown)
    editor.dispatchEvent(e)
    editor.remove()

    expect(e.defaultPrevented).to.equal(false)
    expect(shortcuts.palette).to.equal(false)
  })

  it('runs actions directly even while a dialog is open', () => {
    const fn = cy.stub()
    const off = register({ save: fn })

    overlay('v-overlay v-dialog v-overlay--active', () => {
      expect(trigger('save')).to.equal(false)
      expect(run('save')).to.equal(true)
      expect(run('publish')).to.equal(false)
    })

    expect(fn).to.have.been.calledOnce
    off()
  })

  it('tracks the actions of the topmost view reactively', () => {
    const saving = computed(() => has('save'))
    expect(saving.value).to.equal(false)

    const off = register({ save: cy.stub() })
    expect(saving.value).to.equal(true)

    const off2 = register({ search: cy.stub() })
    expect(saving.value).to.equal(false)

    off2()
    expect(saving.value).to.equal(true)

    off()
    expect(saving.value).to.equal(false)
  })

  it('formats the key hints of commands and routes', () => {
    expect(hint('save')).to.equal(commands.save.keys.join('+'))
    expect(hint('back')).to.equal('Esc')
    expect(hint('page:view')).to.equal('G P')
    expect(hint('unknown')).to.equal('')
  })

  it('executes global commands and view actions', () => {
    const fn = cy.stub()
    const off = register({ search: fn })

    expect(available('sheet')).to.equal(true)
    expect(available('search')).to.equal(true)
    expect(available('save')).to.equal(false)

    execute('sheet')
    expect(shortcuts.sheet).to.equal(true)
    execute('search')
    expect(fn).to.have.been.calledOnce
    off()
  })

  it('ignores Ctrl/Cmd shortcuts with unexpected modifiers', () => {
    const off = register({ save: cy.stub() })

    expect(key('k', { ctrlKey: true, shiftKey: true }).defaultPrevented).to.equal(false)
    expect(shortcuts.palette).to.equal(false)
    expect(key('K', { metaKey: true }).defaultPrevented).to.equal(true)
    expect(shortcuts.palette).to.equal(true)
    off()
  })

  it('accepts characters typed with AltGr but not Ctrl+Alt shortcuts', () => {
    const actions = { prevTab: cy.stub(), aside: cy.stub(), search: cy.stub() }
    const off = register(actions)

    expect(key('[', { ctrlKey: true, altKey: true, modifierAltGraph: true }).defaultPrevented).to.equal(true)
    key('\\', { ctrlKey: true, altKey: true, modifierAltGraph: true })
    key('[', { ctrlKey: true, altKey: true }) // Ctrl+Alt+[ without AltGr
    key('/', { altKey: true })

    expect(actions.prevTab).to.have.been.calledOnce
    expect(actions.aside).to.have.been.calledOnce
    expect(actions.search).not.to.have.been.called
    off()
  })

  it('does not save with AltGr+S', () => {
    const fn = cy.stub()
    const off = register({ save: fn })

    key('s', { ctrlKey: true, altKey: true, modifierAltGraph: true })
    expect(fn).not.to.have.been.called
    off()
  })

  it('derives the displayed keys and the aria shortcuts from the keys', () => {
    const mod = commands.save.keys[0]
    const aria = commands.save.aria.split('+')[0]

    expect(['Ctrl', '⌘']).to.include(mod)
    expect(commands.publish.keys).to.deep.equal([mod, 'Shift', 'S'])
    expect(commands.publish.aria).to.equal(`${aria}+Shift+S`)
    expect(commands.confirm.keys).to.deep.equal([mod, 'Enter'])
    expect(commands.back.keys).to.deep.equal(['Esc'])
    expect(commands.back.aria).to.equal('Escape')
    expect(commands.select.keys).to.deep.equal(['Space'])
    expect(commands.select.aria).to.equal('Space')
    expect(commands.drop.keys).to.deep.equal(['Del'])
    expect(commands.sheet.aria).to.equal('Shift+?')
  })

  it('matches key events to the commands of the given scopes', () => {
    const ev = (key, opts = {}) => new KeyboardEvent('keydown', { key, ...opts })
    const input = document.createElement('input')
    const button = document.createElement('button')
    const withTarget = (e, target) => (Object.defineProperty(e, 'target', { value: target }), e)

    expect(command(ev(' '), 'list')).to.equal('select')
    expect(command(ev('Delete'), 'list')).to.equal('drop')
    expect(command(ev('C', { ctrlKey: true }), 'tree')).to.equal('copy')
    expect(command(ev('x', { metaKey: true }), 'list', 'tree')).to.equal('cut')
    expect(command(ev('c', { ctrlKey: true }), 'list')).to.equal(undefined)
    expect(command(ev('c'), 'tree')).to.equal(undefined)
    expect(command(ev('v', { ctrlKey: true, shiftKey: true }), 'tree')).to.equal(undefined)
    expect(command(ev(' ', { shiftKey: true }), 'list')).to.equal(undefined)
    expect(command(ev('Delete', { altKey: true }), 'list')).to.equal(undefined)
    expect(command(withTarget(ev('Delete'), input), 'list')).to.equal(undefined)
    expect(command(withTarget(ev(' '), button), 'list')).to.equal(undefined)
    expect(command(withTarget(ev('c', { ctrlKey: true }), button), 'tree')).to.equal('copy')
    expect(command(ev(' ', { repeat: true }), 'list')).to.equal(undefined)
  })

  it('leaves the page tree shortcuts to the page tree', () => {
    expect(key('c', { ctrlKey: true }).defaultPrevented).to.equal(false)
    expect(key('v', { metaKey: true }).defaultPrevented).to.equal(false)
  })
})
