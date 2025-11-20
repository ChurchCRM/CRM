describe('List Options API - GET Tests', () => {
    it('should GET deposit types successfully', () => {
        cy.makePrivateAdminAPICall('GET', '/api/system/list-options/deposit-types', null, 200)
            .then((response) => {
                expect(response.body).to.be.an('array');
                expect(response.body.length).to.be.greaterThan(0);
                
                // Verify each item has required properties
                response.body.forEach(item => {
                    expect(item).to.have.property('OptionId');
                    expect(item).to.have.property('OptionName');
                });
            });
    });

    it('should GET person classifications successfully', () => {
        cy.makePrivateAdminAPICall('GET', '/api/system/list-options/person-classifications', null, 200)
            .then((response) => {
                expect(response.body).to.be.an('array');
                expect(response.body.length).to.be.greaterThan(0);
                
                // Verify each item has required properties
                response.body.forEach(item => {
                    expect(item).to.have.property('OptionId');
                    expect(item).to.have.property('OptionName');
                });
            });
    });

    it('should GET family roles successfully', () => {
        cy.makePrivateAdminAPICall('GET', '/api/system/list-options/family-roles', null, 200)
            .then((response) => {
                expect(response.body).to.be.an('array');
                expect(response.body.length).to.be.greaterThan(0);
                
                // Verify each item has required properties
                response.body.forEach(item => {
                    expect(item).to.have.property('OptionId');
                    expect(item).to.have.property('OptionName');
                });
            });
    });

    it('should GET group types successfully', () => {
        cy.makePrivateAdminAPICall('GET', '/api/system/list-options/group-types', null, 200)
            .then((response) => {
                expect(response.body).to.be.an('array');
                expect(response.body.length).to.be.greaterThan(0);
                
                // Verify each item has required properties
                response.body.forEach(item => {
                    expect(item).to.have.property('OptionId');
                    expect(item).to.have.property('OptionName');
                });
            });
    });
});
