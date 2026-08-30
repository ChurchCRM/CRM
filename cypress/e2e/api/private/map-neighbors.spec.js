/// <reference types="cypress" />

describe('API — Map Neighbors', () => {
  it('returns the origin family and nearby families for seeded familyId 1', () => {
    const familyId = 1; // present in cypress/data/seed.sql
    cy.makePrivateAdminAPICall(
      'GET',
      `/api/map/neighbors/${familyId}?maxNeighbors=5&maxDistance=50`,
      null,
      200
    ).then((resp) => {
      expect(resp.body).to.be.an('object');

      expect(resp.body).to.have.property('origin');
      expect(resp.body.origin).to.have.property('id', familyId);
      expect(resp.body.origin).to.have.property('name').that.is.a('string');
      expect(resp.body.origin).to.have.property('address').that.is.a('string');
      expect(resp.body.origin).to.have.property('latitude').that.is.a('number');
      expect(resp.body.origin).to.have.property('longitude').that.is.a('number');

      expect(resp.body).to.have.property('neighbors').that.is.an('array');
      resp.body.neighbors.forEach((item) => {
        expect(item).to.have.property('id').that.is.a('number');
        expect(item).to.have.property('name').that.is.a('string');
        expect(item).to.have.property('address').that.is.a('string');
        expect(item).to.have.property('latitude').that.is.a('number');
        expect(item).to.have.property('longitude').that.is.a('number');
        expect(item).to.have.property('distance').that.is.a('number');
        expect(item).to.have.property('distanceText').that.is.a('string');
        expect(item).to.have.property('bearing').that.is.a('string');
        expect(item).to.have.property('profileUrl').that.is.a('string');
        expect(item).to.have.property('directionsUrl').that.is.a('string');
        expect(item).to.have.property('people').that.is.an('array');
        expect(item.people.length).to.be.at.least(1);
        item.people.forEach((person) => {
          expect(person).to.have.property('id').that.is.a('number');
          expect(person).to.have.property('name').that.is.a('string');
          expect(person).to.have.property('classificationId').that.is.a('number');
        });
        expect(item.distance).to.be.at.most(50);
        expect(item.id).to.not.equal(familyId);
      });
    });
  });

  it('filters listed people by classificationIds and drops families with no matches', () => {
    const familyId = 1;
    cy.makePrivateAdminAPICall(
      'GET',
      `/api/map/neighbors/${familyId}?maxNeighbors=5&maxDistance=50&classificationIds=999999`,
      null,
      200
    ).then((resp) => {
      expect(resp.body).to.be.an('object');
      // No family member should ever have classification 999999
      expect(resp.body.neighbors).to.have.length(0);
    });
  });
});
