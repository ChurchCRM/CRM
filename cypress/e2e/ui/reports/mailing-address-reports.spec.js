/// <reference types="cypress" />

/**
 * Mail is addressed to the family's mailing address (#9743).
 *
 * NewsLetterLabels.php and ConfirmLabels.php build every label from
 * Family::getMailingAddressLines() — the flagged second address when a family has
 * one, the primary address otherwise — and order the run by the ZIP that is
 * actually printed rather than by the primary ZIP. Every letter that goes through
 * ChurchInfoReport::startLetterPage() is addressed the same way.
 *
 * The PDFs are fetched with cy.request() (cy.visit() only accepts text/html) and
 * their text is read back with pdfText() below, so each test asserts the address
 * that was actually printed, not just that a PDF came back.
 */
describe("Mailing address on mailed reports (#9743)", () => {
    const createdFamilyIds = [];

    const labelQuery =
        "labeltype=Tractor&labelfont=Helvetica&labelfontsize=default&recipientnamingmethod=familyname";

    /**
     * Direct form login, as in confirm-reports.spec.js: these pages need the
     * MenuOptions role flag and a PHP session uncontaminated by earlier tests.
     */
    const freshAdminLogin = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.env("admin.username"));
        cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
        cy.url().should("not.include", "/session/begin");
    };

    /**
     * Creates a family whose flagged second address is in a different ZIP.
     * Pass `secondCity: ""` for a PO Box that has only a state and ZIP on record,
     * and `members` (first names) when the family has to appear in the directory,
     * which lists people, not families.
     */
    const createFamilyWithMailingAddress = (familyName, { secondCity = "Othertown", members = [] } = {}) => {
        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        members.forEach((firstName, index) => {
            // Last name left blank: the editor fills in the family name. The
            // directory form pre-selects the member classifications, so each
            // member is a Member (1) rather than Unassigned (0).
            cy.get(`input[name="FirstName${index + 1}"]`).type(firstName);
            cy.get(`select[name="Classification${index + 1}"]`).select("1", { force: true });
        });
        cy.get('input[name="Address1"]').type("742 Evergreen Terrace");
        cy.get('input[name="City"]').clear().type("Springfield");
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get('input[name="Zip"]').clear().type("62704");

        cy.get("#secondAddressToggle").click();
        cy.get("#SecondAddress1").type("PO Box 1204");
        if (secondCity !== "") {
            cy.get("#SecondCity").type(secondCity);
        }
        cy.get("#SecondState").select("IL", { force: true });
        cy.get("#SecondZip").type("62998");
        cy.get("#SecondIsMailing").should("not.be.disabled").check();

        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname").should("include", "/people/family/");

        return cy.location("pathname").then((pathname) => {
            const id = Number(pathname.split("/").pop());
            createdFamilyIds.push(id);
            return id;
        });
    };

    /**
     * The text FPDF wrote into a PDF, one entry per Tj operator — which is one per
     * line of a Cell()/MultiCell() call, so a label's address comes back line for
     * line. Page content is zlib-compressed by FPDF, so each `stream ... endstream`
     * block is inflated with the browser's DecompressionStream; blocks that are not
     * page content (fonts, images) yield no Tj operators and drop out on their own.
     *
     * @param {string} binaryBody a cy.request() body fetched with encoding "binary"
     * @returns {Promise<string[]>}
     */
    const pdfText = (binaryBody) => {
        const bytes = Uint8Array.from(binaryBody, (c) => c.charCodeAt(0));
        const decoder = new TextDecoder("latin1");
        const blocks = [];
        const streamStart = /stream\r?\n/g;
        let match;
        while ((match = streamStart.exec(binaryBody)) !== null) {
            const dict = binaryBody.slice(Math.max(0, match.index - 200), match.index);
            const length = /\/Length (\d+)/.exec(dict);
            if (length === null) {
                continue;
            }
            const start = match.index + match[0].length;
            const end = start + Number(length[1]);
            blocks.push({ data: bytes.subarray(start, end), compressed: /FlateDecode/.test(dict) });
            streamStart.lastIndex = end;
        }

        const inflate = async ({ data, compressed }) => {
            if (!compressed) {
                return decoder.decode(data);
            }
            try {
                const inflated = new Blob([data]).stream().pipeThrough(new DecompressionStream("deflate"));
                return decoder.decode(await new Response(inflated).arrayBuffer());
            } catch (error) {
                return "";
            }
        };

        return Promise.all(blocks.map(inflate)).then((contents) =>
            contents.flatMap((content) =>
                Array.from(content.matchAll(/\(((?:\\.|[^\\)])*)\)\s*Tj/g), ([, text]) =>
                    text.replace(/\\([\\()])/g, "$1")
                )
            )
        );
    };

    /** Fetches a PDF report over the browser's session and returns its text lines. */
    const fetchPdfLines = (path) =>
        cy
            .request({ url: path, encoding: "binary", failOnStatusCode: false })
            .then((response) => {
                expect(response.status, "no server error").to.equal(200);
                expect(response.headers["content-type"] || "").to.include("application/pdf");
                return pdfText(response.body);
            });

    /** The three lines a label prints for a family that mails to its PO Box. */
    const expectLabelLines = (lines, familyName) => {
        const at = lines.indexOf(familyName);
        expect(at, `a label for ${familyName}`).to.be.greaterThan(-1);
        expect(lines.slice(at, at + 3)).to.deep.equal([familyName, "PO Box 1204", "Othertown, IL  62998"]);
        expect(lines).to.not.include("742 Evergreen Terrace");
    };

    beforeEach(() => {
        freshAdminLogin();
        cy.visit("/LettersAndLabels.php");
    });

    after(() => {
        // deleteMembers: without it the family delete only unlinks the members,
        // which would leave the directory suite's people behind as orphans.
        createdFamilyIds.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}?deleteMembers=true`, null, 200);
        });
    });

    it("prints the second address on newsletter labels when it is the mailing address", () => {
        const familyName = "MailLabels" + Cypress._.random(0, 1e6);
        createFamilyWithMailingAddress(familyName);
        // New families receive the newsletter by default, so this one gets a label.
        fetchPdfLines(`/Reports/NewsLetterLabels.php?${labelQuery}`).then((lines) => {
            expectLabelLines(lines, familyName);
        });
    });

    it("prints the second address on confirm data labels when it is the mailing address", () => {
        const familyName = "MailConfirm" + Cypress._.random(0, 1e6);
        createFamilyWithMailingAddress(familyName);
        // Confirm labels cover every family.
        fetchPdfLines(`/Reports/ConfirmLabels.php?${labelQuery}`).then((lines) => {
            expectLabelLines(lines, familyName);
        });
    });

    it("addresses the confirmation letter to the second address and lists it on the data sheet", () => {
        const familyName = "MailLetter" + Cypress._.random(0, 1e6);
        createFamilyWithMailingAddress(familyName).then((familyId) => {
            fetchPdfLines(`/people/report/verify?familyId=${familyId}`).then((lines) => {
                // The address block under the letterhead — salutation, then the
                // mailing address — is what shows through the envelope window.
                const block = lines.indexOf("PO Box 1204");
                expect(block, "the mailing address in the letter's address block").to.be.greaterThan(-1);
                expect(lines[block - 1]).to.include(familyName);
                expect(lines[block + 1]).to.equal("Othertown, IL  62998");
                // The data sheet lists what is on record: the primary address and,
                // because it differs, the mailing address too.
                const address = lines.indexOf("Address:");
                expect(address, "the data sheet's Address line").to.be.greaterThan(-1);
                expect(lines[address + 1]).to.equal("742 Evergreen Terrace, Springfield, IL  62704");
                const mailing = lines.indexOf("Mailing Address:");
                expect(mailing, "the data sheet's Mailing Address line").to.be.greaterThan(-1);
                expect(lines[mailing + 1]).to.equal("PO Box 1204, Othertown, IL  62998");
            });
        });
    });

    // Keep these last: the API-key call replaces the browser's CRM session server-side.
    it("resolves the printed address to the flagged second address", () => {
        createFamilyWithMailingAddress("MailLabelData" + Cypress._.random(0, 1e6)).then((familyId) => {
            cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).then((response) => {
                // What a label prints, line for line.
                expect(response.body.MailingAddressLines).to.equal("PO Box 1204, Othertown, IL  62998");
                // The ZIP the label run is sorted on.
                expect(response.body.MailingAddress.Zip).to.equal("62998");
                expect(response.body.Zip).to.equal("62704");
            });
        });
    });

    it("prints a city-less mailing address without a dangling comma", () => {
        createFamilyWithMailingAddress("MailNoCity" + Cypress._.random(0, 1e6), { secondCity: "" }).then(
            (familyId) => {
                cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).then((response) => {
                    expect(response.body.MailingAddressLines).to.equal("PO Box 1204, IL 62998");
                });
            }
        );
    });

    /**
     * The church directory report (#9743): "Address" is relabelled "Primary
     * Address" and a new "Mailing Address if Different" sub-option, off by
     * default, prints the flagged second address under a "Mailing Address:" label.
     *
     * Mocha runs a nested suite after its parent's own tests, so these start from
     * the parent's freshAdminLogin() and are unaffected by the API-key calls above.
     * Families created here land in the same createdFamilyIds list, so the shared
     * `after` hook still returns family_fam to the row count it started with.
     */
    describe("Church directory report", () => {
        /**
         * Posts the directory form exactly as the browser serialises it, so the
         * option keys under test are the real ones and the defaults are honest.
         * The form target is a PDF, which cy.visit() cannot follow, so the POST is
         * replayed with cy.request() over the session the visit established and
         * the PDF's text lines are handed back.
         */
        const submitDirectoryForm = () =>
            cy.get('form[action="Reports/DirectoryReport.php"]').then(($form) =>
                cy
                    .request({
                        method: "POST",
                        url: "Reports/DirectoryReport.php",
                        headers: { "content-type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams(new FormData($form[0])).toString(),
                        encoding: "binary",
                        failOnStatusCode: false,
                    })
                    .then((response) => {
                        expect(response.status, "no server error").to.equal(200);
                        expect(response.headers["content-type"] || "").to.include("application/pdf");
                        return pdfText(response.body);
                    })
            );

        beforeEach(() => {
            cy.visit("/DirectoryReports.php");
        });

        it("relabels the address option and offers an indented mailing-address sub-option", () => {
            cy.get('label[for="bDirAddress"]').should("have.text", "Primary Address");
            // Same POST key, same default — only the wording changed.
            cy.get("#bDirAddress").should("be.checked");

            cy.get('label[for="bDirMailingAddress"]').should(
                "have.text",
                "Mailing Address if Different"
            );
            // Off by default, so existing directories render exactly as before.
            cy.get("#bDirMailingAddress").should("not.be.checked").and("be.enabled");
            // Indented so it reads as a sub-option of "Primary Address" in the grid.
            cy.get("#bDirMailingAddress").parent().should("have.class", "ms-4");
        });

        it("disables and clears the sub-option while the primary address is off", () => {
            // The mailing address prints beneath the primary one, so the report
            // ignores the sub-option when the primary address is off; the form
            // says so instead of letting the choice vanish silently.
            cy.get("#bDirMailingAddress").check();
            cy.get("#bDirAddress").uncheck();
            cy.get("#bDirMailingAddress").should("be.disabled").and("not.be.checked");
            cy.get("#bDirAddress").check();
            cy.get("#bDirMailingAddress").should("be.enabled").and("not.be.checked");
        });

        it("generates a directory with the mailing address option off", () => {
            const familyName = "MailDirOff" + Cypress._.random(0, 1e6);
            createFamilyWithMailingAddress(familyName, { members: ["Ann", "Ben"] }).then(() => {
                cy.visit("/DirectoryReports.php");
                submitDirectoryForm().then((lines) => {
                    const at = lines.findIndex((line) => line.includes(familyName));
                    expect(at, `the ${familyName} entry`).to.be.greaterThan(-1);
                    expect(lines).to.include("742 Evergreen Terrace");
                    // The default output is the directory exactly as it printed before.
                    expect(lines.some((line) => line.includes("Mailing Address"))).to.equal(false);
                    expect(lines).to.not.include("PO Box 1204");
                });
            });
        });

        it("prints the mailing address under the primary one when the option is on", () => {
            const familyName = "MailDirOn" + Cypress._.random(0, 1e6);
            createFamilyWithMailingAddress(familyName, { members: ["Ann", "Ben"] }).then(() => {
                cy.visit("/DirectoryReports.php");
                cy.get("#bDirMailingAddress").check();
                submitDirectoryForm().then((lines) => {
                    const at = lines.findIndex((line) => /Mailing Address: PO Box 1204$/.test(line));
                    expect(at, "the family's Mailing Address line").to.be.greaterThan(-1);
                    expect(lines[at + 1]).to.match(/Othertown, IL {2}62998$/);
                    // Beneath the primary address, which still prints.
                    const primary = lines.findIndex((line) => line.includes("742 Evergreen Terrace"));
                    expect(primary).to.be.greaterThan(-1).and.to.be.lessThan(at);
                });
            });
        });
    });
});
