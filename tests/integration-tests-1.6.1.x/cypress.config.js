const { defineConfig } = require('cypress')

module.exports = defineConfig({
  chromeWebSecurity: false,
  retries: {
    runMode: 2,
    openMode: 2,
  },
  env: {
    runDiscountsTests: false,
  },
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
  },
})
