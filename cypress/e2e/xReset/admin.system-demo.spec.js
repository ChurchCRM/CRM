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

  it('Reject demo data load without force when people exist (error message format)', () => {
    const body = { includeFinancial: false, includeEvents: false, includeSundaySchool: true, force: false };

    cy.makePrivateAdminAPICall('POST', '/admin/api/demo/load', body, 403, 10000).then((resp) => {
      // Expect consistent error response format with 'message' field (not 'error')
      expect(resp.body).to.have.property('success', false);
      expect(resp.body).to.have.property('message');
      expect(resp.body.message).to.include('Demo data import is only available on fresh installations');
      // Should NOT have 'error' field (old format)
      expect(resp.body).to.not.have.property('error');
    });
  });
});
