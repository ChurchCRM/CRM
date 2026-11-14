/// <reference types="cypress" />

import {
    cleanupDeletionFixture,
    countPersonMail,
    createDeletionProperty,
    prepareDeletionRelationships,
    readDeletionRelationships,
    recordOnboardingParticipation,
    recordUnsentActivity,
    recordVolunteerNotification,
} from "../../../../support/person-deletion-fixtures";

const createPaymentForFamily = (familyId, amount, groupKeys) =>
    cy.makePrivateAdminAPICall("GET", "/api/fiscalyear", null, 200)
        .then((fiscalYear) => cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
            type: "Payment",
            iMethod: "CASH",
            Date: new Date().toISOString().slice(0, 10),
            FamilyID: String(familyId),
            FYID: fiscalYear.body.fyId,
            FundSplit: JSON.stringify([{ FundID: "1", Amount: amount, NonDeductible: 0, Comment: "deletion guard test" }]),
        }, 200))
        .then((response) => {
            const groupKey = response.body.payment.GroupKey;
            groupKeys.push(groupKey);
            return groupKey;
        });

describe("Person deletion preserves recorded history", () => {
    let familyId;
    let personId;
    let additionalPersonId;
    let propertyId;
    let familyNoteIds = [];
    let paymentGroupKeys = [];
    let firstName;

    const createFixture = () => {
        const suffix = `${Date.now()}${Cypress._.random(1000, 9999)}`;
        firstName = `Person${suffix}`;

        return cy.setupAdminSession()
            .then(() => cy.createPersonWithBirthday({
                name: firstName,
                lastName: "HistoryTest",
                email: `${firstName.toLowerCase()}@example.test`,
                createFamily: true,
            }))
            .then((createdPersonId) => {
                personId = createdPersonId;
                return cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200);
            })
            .then((response) => {
                familyId = Number(response.body.FamId);
                return createDeletionProperty(personId);
            })
            .then((createdPropertyId) => {
                propertyId = createdPropertyId;
            });
    };

    const removeFixture = () => {
        if (!personId) return;
        let cleanup = cy.wrap(null, { log: false });
        familyNoteIds.forEach((noteId) => {
            cleanup = cleanup.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, [200, 404]));
        });
        paymentGroupKeys.forEach((groupKey) => {
            cleanup = cleanup.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${groupKey}`, null, [200, 404]));
        });
        return cleanup
            .then(() => cleanupDeletionFixture(personId, propertyId))
            .then(() => additionalPersonId ? cy.makePrivateAdminAPICall("DELETE", `/api/person/${additionalPersonId}`, null, [200, 404]) : undefined)
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, [200, 404]))
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}`, null, [200, 404]))
            .then(() => {
                personId = undefined;
                familyId = undefined;
                propertyId = undefined;
                additionalPersonId = undefined;
                familyNoteIds = [];
                paymentGroupKeys = [];
            });
    };

    beforeEach(() => createFixture());
    afterEach(() => removeFixture());

    it("blocks attendance deletion before cleanup and keeps the attendance report resolvable", () => {
        let originalRelationships;
        return cy.makePrivateAdminAPICall("POST", "/api/events/1/checkin", { personId }, 200)
            .then(() => prepareDeletionRelationships(personId, propertyId))
            .then((snapshot) => {
                originalRelationships = snapshot;
            })
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 403))
            .then((response) => {
                expect(response.body.message).to.include("Deactivate the person instead");
                expect(response.body.message).to.include("attendance");
            })
            .then(() => readDeletionRelationships(personId))
            .then((snapshot) => expect(snapshot).to.deep.equal(originalRelationships))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/note`, { text: "Preserve family note" }, 201))
            .then((response) => {
                familyNoteIds.push(Number(response.body.note.id));
                return cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}?deleteMembers=true`, null, 403);
            })
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/notes`, null, 200))
            .then((response) => expect(response.body.notes.map((note) => note.text)).to.include("Preserve family note"))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/attendance/person/${personId}`, null, 200))
            .then((response) => expect(response.body.records).to.have.length(1));
    });

    it("blocks substantive notes without exposing their text", () => {
        const privateText = `private note ${firstName} must not leak`;
        return cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/note`, { text: privateText, private: true }, 201)
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 403))
            .then((response) => {
                expect(response.body.message).to.include("notes");
                expect(response.body.message).to.not.include(privateText);
            })
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/notes`, null, 200))
            .then((response) => expect(response.body.notes.map((note) => note.text)).to.include(privateText));
    });

    it("blocks sole-member financial attribution and family member cascades", () => {
        return createPaymentForFamily(familyId, 25, paymentGroupKeys)
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 403))
            .then((response) => {
                expect(response.body.message).to.include("financial history");
                expect(response.body.message).to.not.include("25");
            })
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}?deleteMembers=true`, null, 403))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/payments/family/${familyId}/list?fyid=0`, null, 200))
            .then((response) => expect(response.body.data).to.have.length(1));
    });

    it("allows one member to leave while the family's financial attribution remains", () => {
        const secondFirstName = `${firstName}Other`;
        return cy.createPersonWithBirthday({ name: secondFirstName, lastName: "HistoryTest", familyId, familyRole: 3 })
            .then((createdPersonId) => {
                additionalPersonId = createdPersonId;
                return createPaymentForFamily(familyId, 25, paymentGroupKeys);
            })
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 404))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/payments/family/${familyId}/list?fyid=0`, null, 200))
            .then((response) => expect(response.body.data).to.have.length(1));
    });

    it("blocks persisted volunteer and communication activity", () => {
        return recordVolunteerNotification(personId)
            .then(() => cy.sendComposerEmailNow({
                personIds: [personId],
                subject: `History test ${personId}`,
                body: "Recorded email history",
            }))
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 403))
            .then((response) => {
                expect(response.body.message).to.include("communication history");
                expect(response.body.message).to.include("volunteer service history");
            })
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200));
    });

    it("allows deletion when the only mail and alerts were never sent, and cancels the queued mail", () => {
        return recordUnsentActivity(personId)
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 404))
            .then(() => countPersonMail(personId))
            .then((count) => expect(count).to.equal(0));
    });

    it("blocks a family cascade when the only history is a family note written by someone outside it", () => {
        return cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/note`, { text: "Outside family note" }, 201)
            .then((response) => {
                familyNoteIds.push(Number(response.body.note.id));
                return cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}?deleteMembers=true`, null, 403);
            })
            .then((response) => expect(response.body.message).to.include("Deactivate the family instead"))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/notes`, null, 200))
            .then((response) => expect(response.body.notes.map((note) => note.text)).to.include("Outside family note"));
    });

    it("blocks recorded onboarding and fundraiser participation", () => {
        return recordOnboardingParticipation(personId)
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 403))
            .then((response) => expect(response.body.message).to.include("recorded activities"))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200));
    });

    it("allows a person with no protected history to be deleted", () => {
        return cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 200)
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 404));
    });

    it("allows deletion when the only notes are automatic create and edit timeline entries", () => {
        return cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/activate/false`, null, 200)
            .then(() => cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/activate/true`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, 200))
            .then(() => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 404));
    });
});
