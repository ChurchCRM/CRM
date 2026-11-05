/// <reference types="cypress" />

import { MEMBER_PAGES } from "../../../support/mvc-pages";
import { expectPageHealth } from "../../../support/page-health";

describe("Page load health: member pages", () => {
    beforeEach(() => {
        cy.signInAsMember(100);
    });

    MEMBER_PAGES.forEach((page) => {
        it(`${page} loads without JavaScript or server errors`, () => {
            expectPageHealth(page);
        });
    });
});
