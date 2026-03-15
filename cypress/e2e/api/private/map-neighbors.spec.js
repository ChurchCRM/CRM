/// <reference types="cypress" />

describe('API — Map Neighbors', () => {
  it('returns nearby families for seeded familyId 1', () => {
    const familyId = 1; // present in cypress/data/seed.sql
    cy.makePrivateAdminAPICall(
      'GET',
      `/api/map/neighbors/${familyId}?maxNeighbors=5&maxDistance=50`,
      null,
      200
    ).then((resp) => {
      expect(resp.body).to.be.an('array');
      resp.body.forEach((item) => {
        expect(item).to.have.property('id').that.is.a('number');
        expect(item).to.have.property('name').that.is.a('string');
        expect(item).to.have.property('address').that.is.a('string');
        expect(item).to.have.property('latitude').that.is.a('number');
        expect(item).to.have.property('longitude').that.is.a('number');
        expect(item).to.have.property('distance').that.is.a('number');
        expect(item).to.have.property('distanceText').that.is.a('string');
        expect(item).to.have.property('bearing').that.is.a('string');
        expect(item).to.have.property('profileUrl').that.is.a('string');
        expect(item.distance).to.be.at.most(50);
        expect(item.id).to.not.equal(familyId);
      });
    });
  });
});
