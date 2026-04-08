/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gql from 'graphql-tag'
import { markRaw } from 'vue'
import { defineStore } from 'pinia'
import { apolloClient } from './graphql'
import {
  urladmin,
  urlproxy,
  urlpage,
  urlfile,
  multidomain,
  locales as appLocales,
  config as appConfig,
  schemas as appSchemas
} from './config'

export const useAppStore = defineStore('app', {
  state: () => ({
    urladmin,
    urlproxy,
    urlpage,
    urlfile,
    multidomain
  })
})

export const useUserStore = defineStore('user', {
  state: () => ({
    me: null,
    urlintended: null,
    saveTimer: null
  }),

  actions: {
    can(action) {
      if (!Array.isArray(action)) {
        action = [action]
      }

      for (const act of action) {
        if (this.me?.permission?.[act]) {
          return true
        }
      }
      return false
    },

    intended(url) {
      return url ? (this.urlintended = url) : this.urlintended
    },

    async isAuthenticated() {
      if (this.me !== null) {
        return !!this.me
      }

      await apolloClient
        .query({
          query: gql`
            query {
              me {
                permission
                settings
                email
                name
              }
            }
          `
        })
        .then((response) => {
          if (response.errors) {
            throw response
          }

          this.me = response.data.me
            ? { ...response.data.me, permission: JSON.parse(response.data.me.permission || '{}') }
            : false
        })
        .catch((error) => {
          console.error('Failed to fetch user data', error)
          this.me = false
        })

      return !!this.me
    },

    login(email, password) {
      return apolloClient
        .mutate({
          mutation: gql`
            mutation ($email: String!, $password: String!) {
              cmsLogin(email: $email, password: $password) {
                permission
                settings
                email
                name
              }
            }
          `,
          variables: {
            email: email,
            password: password
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          this.me = response.data.cmsLogin || false

          if (this.me?.permission) {
            this.me.permission = JSON.parse(this.me.permission)
          }

          return this.me
        })
        .catch((error) => {
          this.me = false
          throw error
        })
    },

    logout() {
      clearTimeout(this.saveTimer)
      this.saveTimer = null

      return apolloClient
        .mutate({
          mutation: gql`
            mutation {
              cmsLogout {
                email
                name
              }
            }
          `
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          return response.data.cmsLogout || false
        })
        .finally(() => {
          this.me = null
          return apolloClient.clearStore()
        })
    },

    async user() {
      if (await this.isAuthenticated()) {
        return this.me
      }

      return null
    },

    getData(panel, key, defval = null) {
      return this.me?.settings?.[panel]?.[key] ?? defval
    },

    saveData(panel, key, value) {
      if (!this.me) return

      if (!this.me.settings) {
        this.me.settings = {}
      }
      if (!this.me.settings[panel]) {
        this.me.settings[panel] = {}
      }

      this.me.settings[panel][key] = value

      clearTimeout(this.saveTimer)
      this.saveTimer = setTimeout(() => this.flush(), 60000)
    },

    flush() {
      if (!this.saveTimer || !this.me?.settings) return

      clearTimeout(this.saveTimer)
      this.saveTimer = null

      const messages = useMessageStore()

      apolloClient
        .mutate({
          mutation: gql`
            mutation ($settings: JSON!) {
              cmsUser(settings: $settings) {
                settings
              }
            }
          `,
          variables: {
            settings: JSON.stringify(this.me.settings)
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }
        })
        .catch((error) => {
          messages.add('Failed to save user settings:\n' + error, 'error')
          console.error('Failed to save user data', error)
        })
    }
  }
})

export const useClipboardStore = defineStore('clipboard', {
  state: () => ({}),

  actions: {
    get(key, defval = null) {
      return this[key] ?? defval
    },

    set(key, value) {
      if (typeof key !== 'string') {
        return
      }

      this[key] = value
    }
  }
})

export const useConfigStore = defineStore('config', {
  state: () => appConfig,

  actions: {
    get(key, defval = null) {
      if (typeof key !== 'string') {
        return defval
      }

      const val = key.split('.').reduce((part, key) => {
        return typeof part === 'object' && part !== null ? part[key] : part
      }, this)

      return typeof val === 'undefined' ? defval : val
    }
  }
})

export const useDrawerStore = defineStore('drawer', {
  state: () => ({
    aside: null,
    nav: null
  }),

  actions: {
    toggle(key) {
      this[key] = !this[key]
    }
  }
})

export const useLanguageStore = defineStore('language', {
  state: () => ({
    translations: {
      aa: 'Afar',
      ab: 'Аҧсуа',
      af: 'Afrikaans',
      ak: 'Akana',
      am: 'አማርኛ',
      an: 'Aragonés',
      ar: 'العربية',
      as: 'অসমীয়া',
      av: 'Авар',
      ay: 'Aymar',
      az: 'Azərbaycanca',
      ba: 'Башҡорт',
      be: 'Беларуская',
      bg: 'Български',
      bh: 'भोजपुरी',
      bi: 'Bislama',
      bm: 'Bamanankan',
      bn: 'বাংলা',
      bo: 'བོད་ཡིག',
      br: 'Brezhoneg',
      bs: 'Bosanski',
      ca: 'Català',
      ce: 'Нохчийн',
      ch: 'Chamoru',
      co: 'Corsu',
      cr: 'Nehiyaw',
      cs: 'Česky',
      cu: 'словѣньскъ',
      cv: 'Чăваш',
      cy: 'Cymraeg',
      da: 'Dansk',
      de: 'Deutsch',
      dv: 'ދިވެހިބަސް',
      dz: 'རྫོང་ཁ',
      ee: 'Ɛʋɛ',
      el: 'Ελληνικά',
      en: 'English',
      eo: 'Esperanto',
      es: 'Español',
      et: 'Eesti',
      eu: 'Euskara',
      fa: 'فارسی',
      ff: 'Fulfulde',
      fi: 'Suomi',
      fj: 'Na Vosa Vakaviti',
      fo: 'Føroyskt',
      fr: 'Français',
      fy: 'Frysk',
      ga: 'Gaeilge',
      gd: 'Gàidhlig',
      gl: 'Galego',
      gn: "Avañe'ẽ",
      gu: 'ગુજરાતી',
      gv: 'Gaelg',
      ha: 'هَوُسَ',
      he: 'עברית',
      hi: 'हिन्दी',
      ho: 'Hiri Motu',
      hr: 'Hrvatski',
      ht: 'Krèyol ayisyen',
      hu: 'Magyar',
      hy: 'Հայերեն',
      hz: 'Otsiherero',
      ia: 'Interlingua',
      id: 'Bahasa Indonesia',
      ie: 'Interlingue',
      ig: 'Igbo',
      ii: 'ꆇꉙ',
      ik: 'Iñupiak',
      io: 'Ido',
      is: 'Íslenska',
      it: 'Italiano',
      iu: 'ᐃᓄᒃᑎᑐᑦ',
      ja: '日本語',
      jv: 'Basa Jawa',
      ka: 'ქართული',
      kg: 'KiKongo',
      ki: 'Gĩkũyũ',
      kj: 'Kuanyama',
      kk: 'Қазақша',
      kl: 'Kalaallisut',
      km: 'ភាសាខ្មែរ',
      kn: 'ಕನ್ನಡ',
      ko: '한국어',
      kr: 'Kanuri',
      ks: 'कॉशुर',
      ku: 'Kurdî',
      kv: 'Коми',
      kw: 'Kernewek',
      ky: 'Kırgızca',
      la: 'Latina',
      lb: 'Lëtzebuergesch',
      lg: 'Luganda',
      li: 'Limburgs',
      ln: 'Lingála',
      lo: 'ລາວ',
      lt: 'Lietuvių',
      lv: 'Latviešu',
      mg: 'Malagasy',
      mh: 'Kajin Majel',
      mi: 'Māori',
      mk: 'Македонски',
      ml: 'മലയാളം',
      mn: 'Монгол',
      mo: 'Moldovenească',
      mr: 'मराठी',
      ms: 'Bahasa Melayu',
      mt: 'bil-Malti',
      my: 'Myanmasa',
      na: 'Dorerin Naoero',
      nd: 'Sindebele',
      ne: 'नेपाली',
      ng: 'Oshiwambo',
      nl: 'Nederlands',
      nn: 'Norsk (nynorsk)',
      no: 'Norsk',
      nr: 'isiNdebele',
      nv: 'Diné bizaad',
      ny: 'Chi-Chewa',
      oc: 'Occitan',
      oj: 'ᐊᓂᔑᓈᐯᒧᐎᓐ',
      om: 'Oromoo',
      or: 'ଓଡ଼ିଆ',
      os: 'Иронау',
      pa: 'ਪੰਜਾਬੀ',
      pi: 'Pāli',
      pl: 'Polski',
      ps: 'پښتو',
      pt: 'Português',
      qu: 'Runa Simi',
      rm: 'Rumantsch',
      rn: 'Kirundi',
      ro: 'Română',
      ru: 'Русский',
      rw: 'Kinyarwandi',
      sa: 'संस्कृतम्',
      sc: 'Sardu',
      sd: 'सिंधी',
      se: 'Davvisámegiella',
      sg: 'Sängö',
      sh: 'Srpskohrvatski',
      si: 'සිංහල',
      sk: 'Slovenčina',
      sl: 'Slovenščina',
      sm: 'Gagana Samoa',
      sn: 'chiShona',
      so: 'Soomaaliga',
      sq: 'shqip',
      sr: 'Српски',
      ss: 'SiSwati',
      st: 'Sesotho',
      su: 'Basa Sunda',
      sv: 'Svenska',
      sw: 'Kiswahili',
      ta: 'தமிழ்',
      te: 'తెలుగు',
      tet: 'Tetun',
      tg: 'Тоҷикӣ',
      th: 'ไทย',
      ti: 'ትግርኛ',
      tk: 'Туркмен',
      tl: 'Tagalog',
      tn: 'Setswana',
      to: 'Lea Faka-Tonga',
      tr: 'Türkçe',
      ts: 'Xitsonga',
      tt: 'Tatarça',
      tw: 'Twi',
      ty: "Reo Mā'ohi",
      ug: 'Uyƣurqə',
      uk: 'Українська',
      ur: 'اردو',
      uz: 'Ўзбек',
      ve: 'Tshivenḓa',
      vi: 'Tiếng Việt',
      vo: 'Volapük',
      wa: 'Walon',
      wo: 'Wollof',
      xh: 'isiXhosa',
      yi: 'ייִדיש',
      yo: 'Yorùbá',
      za: 'Cuengh',
      zg: 'ⵜⴰⵎⴰⵣⵉⵖⵜ',
      zh: '中文',
      zu: 'isiZulu'
    },
    available: appLocales
  }),

  actions: {
    default() {
      return Object.keys(this.available)[0] || 'en'
    },

    translate(key) {
      return this.translations[key] ?? this.translations[key?.substring(0, 2)] ?? key
    }
  }
})

/**
 * Store for queued messages to display to the user
 */
export const useMessageStore = defineStore('message', {
  state: () => ({
    queue: []
  }),

  actions: {
    add(msg, type = 'info', timeout = null) {
      if (this.queue.length >= 10) {
        console.warn('Message queue overflow, dropping message:', msg)
        return
      }
      this.queue.push({
        text: msg,
        color: type,
        contentClass: 'text-pre-line',
        timeout: timeout || (type === 'error' ? 10000 : 3000)
      })
    }
  }
})

/**
 * Available element schemas
 */
export const useSchemaStore = defineStore('schema', {
  state: () => appSchemas
})

/**
 * Side store with contextual information
 *
 * store: {
 *   type: {
 *     "heading": 3,
 *     "text": 8,
 *     "article": 1
 *   }
 * },
 * show: {
 *   type: {
 *     "heading": false,
 *     "text": true,
 *   }
 * }
 */
export const useSideStore = defineStore('side', {
  state: () => ({
    store: {},
    show: {}
  }),

  actions: {
    shown(key, what) {
      if (typeof this.show[key] === 'undefined') {
        this.show[key] = {}
      }

      if (typeof this.show[key][what] === 'undefined') {
        this.show[key][what] = true
      }

      return this.show[key][what]
    },

    toggle(key, what) {
      if (!this.show[key]) {
        this.show[key] = {}
      }
      this.show[key][what] = !this.show[key][what]
    }
  }
})

export const useViewStack = defineStore('viewStack', {
  state: () => ({
    stack: []
  }),

  actions: {
    openView(component, props = {}) {
      if (!component) {
        console.error('Component is not defined')
        return
      }

      this.stack.push({
        component: markRaw(component),
        props: props || {}
      })
    },

    closeView() {
      this.stack.pop()
    }
  }
})
