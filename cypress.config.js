const { defineConfig } = require('cypress')

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost:8000',
    setupNodeEvents(on, config) {
      // Hier können wir später Plugin-Events implementieren
    },
    env: {
      wpUsername: 'admin',
      wpPassword: 'admin'
    },
    chromeWebSecurity: false,
    retries: {
      runMode: 2,
      openMode: 0
    }
  },
}) 