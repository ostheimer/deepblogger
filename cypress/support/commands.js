// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

Cypress.Commands.add('login', () => {
  cy.session('wordpress-session', () => {
    cy.visit('/wp-login.php')
    cy.get('#user_login').type(Cypress.env('wpUsername'))
    cy.get('#user_pass').type(Cypress.env('wpPassword'))
    cy.get('#wp-submit').click()
    cy.url().should('include', '/wp-admin')
  })
})

// -- This is a child command --
Cypress.Commands.add('navigateToDeepBlogger', () => {
  cy.visit('/wp-admin/admin.php?page=deepblogger')
}) 