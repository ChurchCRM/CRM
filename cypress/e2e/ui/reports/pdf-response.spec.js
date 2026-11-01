/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

describe("Staff download reports with a common church identity", () => {
    const original = {};
    const churchName = 'Église & "Nord"';
    const keys = ["sChurchName", "sChurchTaxId", "sDateFilenameFormat", "iPDFOutputType"];
    const configure = (name, value) => cy.request("POST", `/admin/api/system/config/${name}`, { value }).its("status").should("eq", 200);
    const download = (url) => cy.request({ url, encoding: "binary" });
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

    it("uses private PDF responses and church-prefixed Unicode filenames across modules", () => {
        [envelope, "/groups/sundayschool/reports/class-list"].forEach((url) => {
            download(url).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.eq("application/pdf");
                expect(response.headers["cache-control"]).to.include("private").and.include("no-store");
                expect(response.headers["x-content-type-options"]).to.eq("nosniff");
                const disposition = response.headers["content-disposition"];
                expect(disposition).to.match(/filename="glise-Nord-[A-Za-z0-9-]+\.pdf"/);
                const filename = decodeURIComponent(disposition.split("filename*=UTF-8''")[1]);
                expect(filename).to.match(/^Église-Nord-(EnvelopeAssignments|ClassList)-\d{4}-\d{2}-\d{2}\.pdf$/);
                expect(filename.match(/Église-Nord/g)).to.have.length(1);
                expect(response.body.slice(0, 5)).to.eq("%PDF-");
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
