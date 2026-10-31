/// <reference types="cypress" />

describe("Letters and Labels", () => {
    beforeEach(() => {
        cy.freshAdminFormLogin();
        cy.visit("/LettersAndLabels.php");
    });

    it("offers the label fonts", () => {
        cy.get("select[name=labelfont] option")
            .then(($opts) => [...$opts].map((o) => o.textContent.trim()))
            .should((names) => {
                expect(names).to.include("Helvetica");
                expect(names).to.include("Helvetica Bold");
                expect(names).to.not.include("Symbol");
                expect(names).to.not.include("Zapfdingbats");
            });
    });

    it("each button sends the form to its label PDF", () => {
        cy.get("form[target=_blank]").then(($form) => {
            const query = new URLSearchParams(new FormData($form[0])).toString();
            const newsletter = $form[0].action;
            const confirm = $form[0].querySelector("button[formaction]").formAction;
            expect(newsletter).to.match(/\/people\/labels\/newsletter$/);
            expect(confirm).to.match(/\/people\/labels\/confirm$/);

            [newsletter, confirm].forEach((url) => {
                cy.request({ url: `${url}?${query}`, encoding: "binary" }).then((response) => {
                    expect(response.headers["content-type"]).to.include("application/pdf");
                    expect(response.body.slice(0, 5)).to.eq("%PDF-");
                });
            });
        });
    });
});
