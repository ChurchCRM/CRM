/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

describe("Staff download reports with a common church identity", () => {
    const original = {};
    const churchName = 'Église & "Nord"';
    const keys = ["sChurchName", "sChurchTaxId", "sDateFilenameFormat", "iPDFOutputType"];
    const configure = (name, value) => cy.request("POST", `/admin/api/system/config/${name}`, { value }).its("status").should("eq", 200);
    const download = (url) => cy.request({ url, encoding: "binary" });
    const reports = [
        ["/finance/reports/envelope-numbers", "EnvelopeAssignments"],
        ["/finance/reports/family-pledge-summary/download?FYID=23", "FamilyPledgeSummary"],
        ["/finance/reports/zero-givers/download?DateStart=1990-01-01&DateEnd=1990-12-31", "ZeroGivers"],
        ["/finance/reports/pledge-reminders/download?FYID=23", "PledgeReminders"],
        ["/fundraiser/3/reports/catalog", "FundraiserCatalog"],
        ["/fundraiser/3/reports/bid-sheets", "FundraiserBidSheets"],
        ["/fundraiser/3/reports/certificates", "FundraiserCertificates"],
        ["/fundraiser/3/reports/statement", "FundraiserStatement"],
        ["/groups/sundayschool/reports/class-list", "ClassList"],
        ["/groups/reports/photo-book?GroupID=1", "PhotoBook-Angels-class"],
        ["/groups/sundayschool/reports/class-attendance?GroupID=1&FirstSunday=2026-09-06&LastSunday=2026-09-27", "ClassAttendance"],
        ["/people/labels?report=birthdays&month=7&labeltype=5160", "Labels"],
        ["/people/labels/name-tags?labeltype=74536", "NameTags"],
        ["/people/labels/newsletter?labeltype=5160", "NewsLetterLabels"],
        ["/people/labels/confirm?labeltype=5160", "ConfirmDataLabels"],
        ["/people/report/verify?familyId=17", "ConfirmReport-Cooper"],
    ];
    const messages = () => cy.request(`${Cypress.mailpitUrl()}/api/v1/messages?limit=200`);
    const newAttachment = (knownIds, subject, attemptsLeft = 20) => messages().then(({ body }) => {
        const message = body.messages.find((entry) => !knownIds.includes(entry.ID) && entry.Subject.includes(subject));
        if (message) {
            return cy.request(`${Cypress.mailpitUrl()}/api/v1/message/${message.ID}`).then(({ body: full }) => {
                const pdf = full.Attachments.find((part) => part.ContentType === "application/pdf");
                expect(pdf, "PDF attachment").to.exist;
                return pdf;
            });
        }
        expect(attemptsLeft, `new ${subject} email`).to.be.greaterThan(0);
        cy.wait(500);
        return newAttachment(knownIds, subject, attemptsLeft - 1);
    });
    const envelope = "/finance/reports/envelope-numbers";

    before(() => {
        keys.forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
    });

    beforeEach(() => {
        cy.setupAdminSession();
        configure("sChurchName", churchName);
        configure("sChurchTaxId", "TAX-11040");
        configure("sDateFilenameFormat", "Y-m-d");
        configure("iPDFOutputType", "1");
    });

    afterEach(() => {
        keys.forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    reports.forEach(([url, label]) => {
        it(`downloads ${label} with private headers and church-prefixed Unicode filenames`, () => {
            ["1", "2"].forEach((value) => {
                configure("iPDFOutputType", value);
                download(url).then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.headers["content-type"]).to.eq("application/pdf");
                    expect(response.headers["cache-control"]).to.include("private").and.include("no-store");
                    expect(response.headers["x-content-type-options"]).to.eq("nosniff");
                    const disposition = response.headers["content-disposition"];
                    expect(disposition).to.match(new RegExp(`^${value === "1" ? "attachment" : "inline"};`));
                    expect(disposition).to.match(/filename="glise-Nord-[A-Za-z0-9-]+\.pdf"/);
                    const filename = decodeURIComponent(disposition.split("filename*=UTF-8''")[1]);
                    const date = label === "ConfirmReport-Cooper" ? "" : "-\\d{4}-\\d{2}-\\d{2}";
                    expect(filename).to.match(new RegExp(`^Église-Nord-${label}${date}\\.pdf$`));
                    expect(filename.match(/Église-Nord/g)).to.have.length(1);
                    expect(response.body.slice(0, 5)).to.eq("%PDF-");
                });
            });
        });
    });

    it("emails the giving statement with the shared Unicode attachment filename", () => {
        messages().then(({ body }) => {
            const knownIds = body.messages.map((message) => message.ID);
            cy.visit("/people/family/1");
            cy.get('form[action$="/giving-statements/email"]').filter(':has(input[name="year"][value="2018"])')
                .find('input[name="csrf_token"]').invoke("val").then((token) => {
                    cy.request({
                        method: "POST", url: "/finance/reports/giving-statements/email", form: true,
                        body: { familyId: 1, year: 2018, csrf_token: token }, followRedirect: false,
                    }).then((response) => {
                        expect(response.status).to.eq(302);
                        expect(response.headers.location).to.include("TaxEmailSent=2018");
                    });
                });
            newAttachment(knownIds, "Giving Statement").then((pdf) => {
                expect(pdf.FileName).to.eq("Église-Nord-GivingStatement-2018-Campbell.pdf");
            });
        });
    });

    it("emails verification with the shared Unicode attachment filename", () => {
        messages().then(({ body }) => {
            const knownIds = body.messages.map((message) => message.ID);
            cy.visit("/people/verify");
            cy.get('#verifyEmailAllForm input[name="csrf_token"]').invoke("val").then((token) => {
                cy.request({
                    method: "POST", url: "/people/report/verify/email", form: true,
                    body: { familyId: 17, csrf_token: token }, followRedirect: false,
                }).then((response) => {
                    expect(response.status).to.eq(302);
                    expect(response.headers.location).to.include("/people/family/17?PDFEmailed=1");
                });
            });
            newAttachment(knownIds, "Cooper: Please verify your family's information").then((pdf) => {
                expect(pdf.FileName).to.match(/^Église-Nord-ConfirmReportEmail-Cooper-\d{4}-\d{2}-\d{2}\.pdf$/);
            });
        });
    });

    it("shares the church header and includes the tax ID only on the giving statement", () => {
        download(envelope).then((response) => pdfText(response.body)).then((lines) => {
            expect(lines.join(" ")).to.include(churchName).and.not.include("TAX-11040");
        });
        download("/finance/reports/giving-statements/download?familyId=1&year=2018")
            .then((response) => pdfText(response.body)).then((lines) => {
                expect(lines.join(" ")).to.include(churchName).and.include("TAX-11040");
            });
    });

    it("honors output preferences and removes path separators from filename dates", () => {
        configure("sDateFilenameFormat", "Y/m/d");
        ["1", "2"].forEach((value) => {
            configure("iPDFOutputType", value);
            download(envelope).then((response) => {
                const disposition = response.headers["content-disposition"];
                expect(disposition).to.match(new RegExp(`^${value === "1" ? "attachment" : "inline"};`));
                const filename = decodeURIComponent(disposition.split("filename*=UTF-8''")[1]);
                expect(filename).to.match(/^Église-Nord-EnvelopeAssignments-\d{4}-\d{2}-\d{2}\.pdf$/);
                expect(filename).not.to.match(/[\\/\r\n"]/);
            });
        });
    });
});
