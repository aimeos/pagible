/**
 * @license MIT, https://opensource.org/license/mit
 */

/**
 * Logging plugin for the application
 */
export default {
  install(app) {
    app.config.globalProperties.$log = (...args) => {
      console.error(...args)
    }
  }
}
