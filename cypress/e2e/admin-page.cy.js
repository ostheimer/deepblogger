describe('DeepBlogger Admin Page', () => {
  beforeEach(() => {
    // Login durchführen
    cy.login()
    
    // Zur DeepBlogger-Seite navigieren
    cy.visit('/wp-admin/admin.php?page=deepblogger')
    
    // Warte auf das Laden der Seite und der JavaScript-Datei
    cy.window().should('have.property', 'jQuery')
    cy.window().should('have.property', 'deepbloggerAdmin')
    
    // Stelle sicher, dass die Admin-JS-Datei geladen wurde
    cy.document().then((doc) => {
      const script = doc.querySelector('script[src*="deepblogger-admin.js"]')
      if (!script) {
        cy.log('Warning: deepblogger-admin.js nicht gefunden')
      }
    })
  })

  it('sollte die Einstellungsseite korrekt anzeigen', () => {
    cy.get('#deepblogger_openai_api_key').should('be.visible')
    cy.get('#deepblogger_openai_model').should('be.visible')
    cy.get('#deepblogger_generate_now').should('be.visible')
  })

  it('sollte den API-Key im Hintergrund speichern können', () => {
    const testApiKey = 'test-api-key-123'
    
    // Intercepte die AJAX-Anfrage
    cy.intercept('POST', '**/admin-ajax.php').as('saveSettings')
    
    // Hole die aktuelle Nonce aus dem Window-Objekt
    cy.window().then((win) => {
      const nonce = win.deepbloggerAdmin.nonce
      
      cy.get('#deepblogger_openai_api_key')
        .clear()
        .type(testApiKey)
      
      // Sende die AJAX-Anfrage mit der korrekten Nonce
      cy.request({
        method: 'POST',
        url: win.deepbloggerAdmin.ajaxurl,
        form: true, // Wichtig für WordPress AJAX
        body: {
          action: 'deepblogger_save_settings',
          nonce: nonce,
          api_key: testApiKey,
          model: 'gpt-4',
          posts_per_category: '3'
        }
      })
    })
    
    cy.wait('@saveSettings')
    
    cy.get('#settings-saved', { timeout: 10000 })
      .should('be.visible')
      .should('contain', 'Einstellungen erfolgreich gespeichert')
  })

  it('sollte die Anzahl der Beiträge pro Kategorie im Hintergrund speichern können', () => {
    const testValue = '3'
    
    cy.intercept('POST', '**/admin-ajax.php').as('saveSettings')
    
    cy.get('#deepblogger_posts_per_category')
      .clear()
      .type(testValue)
      .then(() => {
        cy.wait(500)
        
        return cy.window()
      })
      .then((win) => {
        const formData = new win.FormData()
        formData.append('action', 'deepblogger_save_settings')
        formData.append('nonce', win.deepbloggerAdmin.nonce)
        formData.append('deepblogger_posts_per_category', testValue)
        
        return cy.request({
          method: 'POST',
          url: win.ajaxurl,
          body: formData,
          headers: {
            'Content-Type': 'multipart/form-data'
          },
          timeout: 10000
        })
      })
    
    cy.wait('@saveSettings')
      .its('request.body')
      .should('include', 'action=deepblogger_save_settings')
    
    cy.get('#settings-saved', { timeout: 10000 })
      .should('be.visible')
      .should('contain', 'Einstellungen erfolgreich gespeichert')
  })

  it('sollte den Status beim Generieren aktualisieren', () => {
    cy.intercept('POST', '**/admin-ajax.php').as('generatePosts')
    
    cy.window().then((win) => {
      cy.wait(500)
      
      return cy.request({
        method: 'POST',
        url: win.ajaxurl,
        body: {
          action: 'deepblogger_generate_posts',
          nonce: win.deepbloggerAdmin.nonce
        },
        timeout: 10000
      })
    })
    
    cy.wait('@generatePosts')
      .its('request.body')
      .should('include', 'action=deepblogger_generate_posts')
    
    cy.get('#status_preparing', { timeout: 10000 })
      .should('be.visible')
      .should('have.class', 'active')
  })

  it('sollte Fehlermeldung bei ungültiger OpenAI API-Antwort anzeigen', () => {
    cy.intercept('POST', '**/admin-ajax.php', {
      statusCode: 200,
      body: {
        success: false,
        data: {
          message: 'Ungültige Antwort von der OpenAI API'
        }
      }
    }).as('saveSettings')
    
    cy.get('#deepblogger_openai_api_key')
      .clear()
      .type('invalid-api-key-123')
      .then(() => {
        cy.wait(500)
        
        return cy.window()
      })
      .then((win) => {
        const formData = new win.FormData()
        formData.append('action', 'deepblogger_save_settings')
        formData.append('nonce', win.deepbloggerAdmin.nonce)
        formData.append('deepblogger_openai_api_key', 'invalid-api-key-123')
        
        return cy.request({
          method: 'POST',
          url: win.ajaxurl,
          body: formData,
          headers: {
            'Content-Type': 'multipart/form-data'
          },
          timeout: 10000,
          failOnStatusCode: false
        })
      })
    
    cy.wait('@saveSettings')
    
    cy.get('#settings-error', { timeout: 10000 })
      .should('be.visible')
      .should('contain', 'Ungültige Antwort von der OpenAI API')
  })

  it('sollte Fehlermeldung bei Verbindungsproblemen anzeigen', () => {
    cy.intercept('POST', '**/admin-ajax.php', {
      statusCode: 500,
      body: 'Server Error'
    }).as('generateRequest')
    
    cy.window().then((win) => {
      cy.wait(500)
      
      return cy.request({
        method: 'POST',
        url: win.ajaxurl,
        body: {
          action: 'deepblogger_generate_posts',
          nonce: win.deepbloggerAdmin.nonce
        },
        timeout: 10000,
        failOnStatusCode: false
      })
    })
    
    cy.wait('@generateRequest')
    
    cy.get('#settings-error', { timeout: 10000 })
      .should('be.visible')
      .should('contain', 'Fehler: Konnte keine Verbindung zum Server herstellen')
  })

  // Mock API response
  cy.intercept('POST', '/wp-admin/admin-ajax.php', {
    statusCode: 200,
    body: {
      success: true,
      data: {
        api_key: 'test-key',
        model: '', // Kein Default-Modell
        posts_per_category: 1,
        categories: []
      }
    }
  }).as('saveSettings');
}) 
