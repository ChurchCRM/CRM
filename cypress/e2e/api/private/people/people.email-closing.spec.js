/// <reference types="cypress" />

/**
 * The closing of every ChurchCRM email ("<sConfirmSincerely>,<br> <sConfirmSigner>")
 * prints only the parts that are set, and nothing when both are blank. Read from the
 * family verification email, which uses the shared closing.
 */

const FAMILY_ID = 1;
const SUBJECT = 'subject:"Campbell: Please verify"';
const search = () => `${Cypress.mailpitUrl()}/api/v1/search?query=${encodeURIComponent(SUBJECT)}`;
const saved = {};

const setConfig = (name, value) =>
    cy.makePrivateAdminAPICall("POST", `admin/api/system/config/${name}`, { value }, 200);

// The closing paragraph's HTML, or null when the email has no closing row.
const closingFor = (sincerely, signer) => {
    setConfig("sConfirmSincerely", sincerely);
    setConfig("sConfirmSigner", signer);
    cy.request({ method: "DELETE", url: search() });
    cy.makePrivateAdminAPICall("POST", `/api/family/${FAMILY_ID}/verify`, null, 200);
    return cy
        .request(`${search()}&limit=1`)
        .then((found) => {
            expect(found.body.messages).to.have.length(1);
            return cy.request(`${Cypress.mailpitUrl()}/api/v1/message/${found.body.messages[0].ID}`);
        })
        .then((message) => {
            const doc = new DOMParser().parseFromString(message.body.HTML, "text/html");
            const cell = [...doc.querySelectorAll("td")].find((td) =>
                (td.getAttribute("style") || "").includes("border-bottom: 3px solid #d4dadf"),
            );
            return cell ? cell.querySelector("p:last-of-type").innerHTML.replace(/\s+/g, " ").trim() : null;
        });
};

before(() => {
    cy.getSystemConfig("sConfirmSincerely").then((value) => (saved.sincerely = value));
    cy.getSystemConfig("sConfirmSigner").then((value) => (saved.signer = value));
});

after(() => {
    cy.restoreSystemConfig("sConfirmSincerely", saved.sincerely);
    cy.restoreSystemConfig("sConfirmSigner", saved.signer);
});

describe("Email closing", () => {
    it("prints the word and the signer on two lines when both are set", () => {
        closingFor("Blessings", "Elder Joe Smith").should("eq", "Blessings,<br> Elder Joe Smith");
    });

    it("prints only the signer, with no comma, when the word is blank", () => {
        closingFor("", "Elder Joe Smith").should("eq", "Elder Joe Smith");
    });

    it("prints only the word when the signer is blank", () => {
        closingFor("Blessings", "").should("eq", "Blessings,");
    });

    it("prints no closing when both are blank or only spaces", () => {
        closingFor("", "").should("eq", null);
        closingFor("  ", " ").should("eq", null);
    });
});
