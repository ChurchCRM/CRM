/// <reference types="cypress" />

// The family editor stores the address on the family and creates the member without one.
// Person-over-family precedence is not tested: no UI or API gives a family member an address of their own.
describe("Cart mailing labels", () => {
    const familyName = "Labelfamily" + Cypress._.random(0, 1e6);
    const memberFirstName = "Addressless";
    const street = "742 Evergreen Terrace";
    const city = "Springfield";
    const zip = "62704";
    let familyId;

    const labelQuery =
        "labeltype=5160&labelfont=Helvetica&labelfontsize=10&groupbymode=indiv&onlyfull=1&startrow=1&startcol=1";

    const cart = (method, body = {}) =>
        cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

    before(() => {
        cy.freshAdminFormLogin();

        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type(street);
        cy.get('input[name="City"]').clear().type(city);
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get('input[name="Zip"]').clear().type(zip);
        cy.get('input[name="FirstName1"]').type(memberFirstName);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname")
            .should("include", "/people/family/")
            .then((pathname) => {
                familyId = Number(pathname.split("/").pop());
                expect(familyId).to.be.greaterThan(0);
            });
    });

    after(() => {
        if (familyId) {
            cy.request({
                method: "DELETE",
                url: `/api/family/${familyId}?deleteMembers=true`,
                failOnStatusCode: false,
            });
        }
    });

    beforeEach(() => {
        cy.freshAdminFormLogin();
        cart("DELETE");
        cart("POST", { Family: familyId });
        cy.visit("/people/cart");
        cy.contains("Cart Functions");
        cy.contains(memberFirstName);
    });

    it("offers a Labels button that opens the Generate Labels form", () => {
        cy.get("#cartLabels").should("be.visible").click();

        cy.get("#labelsModal").should("be.visible");
        cy.get("#labelsForm").should("have.attr", "action").and("include", "people/labels");

        cy.get('#labelsForm input[name="groupbymode"]:checked').should("have.value", "indiv");
        cy.get("#labelsForm select[name=labeltype] option").should("have.length.greaterThan", 0);
        cy.get("#labelsForm select[name=labelfont] option").should("have.length.greaterThan", 0);
        cy.get("#labelsForm select[name=labelfontsize] option").should("have.length.greaterThan", 0);
        cy.get("#labelsForm input[name=startrow]").should("have.value", "1");
        cy.get("#labelsForm input[name=startcol]").should("have.value", "1");
        cy.get("#labelsForm input[name=onlyfull]").should("be.checked");
        cy.get("#labelsForm select[name=filetype] option").should("have.length", 2);

        cy.get("#bulkmailquiet").should("be.disabled");
        cy.get("#bulkmailpresort").check();
        cy.get("#bulkmailquiet").should("not.be.disabled");
        cy.get("#bulkmailpresort").uncheck();
        cy.get("#bulkmailquiet").should("be.disabled").and("not.be.checked");
    });

    it("addresses a person with no address of their own at the family address (CSV)", () => {
        cy.request(`/people/labels?${labelQuery}&filetype=CSV`).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.headers["content-type"] || "").to.include("text/csv");

            const csv = String(response.body);
            expect(csv, "member is on the label").to.include(memberFirstName);
            expect(csv, "street comes from the family").to.include(street);
            expect(csv, "city comes from the family").to.include(city);
            expect(csv, "zip comes from the family").to.include(zip);
        });
    });

    it("prints one label per household when grouped by family (CSV)", () => {
        // Labels cap the name at 33 characters.
        const stamp = String(Cypress._.random(0, 1e5));
        const household = "Hh" + stamp;
        const adults = ["Ann" + stamp, "Ben" + stamp];
        const child = "Cal" + stamp;
        const householdStreet = "9 Household Way";

        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(household);
        cy.get('input[name="Address1"]').type(householdStreet);
        cy.get('input[name="City"]').clear().type(city);
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get('input[name="Zip"]').clear().type(zip);
        cy.get('input[name="FirstName1"]').type(adults[0]);
        cy.get('input[name="LastName1"]').clear().type(household);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('select[name="Role1"]').select("1", { force: true });
        cy.get('input[name="FirstName2"]').type(adults[1]);
        cy.get('input[name="LastName2"]').clear().type(household);
        cy.get('select[name="Classification2"]').select("1", { force: true });
        cy.get('select[name="Role2"]').select("2", { force: true });
        cy.get('input[name="FirstName3"]').type(child);
        cy.get('input[name="LastName3"]').clear().type(household);
        cy.get('select[name="Classification3"]').select("1", { force: true });
        cy.get('select[name="Role3"]').select("3", { force: true });
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname")
            .should("include", "/people/family/")
            .then((pathname) => {
                const householdId = Number(pathname.split("/").pop());
                cart("DELETE");
                cart("POST", { Family: householdId });

                const familyQuery = labelQuery.replace("groupbymode=indiv", "groupbymode=fam");
                cy.request(`/people/labels?${familyQuery}&filetype=CSV`).then((response) => {
                    expect(response.status).to.equal(200);
                    const rows = String(response.body)
                        .split("\n")
                        .filter((line) => line.includes(householdStreet));
                    expect(rows, "one label for the household").to.have.length(1);
                    expect(rows[0], "addressed to both adults").to.include(`${adults[0]} & ${adults[1]} ${household}`);
                    expect(rows[0], "not to the child").to.not.include(child);
                });

                cy.request({
                    method: "DELETE",
                    url: `/api/family/${householdId}?deleteMembers=true`,
                    failOnStatusCode: false,
                });
            });
    });

    it("generates the PDF without a server error", () => {
        cy.request({
            url: `/people/labels?${labelQuery}&filetype=PDF`,
            encoding: "binary",
        }).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.headers["content-type"] || "").to.include("application/pdf");
            const body = String(response.body);
            expect(body.slice(0, 5)).to.equal("%PDF-");
            expect(body).to.not.include("Fatal error");
        });
    });
});
