// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Alternativ können wir hier globale Konfigurationen hinzufügen
Cypress.on('uncaught:exception', (err, runnable) => {
  // WordPress wirft manchmal Fehler, die wir für unsere Tests ignorieren können
  return false
})

// Deaktiviere Screenshots für fehlgeschlagene Tests
Cypress.Screenshot.defaults({
  screenshotOnRunFailure: false
}) 