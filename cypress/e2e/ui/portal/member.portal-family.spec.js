/// <reference types="cypress" />

/**
 * Member Portal (MP4, #9865) — My Family self-service.
 *
 * Two seeded personas carry the adult-of-the-family rule:
 *   - Lena Black (user 100, person 100, family 20) has family role 2 (Spouse),
 *     which `sDirRoleSpouse` names, so she is one of the family's adults and
 *     may change its address.
 *   - limited.user (user 4, Darren Campbell, person 4, family 1) has family
 *     role 4 (Other Relative), which is neither `sDirRoleHead` nor
 *     `sDirRoleSpouse`, so the family page is read-only for him.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §5.2.
 *   - adults edit the family address; everybody else views it
 *   - "Confirm your family details" writes the same `verify` note the emailed
 *     token link writes, so staff review is unchanged
 *   - "Add a family member" creates a pending self-registration entry, never a
 *     live member
 */
describe("Member Portal — My Family", () => {
    const adultUser = "lena.black.editself.notes@example.com";
    const nonAdultUser = "limited.user";
    const password = "changeme";

    const loginAs = (username) => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(username);
        cy.get("input[name=Password]").type(password + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/portal");
    };

    // Local helper — NOT a cy.* command. Needed because the assertions at the
    // end of a test use the admin API, and cy.request() with an API key kills
    // the browser's PHP session.
    const freshAdminLogin = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.env("admin.username"));
        cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
        cy.url().should("not.include", "/session/begin");
    };

    describe("An adult of the family", () => {
        it("sees the address, the members list and their own highlighted row", () => {
            loginAs(adultUser);
            cy.visit("/portal/family");

            cy.get("#portal-family-address").should("contain", "Avondale");
            cy.get("#portal-family-members").should("contain", "Amanda");
            cy.get("#portal-family-members").should("contain", "Samantha");
            cy.get('[data-person-row="100"]').should("have.class", "is-self");
            cy.get('[data-person-row="100"]').should("contain", "You");
            cy.get("#portal-family-edit-link").should("exist");
            cy.get("#portal-family-readonly-note").should("not.exist");
        });

        it("shows a family member's photo as a real image, not a broken one", () => {
            // Samantha (person 102) has a seeded photo under
            // cypress/data/images/people. Her avatar used to point at
            // /api/person/102/photo, which a member session is refused, so the
            // row rendered a broken image. naturalWidth tells the two apart.
            loginAs(adultUser);
            cy.visit("/portal/family");

            cy.get('[data-person-row="102"] img.portal-avatar')
                .should("have.attr", "src")
                .and("match", /\/api\/portal\/family\/members\/102\/photo/);
            cy.get('[data-person-row="102"] img.portal-avatar')
                .should("have.prop", "naturalWidth")
                .and("be.greaterThan", 0);

            // A member with no photo still gets initials rather than a broken image.
            cy.get('[data-person-row="103"] .portal-avatar-initials').should("exist");
        });

        it("edits the family address, sees the toast, and the change survives a reload", () => {
            const stamp = String(Date.now()).slice(-5);
            const newAddress = `${stamp} Avondale Ave`;
            const newCity = `Shiloh ${stamp}`;

            loginAs(adultUser);
            cy.visit("/portal/family/edit");

            cy.get("#portal-address1").clear().type(newAddress);
            cy.get("#portal-city").clear().type(newCity);
            cy.get("#portal-family-save").click();

            cy.get(".portal-flash-success", { timeout: 10000 })
                .should("be.visible")
                .and("contain", "Your family details have been saved");

            cy.visit("/portal/family");
            cy.get("#portal-family-address [data-field=address1]").should("contain", newAddress);
            cy.get("#portal-family-address [data-field=city]").should("contain", newCity);
        });

        it("confirms the family details, and the verify note reaches the staff dashboard", () => {
            const comment = `Cypress portal confirm ${Date.now()}`;

            loginAs(adultUser);
            cy.visit("/portal/family");
            cy.get("#portal-family-confirm-link").click();

            cy.url({ timeout: 10000 }).should("include", "/portal/family/confirm");
            cy.get("#portal-confirm-comment-field").should("not.be.visible");
            cy.get("#portal-confirm-change-needed").click();
            cy.get("#portal-confirm-comment").should("be.visible").type(comment);
            cy.get("#portal-confirm-submit").click();

            cy.get(".portal-flash-success", { timeout: 10000 })
                .should("be.visible")
                .and("contain", "The church office has your answer");

            // Teardown-phase assertion: the same list the People → Verify
            // dashboard reads, which selects on EnteredBy = SELF_VERIFY.
            cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((response) => {
                const texts = (response.body.families || []).map((note) => note.Text);
                expect(texts.join("\n")).to.contain(comment);
            });
        });

        it("adds a family member, and the entry waits on People → Self Registrations", () => {
            const stamp = String(Date.now()).slice(-6);
            const firstName = `Portal${stamp}`;

            loginAs(adultUser);
            cy.visit("/portal/family");

            cy.get("#portal-add-member-open").click();
            cy.get("#portal-add-member-dialog").should("be.visible");
            cy.get("#portal-add-member-dialog").should("contain", "church office for review");
            cy.get("#portal-new-firstName").type(firstName);
            cy.get("#portal-new-lastName").clear().type("Black");
            cy.get("#portal-add-member-submit").click();

            cy.get(".portal-flash-success", { timeout: 10000 })
                .should("be.visible")
                .and("contain", "The church office will review this");

            // The member is pending, not live: it appears on the staff review
            // page, which is the whole point of Person::SELF_REGISTER.
            freshAdminLogin();
            cy.visit("/people/self-register");
            cy.get("#selfRegistrations", { timeout: 15000 }).should("contain", firstName);
            cy.get("#selfRegistrations").should("contain", "Family Member");
        });
    });

    describe("A family member who is not an adult of the family", () => {
        it("sees the family read-only, with no edit control", () => {
            loginAs(nonAdultUser);
            cy.visit("/portal/family");

            cy.get("#portal-family-address").should("exist");
            cy.get("#portal-family-edit-link").should("not.exist");
            cy.get("#portal-add-member-open").should("not.exist");
            cy.get("#portal-family-readonly-note")
                .should("be.visible")
                .and("contain", "Only an adult of your family");
        });

        it("is refused the edit page outright", () => {
            loginAs(nonAdultUser);
            cy.visit("/portal/family/edit", { failOnStatusCode: false });

            cy.get(".portal-error-403").should("exist");
            cy.get("#portal-family-form").should("not.exist");
        });
    });
});
