/// <reference types="cypress" />

/**
 * Member Portal (MP4, #9865) — Profile self-service.
 *
 * Seed persona: Lena Black (person 100, family 20, family role 2 = Spouse),
 * signed in with her email.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §5.2 (P11, P12).
 *   - a member reads and edits their own person record and nothing else
 *   - the birthday field is behind bPortalAllowBirthdayEdit
 *   - the photo shows on the profile page
 *   - a member has no password or two-factor page; staff sessions do
 *     (admin.portal-staff.spec.js)
 *
 * Note on the birthday switch: `bPortalAllowBirthdayEdit` is declared by the
 * Admin → Member Portal page (MP3, #9864), which is a sibling branch. Until
 * the two are merged this installation does not declare the item at all and
 * PortalSettings reports it off, so the test below asserts the *agreement*
 * between the setting and the form rather than hard-coding one of the two
 * states: it covers the "off" path today and the "on" path the day an
 * administrator turns it on.
 */
describe("Member Portal — Profile", () => {
    // Uploading Lena's photo writes Images/Person/100.png, which the test stack
    // bind-mounts from a tracked fixture, so its bytes are put back afterwards.
    const LENA_PHOTO = "cypress/data/images/people/100.png";
    let lenaPhoto = null;
    before(() =>
        cy.readFile(LENA_PHOTO, null).then((bytes) => {
            lenaPhoto = bytes;
        }),
    );
    after(() => cy.writeFile(LENA_PHOTO, lenaPhoto, null));

    const login = () => {
        cy.signInAsMember(100);
        cy.visit("/portal/");
    };

    /** The newest "Confirm your email address" link sent to `address` (member sign-in design §9). */
    const confirmLinkTo = (address) =>
        cy.task("mail:list", { limit: 100 }).then((result) => {
            const message = (result.body?.messages || []).find(
                (m) =>
                    (m.To || []).some((to) => (to.Address || "").toLowerCase() === address.toLowerCase()) &&
                    (m.Subject || "").includes("Confirm your email address"),
            );
            expect(message, `confirmation email to ${address}`).to.exist;
            return cy.task("mail:get", { id: message.ID }).then((full) => full.body.HTML.match(/\/portal\/email\/confirm\/[0-9a-f]{64}/)[0]);
        });

    /** Asks for a new email on the profile form and uses the link sent to it. */
    const changeEmailTo = (email) => {
        cy.visit("/portal/profile/edit");
        cy.get("#portal-email").clear().type(email);
        cy.get("#portal-profile-save").click();
        cy.get(".portal-flash-success", { timeout: 10000 }).should("contain", email);
        confirmLinkTo(email).then((path) => {
            cy.visit(path);
            cy.get("#portal-email-confirm").should("have.attr", "data-state", "confirm");
            cy.get("#portal-email-confirm-button").click();
            cy.get("#portal-email-confirm").should("have.attr", "data-state", "done");
        });
    };

    beforeEach(() => cy.expireSignInThrottle("email-change.person"));

    it("The portal navigation offers Profile and My Family", () => {
        login();
        cy.get("#portal-nav").within(() => {
            cy.contains("Profile").should("exist");
            cy.contains("My Family").should("exist");
        });
    });

    it("The home page shows real My Family and Profile cards", () => {
        login();
        cy.get("#portal-home-family-card").should("contain", "Black");
        cy.get("#portal-home-profile-card").should("contain", "Lena");
        cy.get("#portal-home-family-card").contains("Update your details").should("exist");
    });

    it("The profile page shows the member's own details and their avatar", () => {
        login();
        cy.visit("/portal/profile");
        cy.get("#portal-profile-details").should("exist");
        cy.contains("Lena").should("exist");
        // Either an uploaded photo or the initials stand-in — never a broken image.
        cy.get("#portal-profile-photo").should("exist");
        cy.get("#portal-profile-details [data-field=cellPhone]").should("not.be.empty");
    });

    it("The avatar is a real image once a photo is uploaded, on both profile pages", () => {
        // A member session may not read /api/person/{id}/photo — AuthMiddleware
        // confines it to /portal and /api/portal — so an avatar pointed there
        // decodes to nothing and shows as a broken image. naturalWidth is what
        // tells a loaded photo from a broken one.
        //
        // The file read from is somebody else's fixture and is only ever read;
        // Lena's own file is restored by the after() hook.
        login();
        cy.visit("/portal/profile/edit");
        cy.get("#portal-photo-input").selectFile("cypress/data/images/people/102.png", { force: true });

        cy.get(".portal-flash-success", { timeout: 10000 })
            .should("be.visible")
            .and("contain", "Your photo has been saved");

        // The preview is re-pointed at the URL the upload response returned.
        cy.get("img#portal-photo-preview").should("have.attr", "src").and("match", /\/api\/portal\/me\/photo/);
        cy.get("img#portal-photo-preview").should("have.prop", "naturalWidth").and("be.greaterThan", 0);

        // And the same photo loads on the profile page itself, reloaded fresh.
        cy.visit("/portal/profile");
        cy.get("img#portal-profile-photo").should("have.attr", "src").and("match", /\/api\/portal\/me\/photo/);
        cy.get("img#portal-profile-photo").should("have.prop", "naturalWidth").and("be.greaterThan", 0);
    });

    it("Editing the mobile phone saves at once; a new email changes only when its link is used", () => {
        const stamp = Date.now();
        const newPhone = `(206) 555-${String(stamp).slice(-4)}`;
        const newEmail = `lena.portal.${stamp}@example.com`;

        login();
        cy.visit("/portal/profile/edit");
        cy.get("#portal-email")
            .invoke("val")
            .then((originalEmail) => {
                cy.get("#portal-cellPhone").clear().type(newPhone);
                cy.get("#portal-email").clear().type(newEmail);
                cy.get("#portal-profile-save").click();

                cy.get(".portal-flash-success", { timeout: 10000 })
                    .should("be.visible")
                    .and("contain", `We sent a link to ${newEmail}`);

                // The form re-renders from the API response, not from what was typed.
                cy.get("#portal-cellPhone").should("have.value", newPhone);
                cy.get("#portal-email").should("have.value", originalEmail);
                cy.get("#portal-email-hint").should("contain", newEmail);

                cy.visit("/portal/profile");
                cy.get("#portal-profile-details [data-field=cellPhone]").should("contain", newPhone);
                cy.get("#portal-profile-details [data-field=email]").should("contain", originalEmail);

                confirmLinkTo(newEmail).then((path) => {
                    cy.visit(path);
                    cy.get("#portal-email-confirm-button").click();
                    cy.get("#portal-email-confirm").should("have.attr", "data-state", "done");
                });
                cy.visit("/portal/profile");
                cy.get("#portal-profile-details [data-field=email]").should("contain", newEmail);

                changeEmailTo(originalEmail);
            });
    });

    it("An empty required name is refused inline and nothing is saved", () => {
        login();
        cy.visit("/portal/profile/edit");

        cy.get("#portal-firstName").clear();
        cy.get("#portal-profile-save").click();

        cy.get('[data-error-for="firstName"]').should("be.visible").and("not.be.empty");
        cy.get("#portal-firstName").should("have.class", "is-invalid");

        cy.visit("/portal/profile");
        cy.get("#portal-profile-details [data-field=firstName]").should("contain", "Lena");
    });

    it("The birthday field is shown exactly when the portal setting allows it", () => {
        login();
        // Both pages read the same PortalSettings flag, so asserting that they
        // agree covers the "off" state today and the "on" state the day an
        // administrator turns bPortalAllowBirthdayEdit on.
        cy.visit("/portal/profile");
        cy.get("body").then(($body) => {
            const birthdayIsShown = $body.find("#portal-profile-details [data-field=birthday]").length > 0;
            cy.visit("/portal/profile/edit");
            if (birthdayIsShown) {
                cy.get("#portal-birthday").should("exist");
            } else {
                cy.get("#portal-birthday").should("not.exist");
            }
        });
    });

    it("A member session offers no password or two-factor page", () => {
        login();
        cy.visit("/portal/profile");
        cy.get("#portal-profile-details").should("exist");
        cy.get("#portal-change-password-link").should("not.exist");
        cy.get("#portal-two-factor-link").should("not.exist");
    });

    it("A long email address wraps after the @ or before a dot, inside its card (#10421)", () => {
        const longEmail = "lena.walker.portal.wrapping@members.example.org";
        const renderedLines = (element) => {
            const doc = element.ownerDocument;
            const walker = doc.createTreeWalker(element, NodeFilter.SHOW_TEXT);
            const lines = [];
            let lastTop = null;
            for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                for (let i = 0; i < node.length; i += 1) {
                    const range = doc.createRange();
                    range.setStart(node, i);
                    range.setEnd(node, i + 1);
                    const top = Math.round(range.getBoundingClientRect().top);
                    if (top !== lastTop) {
                        lines.push("");
                        lastTop = top;
                    }
                    lines[lines.length - 1] += node.data[i];
                }
            }
            return lines;
        };

        cy.viewport(375, 812);
        login();
        cy.visit("/portal/profile/edit");
        cy.get("#portal-email")
            .invoke("val")
            .then((originalEmail) => {
                changeEmailTo(longEmail);

                for (const [path, list] of [
                    ["/portal/", "#portal-home-profile-details"],
                    ["/portal/profile", "#portal-profile-details"],
                ]) {
                    cy.visit(path);
                    cy.get(`${list} [data-field=email]`).should(($email) => {
                        const element = $email[0];
                        const lines = renderedLines(element);
                        expect(lines.join(""), "rendered address").to.equal(longEmail);
                        expect(lines.length, "lines at phone width").to.be.greaterThan(1);
                        lines.slice(1).forEach((line, index) => {
                            expect(
                                lines[index].endsWith("@") || line.startsWith("."),
                                `break between "${lines[index]}" and "${line}"`
                            ).to.equal(true);
                        });
                        expect(element.scrollWidth, "no overflow").to.be.at.most(element.clientWidth);
                    });
                }

                changeEmailTo(originalEmail);
            });
    });
});
