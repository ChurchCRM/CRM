/// <reference types="cypress" />

describe("API Finance Payments - giving recorded per person", () => {
    const MEMBER_OF_FAMILY_1 = 2;
    const PERSON_WITHOUT_FAMILY = 3;
    const createdKeys = [];

    const pledgePayload = (overrides = {}) => ({
        type: "Pledge",
        iMethod: "CASH",
        Date: "2025-10-25",
        FYID: 29,
        schedule: "Once",
        tScanString: "",
        FundSplit: JSON.stringify([{ FundID: "1", Amount: 40.0, NonDeductible: 0, Comment: "" }]),
        ...overrides,
    });

    const create = (overrides) =>
        cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", pledgePayload(overrides), 200).then((resp) => {
            createdKeys.push(resp.body.groupKey);
            return resp.body.groupKey;
        });

    const getGroup = (groupKey) =>
        cy
            .makePrivateAdminAPICall("GET", `/api/payments/pledges/${encodeURIComponent(groupKey)}`, null, 200)
            .its("body");

    after(() => {
        createdKeys.forEach((key) =>
            cy.makePrivateAdminAPICall("DELETE", `/api/payments/${encodeURIComponent(key)}`, null, [200, 404]),
        );
    });

    it("books a person's gift to that person and their own family", () => {
        create({ PersonID: MEMBER_OF_FAMILY_1 }).then((groupKey) => {
            getGroup(groupKey).then((group) => {
                expect(group.personId).to.equal(MEMBER_OF_FAMILY_1);
                expect(group.personName).to.equal("Mathew Campbell");
                expect(group.familyId).to.equal(1);
            });
        });
    });

    it("replaces a submitted family with the person's family", () => {
        create({ PersonID: PERSON_WITHOUT_FAMILY, FamilyID: 1 }).then((groupKey) => {
            getGroup(groupKey).then((group) => {
                expect(group.personId).to.equal(PERSON_WITHOUT_FAMILY);
                expect(group.familyId).to.equal(0);
                expect(group.familyName).to.equal("");
            });
        });
    });

    it("keeps family-wide records without a person", () => {
        create({ FamilyID: 1 }).then((groupKey) => {
            getGroup(groupKey).then((group) => {
                expect(group.personId).to.equal(null);
                expect(group.personName).to.equal("");
                expect(group.familyId).to.equal(1);
            });
        });
    });

    it("rejects an unknown person", () => {
        cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", pledgePayload({ PersonID: 999999 }), 400)
            .its("body.message")
            .should("equal", "Person not found");
    });

    it("lists only the person's own rows, and names the person in the family list", () => {
        create({ PersonID: MEMBER_OF_FAMILY_1 }).then((personKey) => {
            create({ FamilyID: 1 }).then((familyKey) => {
                cy.makePrivateAdminAPICall("GET", `/api/payments/person/${MEMBER_OF_FAMILY_1}/list?fyid=0`, null, 200).then(
                    (resp) => {
                        const keys = resp.body.data.map((row) => row.GroupKey);
                        expect(keys).to.include(personKey);
                        expect(keys).to.not.include(familyKey);
                        resp.body.data.forEach((row) => expect(row.PersonId).to.equal(MEMBER_OF_FAMILY_1));
                    },
                );
                cy.makePrivateAdminAPICall("GET", "/api/payments/family/1/list?fyid=0", null, 200).then((resp) => {
                    const personRow = resp.body.data.find((row) => row.GroupKey === personKey);
                    const familyRow = resp.body.data.find((row) => row.GroupKey === familyKey);
                    expect(personRow.Person).to.equal("Mathew Campbell");
                    expect(familyRow.Person).to.equal("");
                });
            });
        });
    });

    it("moves a record to another person on update, and back to the family", () => {
        create({ PersonID: MEMBER_OF_FAMILY_1 }).then((groupKey) => {
            const url = `/api/payments/${encodeURIComponent(groupKey)}`;
            cy.makePrivateAdminAPICall("PUT", url, pledgePayload({ PersonID: PERSON_WITHOUT_FAMILY }), 200);
            getGroup(groupKey).its("personId").should("equal", PERSON_WITHOUT_FAMILY);
            cy.makePrivateAdminAPICall("PUT", url, pledgePayload({ FamilyID: 1 }), 200);
            getGroup(groupKey).then((group) => {
                expect(group.personId).to.equal(null);
                expect(group.familyId).to.equal(1);
            });
        });
    });

    it("refuses the person list without the Finance role", () => {
        cy.makePrivateNoFinanceAPICall("GET", `/api/payments/person/${MEMBER_OF_FAMILY_1}/list`, null, 403);
    });

    it("returns each person's family from the person search", () => {
        cy.makePrivateAdminAPICall("GET", "/api/persons/search/Mathew", null, 200).then((resp) => {
            const mathew = resp.body.find((row) => row.objid === MEMBER_OF_FAMILY_1);
            expect(mathew.familyId).to.equal(1);
            expect(mathew.familyName).to.be.a("string").and.not.be.empty;
        });
    });
});
