/// <reference types="cypress" />

import { addAgedAuditRows, auditRowsById, removeAuditRows } from "../../../../support/sign-in-audit-retention";

// Member sign-in audit retention (#10463, design §11.2): the daily timer job
// deletes rows that name no person after 30 days, and after 90 days clears every
// row's IP address and the address typed on a refused or throttled sign-in.
describe("Member sign-in audit retention", () => {
    const PERSON = 2;
    const TYPED = "stranger@example.com";
    const IP = "203.0.113.7";
    const ids = [];

    after(() => {
        removeAuditRows(ids);
    });

    it("forgets strangers after 30 days and identifiers after 90, keeping each person's history", () => {
        addAgedAuditRows([
            { daysAgo: 31, event: "refused", email: TYPED, ip: IP },
            { daysAgo: 29, event: "refused", email: TYPED, ip: IP },
            { daysAgo: 91, personId: PERSON, event: "refused", email: TYPED, ip: IP },
            { daysAgo: 91, personId: PERSON, event: "throttled", email: TYPED, ip: IP },
            { daysAgo: 91, personId: PERSON, event: "signed_in", email: TYPED, ip: IP },
            { daysAgo: 89, personId: PERSON, event: "throttled", email: TYPED, ip: IP },
        ]).then((added) => {
            ids.push(...added);
            const [strangerOld, strangerRecent, refusedOld, throttledOld, signedInOld, throttledRecent] = added;

            cy.makePrivateAdminAPICall("POST", "/api/background/timerjobs", { force: true }, 200).its("body.ran").should("eq", true);

            auditRowsById(added).then((rows) => {
                expect(rows, "the stranger's old row").to.not.have.property(String(strangerOld));
                expect(rows[strangerRecent]).to.deep.eq({ email: TYPED, ip: IP });
                expect(rows[refusedOld]).to.deep.eq({ email: null, ip: null });
                expect(rows[throttledOld]).to.deep.eq({ email: null, ip: null });
                expect(rows[signedInOld]).to.deep.eq({ email: TYPED, ip: null });
                expect(rows[throttledRecent]).to.deep.eq({ email: TYPED, ip: IP });
            });
        });
    });
});
