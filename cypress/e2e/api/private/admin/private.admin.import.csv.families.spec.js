/// <reference types="cypress" />

describe('API Private Admin Import CSV Families', () => {
  it('GET /admin/api/import/csv/families returns CSV attachment for admins', () => {
    cy.makePrivateAdminAPICall('GET', '/admin/api/import/csv/families', null, 200).then((resp) => {
      // Assert headers
      expect(resp.headers['content-type']).to.match(/text\/?csv/);
      expect(resp.headers['content-disposition']).to.match(/attachment;\s*filename=\"?csv-families-template.csv\"?/);

      // Body should contain the FamilyID header and sample rows
      const body = resp.body;
      // cy.request returns body as string for text responses
      expect(body).to.be.a('string');
      expect(body).to.include('FamilyID');
      expect(body).to.include('Smith');
      expect(body).to.include('Johnson');
    });
  });
});
