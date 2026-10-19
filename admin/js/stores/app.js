/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'
import { urladmin, urlasset, urlproxy, urlpage, urlfile, multidomain } from '../config'

export const useAppStore = defineStore('app', {
  state: () => ({
    urladmin,
    urlasset,
    urlproxy,
    urlpage,
    urlfile,
    multidomain
  })
})
