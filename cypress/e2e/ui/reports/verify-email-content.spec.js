/// <reference types="cypress" />

/**
 * The verification email carries a portal link and nothing from the family record:
 * no attachment, no custom-field values, whoever sends it.
 *
 * Seed family 17 (Cooper) has one person with an administrator-only custom field
 * ("Father of confession - Other": "Fr. Join Smith"). The message is read from Mailpit.
 */
const familyId = 17;
const recipient = "norman.cooper@example.com";
const adminOnlyValue = "Fr. Join Smith";

const mailpit = (path) => cy.request({ url: `${Cypress.mailpitUrl()}/api/v1/${path}` });

const messageIds = () => mailpit("messages?limit=200").then((resp) => resp.body.messages.map((message) => message.ID));

const newMessageTo = (address, knownIds, attemptsLeft = 20) =>
    mailpit("messages?limit=200").then((resp) => {
        const found = resp.body.messages.find(
            (message) => !knownIds.includes(message.ID) && message.To.some((to) => to.Address === address),
        );
        if (found) {
            return found;
        }
        expect(attemptsLeft, `a message to ${address} arrived`).to.be.greaterThan(0);
        cy.wait(500);
        return newMessageTo(address, knownIds, attemptsLeft - 1);
    });

const sendVerifyEmail = () => {
    cy.visit("people/verify");
    cy.get('#verifyEmailAllForm input[name="csrf_token"]')
        .invoke("val")
        .then((token) =>
            cy.request({
                method: "POST",
                url: "people/report/verify/email",
                form: true,
                body: { csrf_token: token, familyId },
                followRedirect: false,
            }),
        )
        .its("status")
        .should("eq", 302);
};

describe("Verification email - content", () => {
    ["church-admin", "office-staff", "staff-all-but-admin"].forEach((persona) => {
        it(`${persona} sends a portal link, no attachment and no custom-field value`, () => {
            cy.setupPersonaSession(persona);
            messageIds().then((knownIds) => {
                sendVerifyEmail();
                newMessageTo(recipient, knownIds).then((message) =>
                    mailpit(`message/${message.ID}`).then((resp) => {
                        expect(resp.body.Attachments, "attachments").to.have.length(0);
                        expect(resp.body.HTML).to.include("/portal/family");
                        expect(resp.body.HTML).to.not.include("/external/verify/");
                        expect(resp.body.HTML).to.not.include(adminOnlyValue);
                    }),
                );
            });
        });
    });
});
