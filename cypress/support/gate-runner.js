import { GATES } from "./gates";

const rowsFor = (persona, audience, probe) =>
    GATES.filter(
        (row) => row.audience === audience && row[probe] && (row.allow.includes(persona) || row.deny.includes(persona)),
    );

export const personasFor = (audience, probe) => [
    ...new Set(
        GATES.filter((row) => row.audience === audience && row[probe]).flatMap((row) => [...row.allow, ...row.deny]),
    ),
];

export function runApiGates(persona, audience = "standard") {
    const rows = rowsFor(persona, audience, "api").filter((row) => !(row.denyOnly && row.allow.includes(persona)));
    if (rows.length === 0) {
        return;
    }

    describe(`${persona}: API gates`, () => {
        rows.forEach((row) => {
            const allowed = row.allow.includes(persona);
            if (allowed && row.denyOnly) {
                return;
            }

            it(`${row.id} ${allowed ? "allows" : "refuses"} ${row.api.method} ${row.api.url}`, () => {
                cy.personaAPICall(persona, row.api.method, row.api.url, row.api.body ?? null, allowed ? 200 : 403).then((response) => {
                    if (!allowed) {
                        expect(response.body.success).to.eq(false);
                    }
                });
            });
        });
    });
}

export function runPageGates(persona, audience = "standard") {
    const rows = rowsFor(persona, audience, "page");
    if (rows.length === 0) {
        return;
    }

    describe(`${persona}: page gates`, () => {
        beforeEach(() => cy.setupPersonaSession(persona));

        rows.forEach((row) => {
            const allowed = row.allow.includes(persona);

            it(`${row.id} ${allowed ? "opens" : "is refused"} ${row.page}`, () => {
                cy.visit(row.page, { failOnStatusCode: false });
                if (allowed) {
                    cy.location("pathname").should("include", row.page.replace(/\/$/, ""));
                    cy.location("pathname").should("not.include", "/v2/access-denied");
                } else {
                    cy.url().should("include", "/v2/access-denied");
                }
            });
        });
    });
}
