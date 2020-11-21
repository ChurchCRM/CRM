/// <reference types="cypress" />

context('API Private Family Verify', () => {
    
    it('Verify API', () => {
        let result = cy.makePrivateAPICall("POST", '/api/family/2/verify', "", 200);

    });
});

