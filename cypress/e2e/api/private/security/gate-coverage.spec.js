/// <reference types="cypress" />

import { COVERAGE, MAX_PENDING } from "../../../../support/gates/coverage";
import { GATES } from "../../../../support/gates";

describe("Role gates: every wrapped route file is accounted for", () => {
    let files;

    before(() => {
        cy.task("gates:wrapperFiles").then((found) => {
            files = found;
        });
    });

    it("lists no wrapped file the coverage map does not know", () => {
        expect(files.filter((file) => !(file in COVERAGE))).to.deep.equal([]);
    });

    it("lists no coverage entry for a file that has no gate any more", () => {
        expect(Object.keys(COVERAGE).filter((file) => !files.includes(file))).to.deep.equal([]);
    });

    it("names only rows that exist", () => {
        const ids = GATES.map((row) => row.id);
        const unknown = Object.values(COVERAGE).flatMap((entry) => (entry.rows ?? []).filter((id) => !ids.includes(id)));
        expect(unknown).to.deep.equal([]);
    });

    it("gives every file a row, a spec or a pending reason", () => {
        const bare = Object.entries(COVERAGE).filter(([, entry]) => !entry.rows && !entry.spec && !entry.pending);
        expect(bare.map(([file]) => file)).to.deep.equal([]);
    });

    it("points only at specs that exist", () => {
        const specs = [...new Set(Object.values(COVERAGE).flatMap((entry) => (entry.spec ? [entry.spec] : [])))];
        specs.forEach((spec) => cy.readFile(spec));
    });

    it("does not grow the pending list", () => {
        const pending = Object.values(COVERAGE).filter((entry) => entry.pending);
        expect(pending.length).to.be.at.most(MAX_PENDING);
    });
});
