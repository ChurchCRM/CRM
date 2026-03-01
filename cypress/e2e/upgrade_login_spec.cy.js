describe('Upgrade - Login smoke test', () => {
  const user = Cypress.env('LOGIN_USER') || 'admin'
  const pass = Cypress.env('LOGIN_PASS') || 'admin'

  it('visits root and attempts to login', () => {
    cy.visit('/')

    // If redirected to setup, fail fast
    cy.get('body').then(($b) => {
      if ($b.text().toLowerCase().includes('setup') || $b.find('form#setupForm').length) {
        // explicitly fail
        throw new Error('Site redirected to setup wizard; fresh install not ready for login')
      }
    })

    // Find a username input (try several common selectors)
    cy.get('form').then(($form) => {
      const textInput = $form.find('input[type="text"]').first()
      const emailInput = $form.find('input[type="email"]').first()
      const passwordInput = $form.find('input[type="password"]').first()

      if (textInput.length && passwordInput.length) {
        cy.wrap(textInput).type(user)
        cy.wrap(passwordInput).type(pass, { log: false })
        cy.wrap($form).find('button[type="submit"], input[type="submit"]').first().click()
      } else if (emailInput.length && passwordInput.length) {
        cy.wrap(emailInput).type(user)
        cy.wrap(passwordInput).type(pass, { log: false })
        cy.wrap($form).find('button[type="submit"], input[type="submit"]').first().click()
      } else {
        // No recognizable login form
        throw new Error('Login form not found on page')
      }
    })

    // After submitting, look for a logout link or absence of login text
    cy.contains(/logout|sign out|log out/i, { timeout: 10000 }).should('exist')
  })
})
