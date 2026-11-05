/// <reference types="cypress" />

import { MVC_PAGES } from "../../../support/mvc-pages";
import { expectPageHealth } from "../../../support/page-health";

const staffPages = [
    ...Object.entries(MVC_PAGES)
        .filter(([app]) => app !== "admin")
        .flatMap(([, pages]) => pages),
];

describe("Page load health: staff pages", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    staffPages.forEach((page) => {
        it(`${page} loads without JavaScript or server errors`, () => {
            expectPageHealth(page);
        });
    });
});
