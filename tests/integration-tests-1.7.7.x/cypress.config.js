const { defineConfig } = require('cypress')

module.exports = defineConfig({
  chromeWebSecurity: false,
  viewportWidth: 1366,
  viewportHeight: 786,
  retries: {
    runMode: 2,
    openMode: 0,
  },
  pageLoadTimeout: 180000,
  env: {
    runDiscountsTests: false,
    videoCompression: false,
    videoUploadOnPasses: false,
    NODE_TLS_REJECT_UNAUTHORIZED: 0,
  },
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
  },
})
