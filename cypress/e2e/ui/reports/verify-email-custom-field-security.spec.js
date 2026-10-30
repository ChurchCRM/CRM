/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * The verification email's PDF only lists person custom fields the sender may see.
 *
 * Seed family 17 (Cooper) has one person with an administrator-only custom field
 * ("Father of confession - Other": "Fr. Join Smith") and every member has the open field
 * "Highest Degree Received". The email goes to the family's members, so the PDF is read
 * from the Mailpit copy of the message sent to norman.cooper@example.com.
 */
const familyId = 17;
const recipient = "norman.cooper@example.com";
const adminOnlyLabel = "Father of confession";
const adminOnlyValue = "Fr. Join Smith";
const openFieldLabel = "Highest Degree Received";

const mailpit = (path, options = {}) => cy.request({ url: `${Cypress.mailpitUrl()}/api/v1/${path}`, ...options });

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

const attachedPdfText = (message) =>
    mailpit(`message/${message.ID}`).then((resp) => {
        const pdf = resp.body.Attachments.find((attachment) => attachment.ContentType === "application/pdf");
        expect(pdf, "PDF attachment").to.exist;
        return mailpit(`message/${message.ID}/part/${pdf.PartID}`, { encoding: "binary" })
            .then((part) => pdfText(part.body))
            .then((lines) => lines.join(" "));
    });

const emailedPdfText = () =>
    messageIds().then((knownIds) => {
        sendVerifyEmail();
        return newMessageTo(recipient, knownIds).then(attachedPdfText);
    });

describe("Verification email - custom field security", () => {
    it("church-admin sends the administrator-only value and the open field", () => {
        cy.setupPersonaSession("church-admin");
        emailedPdfText().then((text) => {
            expect(text).to.include(adminOnlyLabel);
            expect(text).to.include(adminOnlyValue);
            expect(text).to.include(openFieldLabel);
        });
    });

    ["office-staff", "staff-all-but-admin"].forEach((persona) => {
        it(`${persona} sends the open field but not the administrator-only field`, () => {
            cy.setupPersonaSession(persona);
            emailedPdfText().then((text) => {
                expect(text).to.include(openFieldLabel);
                expect(text).to.not.include(adminOnlyLabel);
                expect(text).to.not.include(adminOnlyValue);
            });
        });
    });
});
