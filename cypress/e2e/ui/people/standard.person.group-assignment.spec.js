/// <reference types="cypress" />

const personEditorPath = "PersonEditor.php";
const personViewPath = "people/view/";

const angelsClassGroupId = 1;
const churchBoardGroupId = 9;
const boysScoutsGroupId = 7;

describe("PersonEditor: Group Assignment", () => {
    const uniqueSeed = Date.now().toString();
    const createdPersonIds = [];

    after(() => {
        cy.cleanupPeople(createdPersonIds);
    });

    beforeEach(() => {
        cy.freshAdminFormLogin();
    });

    function createPerson(firstName, groupNames = []) {
        cy.visit(personEditorPath);

        cy.get("#groupIds")
            .should("exist")
            .should("have.class", "tomselected");

        groupNames.forEach((groupName) => {
            // Open TomSelect dropdown directly
            cy.get("#groupIds").then(($el) => {
                $el[0].tomselect.open();
            });

            // Select the requested group
            cy.get("#groupIds-ts-dropdown")
                .contains(".option", groupName)
                .should("be.visible")
                .click();
        });

        cy.get("#FirstName").type(firstName);
        cy.get("#LastName").type("GroupAssignment");

        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should("contain", personViewPath);

        return cy.trackPersonFromUrl(createdPersonIds);
    }

    function verifyMembership(groupId, personId, expected) {
        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${groupId}/members`,
            null,
            200,
        ).then((resp) => {
            const members =
                resp.body?.Person2group2roleP2g2rs || [];

            const isMember = members.some(
                (member) => Number(member.PersonId) === personId,
            );

            expect(
                isMember,
                `Person ${personId} membership in group ${groupId}`,
            ).to.equal(expected);
        });
    }

    it("should assign one selected group when creating a person", () => {
        const firstName = `SingleGroup${uniqueSeed}`;

        createPerson(firstName, ["Angels class"]).then((personId) => {
            verifyMembership(angelsClassGroupId, personId, true);
            verifyMembership(churchBoardGroupId, personId, false);
            verifyMembership(boysScoutsGroupId, personId, false);
        });
    });

    it("should assign multiple selected groups when creating a person", () => {
        const firstName = `MultipleGroups${uniqueSeed}`;

        createPerson(firstName, ["Angels class", "Church Board"]).then((personId) => {
            verifyMembership(angelsClassGroupId, personId, true);
            verifyMembership(churchBoardGroupId, personId, true);
            verifyMembership(boysScoutsGroupId, personId, false);
        });
    });

    it("should not assign any group when no group is selected", () => {
        const firstName = `NoGroup${uniqueSeed}`;

        createPerson(firstName).then((personId) => {
            verifyMembership(angelsClassGroupId, personId, false);
            verifyMembership(churchBoardGroupId, personId, false);
            verifyMembership(boysScoutsGroupId, personId, false);
        });
    });

    it("should hide group assignment from staff without Manage Groups permission", () => {
        const firstName = `OfficeStaff${uniqueSeed}`;

        cy.setupPersonaSession("office-staff");
        cy.visit(personEditorPath);

        cy.get("#groupIds").should("not.exist");
        cy.get("#FirstName").type(firstName);
        cy.get("#LastName").type("GroupAssignment");
        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should("contain", personViewPath);

        cy.trackPersonFromUrl(createdPersonIds).then((personId) => {
            verifyMembership(angelsClassGroupId, personId, false);
            verifyMembership(churchBoardGroupId, personId, false);
            verifyMembership(boysScoutsGroupId, personId, false);
        });
    });

    it("should retry group assignment without creating a duplicate person", () => {
        const firstName = `RetryGroup${uniqueSeed}`;

        createPerson(firstName).then((personId) => {
            cy.visit(
                `${personEditorPath}?PersonID=${personId}&GroupAssignmentRetry=1&GroupIds=${angelsClassGroupId}`,
            );

            cy.get("#groupAssignmentRetryForm").should("exist");
            cy.contains(".card-title", "Retry Group Assignment").should("be.visible");

            cy.get('button[name="GroupAssignmentRetrySubmit"]')
                .should("be.visible")
                .click();

            cy.location("pathname").should(
                "match",
                new RegExp(`/people/view/${personId}$`),
            );

            verifyMembership(angelsClassGroupId, personId, true);

            cy.makePrivateAdminAPICall(
                "GET",
                `/api/person/${personId}`,
                null,
                200,
            ).then(({ body }) => {
                expect(body.FirstName).to.equal(firstName);
                expect(body.LastName).to.equal("GroupAssignment");
            });

            cy.request(`/api/search/${firstName}`).then(({ body }) => {
                const personsGroup = body.find((group) =>
                    group.text.startsWith("Persons"),
                );

                const matchingPeople = personsGroup?.children.filter((person) =>
                    person.text.startsWith(`${firstName} `),
                ) || [];

                expect(matchingPeople).to.have.length(1);
                expect(matchingPeople[0].uri).to.include(
                    `/people/view/${personId}`,
                );
            });
        });
    });

});
