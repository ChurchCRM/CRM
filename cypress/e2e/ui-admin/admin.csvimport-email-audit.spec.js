/// <reference types="cypress" />

/**
 * Member sign-in row 10 (#10463): an address a CSV import gives a new person is
 * the address on record (docs/member-sign-in-design.md §9). It signs in at
 * once, and a first email is no change, so the audit records none. The audit
 * has no HTTP surface until row 9, and the importer answers no ids, so both are
 * read with cy.dbQuery().
 */

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

describe("CSV import and the sign-in email audit (§9)", () => {
    const email = "emailaudit.import@example.com";
    const personIds = [];
    let startId;

    before(() => {
        db("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => {
            startId = Number(rows[0].id);
        });
    });

    after(() => {
        cy.cleanupPeople(personIds);
    });

    it("makes each imported address the address on record, with no email change audited", () => {
        cy.freshAdminFormLogin({ path: "/login", visible: true, timeout: 10000 });
        cy.visit("admin/import/csv");
        cy.get("#csvFile").selectFile("cypress/fixtures/test_import_email_audit.csv", { force: true });
        cy.get("#csv-import-form").submit();
        cy.get("#mapping-card").should("be.visible");
        cy.get("#execute-import").click();
        cy.get("#summary-imported").should("have.text", "1");

        db("SELECT per_ID FROM person_per WHERE per_Email = ? ORDER BY per_ID DESC", [email]).then((people) => {
            people.forEach((row) => personIds.push(row.per_ID));
            const personId = people[0].per_ID;

            db("SELECT * FROM portal_signin_audit_psa WHERE psa_per_ID = ? AND psa_ID > ?", [personId, startId]).then((audit) => {
                expect(audit).to.have.length(0);
            });
            cy.request(`/api/person/${personId}/sign-in`).its("body.unconfirmedEmail").should("be.null");
        });
    });
});
