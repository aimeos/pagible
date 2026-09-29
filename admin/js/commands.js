/**
 * @license MIT, https://opensource.org/license/mit
 */

import {
  mdiArrowLeft,
  mdiCheck,
  mdiCheckboxMarkedOutline,
  mdiDelete,
  mdiChevronLeft,
  mdiChevronRight,
  mdiConsoleLine,
  mdiContentCopy,
  mdiContentCut,
  mdiContentPaste,
  mdiContentSave,
  mdiDockRight,
  mdiFileTree,
  mdiFolderMultipleImage,
  mdiKeyboardOutline,
  mdiKeyVariant,
  mdiMagnify,
  mdiPlus,
  mdiPublish,
  mdiShareVariant
} from '@mdi/js'
import gettext from './i18n'

export const isMac = /Mac|iPhone|iPad/.test(navigator.userAgentData?.platform || navigator.platform || '')
const modKey = isMac ? '⌘' : 'Ctrl'
const ariaMod = isMac ? 'Meta' : 'Control'

// displayed names of KeyboardEvent keys, single characters are shown in upper case
const NAMES = { ' ': 'Space', Escape: 'Esc', Delete: 'Del' }

const upper = (key) => (key.length === 1 ? key.toUpperCase() : key)
const display = (key) => NAMES[key] || upper(key)
const ariaName = (key) => (key === ' ' ? 'Space' : upper(key))

/**
 * Commands used for the key handling, the command palette and the shortcut sheet
 * "scope" is "global" (independent of the view), "view" (action of the topmost view) or "list" (focused list item)
 * "scope" "tree" commands are handled by the page tree
 * "key" contains the KeyboardEvent keys (with Ctrl/Cmd if "mod" and Shift if "shift" is set)
 * The displayed "keys" and the "aria" value for aria-keyshortcuts are added from the first key
 */
export const commands = {
  palette: {
    scope: 'global',
    icon: mdiConsoleLine,
    mod: true,
    key: ['k'],
    label: () => gettext.$gettext('Open commands')
  },
  confirm: {
    scope: 'global',
    icon: mdiCheck,
    mod: true,
    key: ['Enter'],
    label: () => gettext.$gettext('Confirm dialog')
  },
  save: {
    scope: 'view',
    icon: mdiContentSave,
    mod: true,
    key: ['s'],
    label: () => gettext.$gettext('Save changes')
  },
  publish: {
    scope: 'view',
    icon: mdiPublish,
    mod: true,
    shift: true,
    key: ['s'],
    label: () => gettext.$gettext('Open publish menu')
  },
  create: {
    scope: 'view',
    icon: mdiPlus,
    key: ['n'],
    label: () => gettext.$gettext('New page, file or element')
  },
  search: {
    scope: 'view',
    icon: mdiMagnify,
    key: ['/'],
    label: () => gettext.$gettext('Search in list')
  },
  prevTab: {
    scope: 'view',
    icon: mdiChevronLeft,
    key: ['['],
    label: () => gettext.$gettext('Previous tab')
  },
  nextTab: {
    scope: 'view',
    icon: mdiChevronRight,
    key: [']'],
    label: () => gettext.$gettext('Next tab')
  },
  aside: {
    scope: 'view',
    icon: mdiDockRight,
    key: ['\\'],
    label: () => gettext.$gettext('Show or hide side panel')
  },
  back: {
    scope: 'view',
    icon: mdiArrowLeft,
    key: ['Escape'],
    label: () => gettext.$gettext('Back to list view')
  },
  sheet: {
    scope: 'global',
    icon: mdiKeyboardOutline,
    key: ['?'],
    aria: 'Shift+?',
    label: () => gettext.$gettext('Show keyboard shortcuts')
  },
  select: {
    scope: 'list',
    icon: mdiCheckboxMarkedOutline,
    key: [' '],
    label: () => gettext.$gettext('Select focused page, file or element')
  },
  drop: {
    scope: 'list',
    icon: mdiDelete,
    key: isMac ? ['Delete', 'Backspace'] : ['Delete'], // Mac keyboards label Backspace as "delete"
    label: () => gettext.$gettext('Delete focused page, file or element')
  },
  copy: {
    scope: 'tree',
    icon: mdiContentCopy,
    mod: true,
    key: ['c'],
    label: () => gettext.$gettext('Copy page')
  },
  cut: {
    scope: 'tree',
    icon: mdiContentCut,
    mod: true,
    key: ['x'],
    label: () => gettext.$gettext('Cut page')
  },
  paste: {
    scope: 'tree',
    icon: mdiContentPaste,
    mod: true,
    key: ['v'],
    label: () => gettext.$gettext('Paste page')
  }
}

for (const cmd of Object.values(commands)) {
  const key = cmd.key[0]

  cmd.keys = [cmd.mod && modKey, cmd.shift && 'Shift', display(key)].filter(Boolean)
  cmd.aria ??= [cmd.mod && ariaMod, cmd.shift && 'Shift', ariaName(key)].filter(Boolean).join('+')
}

/**
 * Routes reachable by "g" followed by the key
 * "title" is the name of the panel, "label" describes the shortcut
 */
export const routes = {
  'page:view': {
    icon: mdiFileTree,
    key: ['p'],
    title: () => gettext.$gettext('Pages'),
    label: () => gettext.$gettext('Go to pages')
  },
  'file:view': {
    icon: mdiFolderMultipleImage,
    key: ['m'],
    title: () => gettext.$gettext('Media'),
    label: () => gettext.$gettext('Go to media')
  },
  'element:view': {
    icon: mdiShareVariant,
    key: ['e'],
    title: () => gettext.$gettext('Shared elements'),
    label: () => gettext.$gettext('Go to shared elements')
  },
  'access:view': {
    icon: mdiKeyVariant,
    key: ['u'],
    title: () => gettext.$gettext('Users'),
    label: () => gettext.$gettext('Go to users')
  }
}

for (const route of Object.values(routes)) {
  route.keys = ['G', display(route.key[0])]
}

/**
 * Returns the displayed keys of a command or route, e.g. "Ctrl+S" or "G P" for key sequences
 */
export function hint(name) {
  return commands[name]?.keys.join('+') || routes[name]?.keys.join(' ') || ''
}
