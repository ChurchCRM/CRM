/// <reference types="cypress" />

/**
 * The person API and the family page show a user every custom field their
 * FieldSecurity allows, and no more.
 *
 * Seed person 2 and family 1 hold a value in each security class. Person fields:
 * c2 administrators, c5/c6/c12 Finance, c7 Notes, c9 Manage Groups, c10 Edit Records,
 * c11 Delete Records, c13 Add Records, c8 open to every user. Family fields:
 * "Housing Support Level" administrators, "Envelope Audit Note" Finance,
 * "Home Visit Notes" Notes, "Family Since Year" open to every user.
 */
describe("Custom field visibility by FieldSecurity", () => {
    const personId = 2;
    const familyId = 1;

    const personFieldIds = () =>
        cy.request(`/api/person/${personId}`).then((response) => response.body.singlePersonCustom.map((item) => item.id).sort());

    const familyPageText = () => cy.request(`/people/family/${familyId}`).then((response) => response.body);

    const cases = [
        {
            persona: "church-admin",
            personVisible: ["c2", "c5", "c6", "c7", "c8", "c9", "c10", "c11", "c12", "c13"],
            personHidden: [],
            familyVisible: ["Housing Support Level", "Envelope Audit Note", "Home Visit Notes", "Family Since Year"],
            familyHidden: [],
        },
        {
            persona: "staff-all-but-admin",
            personVisible: ["c5", "c6", "c7", "c8", "c9", "c10", "c11", "c12", "c13"],
            personHidden: ["c2"],
            familyVisible: ["Envelope Audit Note", "Home Visit Notes", "Family Since Year"],
            familyHidden: ["Housing Support Level"],
        },
        {
            persona: "office-staff",
            personVisible: ["c8", "c10", "c13"],
            personHidden: ["c2", "c5", "c6", "c7", "c9", "c11", "c12"],
            familyVisible: ["Family Since Year"],
            familyHidden: ["Housing Support Level", "Envelope Audit Note", "Home Visit Notes"],
        },
    ];

    cases.forEach(({ persona, personVisible, personHidden, familyVisible, familyHidden }) => {
        describe(persona, () => {
            beforeEach(() => cy.setupPersonaSession(persona));

            it("sees the person custom fields its security allows", () => {
                personFieldIds().then((ids) => {
                    personVisible.forEach((id) => expect(ids, id).to.include(id));
                    personHidden.forEach((id) => expect(ids, id).to.not.include(id));
                });
            });

            it("sees the family custom fields its security allows", () => {
                familyPageText().then((html) => {
                    familyVisible.forEach((label) => expect(html, label).to.include(label));
                    familyHidden.forEach((label) => expect(html, label).to.not.include(label));
                });
            });
        });
    });
});
