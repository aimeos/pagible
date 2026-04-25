/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gql from 'graphql-tag'

const mutations = {
  element: 'pubElement',
  file: 'pubFile',
  page: 'pubPage'
}

/**
 * @param {Object} vm Vue component instance
 * @param {String} type 'page', 'element', or 'file'
 * @param {Object} msgs { success, scheduled(date), error }
 * @param {Date|null} at Schedule date or null for immediate publish
 */
export function publishItem(vm, type, msgs, at = null) {
  if (!vm.user.can(`${type}:publish`)) {
    vm.messages.add(vm.$gettext('Permission denied'), 'error')
    return
  }

  vm.publishing = true

  vm.save(true)
    .then((valid) => {
      if (!valid || vm.changed) {
        return
      }

      return vm.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: [ID!]!, $at: DateTime) {
              ${mutations[type]}(id: $id, at: $at) {
                id
              }
            }
          `,
          variables: {
            id: [vm.item.id],
            at: at?.toISOString()?.substring(0, 19)?.replace('T', ' ')
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          if (!at) {
            vm.item.published = true
            vm.messages.add(msgs.success, 'success')
          } else {
            vm.item.publish_at = at
            vm.messages.add(msgs.scheduled(at), 'info')
          }

          vm.viewStack.closeView()
        })
        .catch((error) => {
          vm.messages.add(msgs.error + ':\n' + error, 'error')
          vm.$log(`${type}Detail::publish(): Error publishing ${type}`, at, error)
        })
    })
    .finally(() => {
      vm.publishing = false
    })
}
