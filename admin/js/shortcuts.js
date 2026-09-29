/**
 * @license MIT, https://opensource.org/license/mit
 */

import { getCurrentInstance, onActivated, onBeforeUnmount, onDeactivated, onMounted, reactive, shallowReactive } from 'vue'
import { commands, isMac, routes } from './commands'

export { commands, hint, routes } from './commands'

export const shortcuts = reactive({ palette: false, sheet: false })

// single key view actions which are ignored while typing or when a menu/dialog is open
const actionKeys = Object.fromEntries(
  Object.entries(commands)
    .filter(([, cmd]) => cmd.scope === 'view' && !cmd.mod && cmd.key)
    .flatMap(([name, cmd]) => cmd.key.map((key) => [key, name]))
)

// global Ctrl/Cmd shortcuts, e.g. "s" or "Shift+s"
const modKeys = Object.fromEntries(
  Object.entries(commands)
    .filter(([, cmd]) => cmd.mod && ['global', 'view'].includes(cmd.scope))
    .flatMap(([name, cmd]) => cmd.key.map((key) => [(cmd.shift ? 'Shift+' : '') + key, name]))
)

// second key after "g" and the route it navigates to
const routeKeys = Object.fromEntries(
  Object.entries(routes).flatMap(([name, route]) => route.key.map((key) => [key, name]))
)

// action maps of the mounted views, the last one is the topmost view
const views = shallowReactive([])

// time when "g" has been pressed, the next key must follow within the timeout
let leader = 0
const LEADER_TIMEOUT = 1500

let navigate = null

/**
 * Sets the function which navigates to a named route, e.g. (name) => router.push({ name })
 */
export function setNavigate(fn) {
  navigate = fn
}

/**
 * Registers the actions of a view, e.g. { save: fn, back: fn }
 * Only the actions of the most recently registered (topmost) view are triggered
 * Returns a function which unregisters the actions again
 */
export function register(actions) {
  views.push(actions)

  return () => {
    const idx = views.lastIndexOf(actions)
    idx !== -1 && views.splice(idx, 1)
  }
}

/**
 * Registers the actions returned by the factory while the component is mounted and active
 * (kept-alive list views are deactivated when a detail view replaces them)
 * The factory gets the component instance and can return null to register nothing
 */
export function useShortcuts(factory) {
  const vm = getCurrentInstance().proxy
  let off = null

  const bind = () => {
    const actions = off ? null : factory(vm)
    actions && (off = register(actions))
  }

  const unbind = () => {
    off?.()
    off = null
  }

  onMounted(bind)
  onActivated(bind)
  onDeactivated(unbind)
  onBeforeUnmount(unbind)
}

/**
 * Calls the action of the topmost view
 * Returns true if the action has been executed
 */
export function run(name) {
  const fn = views[views.length - 1]?.[name]
  fn?.()
  return !!fn
}

/**
 * Calls the action of the topmost view unless a modal dialog covers the view
 * Returns true if the action has been executed
 */
export function trigger(name) {
  return !dialogs().length && run(name)
}

/**
 * Returns true if the topmost view provides the action (reactive)
 */
export function has(name) {
  return !!views[views.length - 1]?.[name]
}

/**
 * Returns the name of the command of the given scopes matching the key event
 * Auto-repeated keys and keys typed in input fields are ignored,
 * buttons handle keys without Ctrl/Cmd themselves, e.g. Space
 */
export function command(ev, ...scopes) {
  const mod = ev.ctrlKey || ev.metaKey

  if (ev.isComposing || ev.repeat || ev.altKey || editable(ev.target) || (!mod && ev.target?.tagName === 'BUTTON')) {
    return undefined
  }

  const key = ev.key?.length === 1 ? ev.key.toLowerCase() : ev.key

  return Object.keys(commands).find((name) => {
    const cmd = commands[name]
    return scopes.includes(cmd.scope) && cmd.key.includes(key) && !!cmd.mod === mod && !!cmd.shift === ev.shiftKey
  })
}

// commands which don't depend on the current view
const globals = {
  palette() {
    // don't open the palette on top of another dialog
    if (shortcuts.palette || !dialogs().length) {
      shortcuts.palette = !shortcuts.palette
    }
  },
  sheet() {
    shortcuts.sheet = !shortcuts.sheet
  }
}

/**
 * Returns true if the command can be executed in the current view (reactive)
 */
export function available(name) {
  switch (commands[name]?.scope) {
    case 'view':
      return has(name)
    case 'global':
      return !!globals[name]
    default:
      return false
  }
}

/**
 * Executes a global command or the action of the topmost view
 */
export function execute(name) {
  globals[name] ? globals[name]() : run(name)
}

export function editable(el) {
  return !!el && (el.isContentEditable || ['INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName))
}

// open modal dialogs, the last one is the topmost dialog
function dialogs() {
  return document.querySelectorAll('.v-dialog.v-overlay--active')
}

/**
 * Returns true if Ctrl/Cmd/Alt are pressed for a shortcut and not only to type a character,
 * e.g. [ ] and \ require AltGr (reported as Ctrl+Alt on Windows) or Option on many layouts
 */
function modified(e) {
  const altGr = e.getModifierState?.('AltGraph') || (isMac && e.altKey && !e.ctrlKey && !e.metaKey)
  return !altGr && (e.ctrlKey || e.metaKey || e.altKey)
}

function overlay() {
  return !!document.querySelector('.v-overlay--active:not(.v-tooltip):not(.v-snackbar)')
}

/**
 * Clicks the enabled confirm button ([data-confirm]) of the topmost dialog
 * Returns true if a button has been clicked
 */
function confirm() {
  const list = dialogs()
  const btn = list[list.length - 1]?.querySelector('[data-confirm]:not([disabled]):not(.v-btn--loading)')

  btn?.click()
  return !!btn
}

export function keydown(e) {
  if (e.isComposing) {
    return
  }

  if ((e.ctrlKey || e.metaKey) && !e.altKey) {
    const key = e.key?.length === 1 ? e.key.toLowerCase() : e.key
    const name = modKeys[(e.shiftKey ? 'Shift+' : '') + key]

    switch (name) {
      case 'save':
      case 'publish':
        e.preventDefault() // never open the browser's "Save page" dialog
        !e.repeat && trigger(name)
        return
      case 'palette':
        // the rich text editor uses Ctrl/Cmd+K for links
        if (!e.target?.isContentEditable) {
          e.preventDefault()
          globals.palette()
        }
        return
      case 'confirm':
        !e.repeat && confirm() && e.preventDefault()
        return
    }
  }

  if (modified(e) || editable(e.target)) {
    return
  }

  if (Date.now() - leader < LEADER_TIMEOUT) {
    leader = 0

    if (routeKeys[e.key] && !overlay() && navigate) {
      e.preventDefault()
      navigate(routeKeys[e.key])
    }
    return
  }

  if (e.key === 'g' && !e.repeat && !overlay()) {
    leader = Date.now()
    return
  }

  if (commands.sheet.key.includes(e.key)) {
    e.preventDefault()
    globals.sheet()
    return
  }

  if (actionKeys[e.key] && !e.repeat && !overlay() && trigger(actionKeys[e.key])) {
    e.preventDefault()
  }
}
