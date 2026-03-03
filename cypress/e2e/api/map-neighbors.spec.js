describe('API — Map Neighbors', () => {
  it('returns nearby families for seeded familyId 1', () => {
    const familyId = 1; // present in cypress/data/seed.sql
    cy.request({
      method: 'GET',
      url: `/api/map/neighbors/${familyId}?maxNeighbors=5&maxDistance=50`,
      headers: {
        'x-api-key': Cypress.env('admin.api.key')
      }
    }).then((resp) => {
      expect(resp.status).to.equal(200);
      expect(resp.body).to.be.an('array');
      // light assertions: at least zero or more neighbors, all distances numeric and <= maxDistance
      resp.body.forEach((item) => {
        expect(item).to.have.property('id').that.is.a('number');
        expect(item).to.have.property('latitude').that.is.a('number');
        expect(item).to.have.property('longitude').that.is.a('number');
        expect(item).to.have.property('distance').that.is.a('number');
        expect(item.distance).to.be.at.most(50);
        expect(item.id).to.not.equal(familyId);
      });
    });
  });
});
