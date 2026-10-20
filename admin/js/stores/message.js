/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'
import gettext from '../i18n'

// Maximum number of queued messages, further ones are dropped
const MAX_QUEUE = 10
// Display durations in milliseconds of error messages, messages with an action and other messages
const TIMEOUT_ERROR = 10000
const TIMEOUT_ACTION = 8000
const TIMEOUT_DEFAULT = 3000

/**
 * Store for queued messages to display to the user
 */
// message actions are kept outside of the queue items because the items are passed as props
// to the snackbars; only their id is stored in the item as harmless data attribute
const messageActions = new Map()
let messageId = 0

export const useMessageStore = defineStore('message', {
  state: () => ({
    queue: []
  }),

  actions: {
    /**
     * Adds a message to the queue
     *
     * @param {String} msg Message text
     * @param {String} type Message type (info, success, warning, error)
     * @param {Number|null} timeout Display duration in milliseconds
     * @param {Object|null} action Optional button as { label, handler }
     */
    add(msg, type = 'info', timeout = null, action = null) {
      if (this.queue.length >= MAX_QUEUE) {
        console.warn('Message queue overflow, dropping message:', msg)
        return
      }

      const item = {
        text: msg,
        color: type,
        contentClass: 'text-pre-line',
        timeout: timeout || (type === 'error' ? TIMEOUT_ERROR : action ? TIMEOUT_ACTION : TIMEOUT_DEFAULT)
      }

      if (action) {
        const id = ++messageId
        messageActions.set(id, action)
        item['data-action'] = id
        item.onDismiss = () => messageActions.delete(id)
      }

      this.queue.push(item)
    },

    // removes all queued messages and their actions, e.g. undo actions of the previous user
    clear() {
      this.queue = []
      messageActions.clear()
    },

    action(id) {
      return messageActions.get(id) || null
    },

    /**
     * Adds the "Permission denied" error message
     *
     * @returns {Boolean} Always false
     */
    denied() {
      this.add(gettext.$gettext('Permission denied'), 'error')
      return false
    },

    /**
     * Adds an error message for the failed operation and logs the details
     *
     * @param {String} msg Translated description of the failed operation
     * @param {Error|String} error Error that occurred
     * @param {...any} args Additional values written to the console
     */
    error(msg, error, ...args) {
      this.add(msg + ':\n' + error, 'error')
      console.error(msg, ...args, error)
    },

    run(id) {
      const action = messageActions.get(id)
      messageActions.delete(id)
      action?.handler()
    }
  }
})
