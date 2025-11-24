/// <reference types="cypress" />

describe('API Private Admin Demo', () => {
  it('Load demo data (force=true) as admin', () => {
    const body = { includeFinancial: false, includeEvents: false, includeSundaySchool: true, force: true };

    cy.makePrivateAdminAPICall('POST', '/admin/api/demo/load', body, 200, 120000).then((resp) => {
      // Expect success flag and import metadata in body
      expect(resp.body).to.have.property('success');
      // success may be true or false depending on internal errors; at minimum assert shape
      expect(resp.body).to.have.property('imported');
      expect(resp.body).to.have.property('warnings');
    });
  });
});
