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

      // Template should include Classification and FamilyRole columns
      expect(body).to.include('Classification');
      expect(body).to.include('FamilyRole');

      // Classification values in the template must be valid classifications (not family roles)
      // "Child" is a family role — must not appear in the Classification column
      const rows = body.split('\n').filter(Boolean);
      const headers = rows[0].split(',');
      const classIdx = headers.indexOf('Classification');
      const roleIdx = headers.indexOf('FamilyRole');

      expect(classIdx).to.be.greaterThan(-1, 'Classification column found');
      expect(roleIdx).to.be.greaterThan(-1, 'FamilyRole column found');

      rows.slice(1).forEach((row) => {
        const cols = row.split(',');
        const classification = cols[classIdx] ?? '';
        // A blank classification is fine (no-family rows); the value "Child" is not a classification
        if (classification !== '') {
          expect(classification.toLowerCase()).not.to.equal('child');
        }
      });
    });
  });
});
