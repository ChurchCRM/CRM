/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const download = (body) => cy.request({
    method: "POST", url: "/finance/reports/voting-members/download", form: true, body,
    encoding: "binary", failOnStatusCode: false, followRedirect: false,
});

const expectCount = (response, count) => {
    expect(response.status).to.eq(200);
    expect(response.headers["content-type"]).to.include("application/pdf");
    expect(response.headers["cache-control"]).to.include("private").and.include("no-store");
    return pdfText(response.body).then((lines) => {
        const text = lines.join(" ");
        expect(text).to.match(new RegExp(`People matching filters\\s*:\\s*${count}\\b`));
        return text;
    });
};

describe("Treasurer prepares a voting review list under church bylaws", () => {
    const people = [];
    const marker = `Voting${Date.now()}`;
    let classificationId;
    let familyId;
    let depositId;
    let originalMonth;
    let originalOutput;
    let originalFinance;
    const criteria = (extra = {}) => ({
        classList: [classificationId], AsOf: "2025-10-31", MinimumAge: 0, MembershipMonths: 0,
        RequireDonationYears: 0, FYID: 29, ...extra,
    });
    const createPerson = (name, birth, joined, deceased = false) => {
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type(marker);
        cy.get("#Gender").select("1");
        cy.get("#Classification").select(String(classificationId));
        if (birth) {
            cy.get("#BirthYear").clear().type(birth[0]);
            cy.get("#BirthMonth").select(birth[1]);
            cy.get("#BirthDay").select(birth[2]);
        }
        if (joined) cy.get("#MembershipDate").clear().type(joined);
        if (deceased) cy.get("#IsDeceased").check();
        cy.get('button[name="PersonSubmit"]').click();
        cy.location("pathname").should("match", /\/people\/view\/\d+$/).then((path) => {
            people.push(Number(path.split("/").pop()));
        });
    };

    before(() => {
        cy.getSystemConfig("iFYMonth").then((value) => { originalMonth = value; });
        cy.getSystemConfig("iPDFOutputType").then((value) => { originalOutput = value; });
        cy.getSystemConfig("bEnabledFinance").then((value) => { originalFinance = value; });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/iFYMonth", { value: "1" }, 200);
        cy.makePrivateAdminAPICall("POST", "/admin/api/options/1", { name: marker }, 200).then(({ body }) => {
            classificationId = body.optionId ?? body.id;
        });
        cy.setupAdminSession();
        cy.then(() => createPerson("Boundary & Équipe", ["2007", "10", "31"], "2025-04-30"));
        cy.then(() => createPerson("TooYoung", ["2007", "11", "01"], "2020-01-01"));
        cy.then(() => createPerson("MissingDates", null, null));
        cy.then(() => createPerson("RecentMember", ["1990", "01", "01"], "2025-05-01"));
        cy.then(() => createPerson("Deceased", null, null, true));
        cy.then(() => createPerson("Inactive", null, null));
        cy.then(() => cy.request("POST", `/api/person/${people[5]}/activate/false`));
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(`${marker}Donors`);
        ["AnnDonor", "BenDonor"].forEach((name, index) => {
            cy.get(`input[name="FirstName${index + 1}"]`).type(name);
            cy.then(() => cy.get(`select[name="Classification${index + 1}"]`).select(String(classificationId)));
        });
        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname").should("match", /\/people\/family\/\d+$/).then((path) => {
            familyId = Number(path.split("/").pop());
        });
        cy.request("POST", "/api/deposits", { depositType: "Bank", depositComment: marker, depositDate: "2025-01-01" }).its("body.Id").then((id) => {
            depositId = id;
            ["2024-01-01", "2025-12-01"].forEach((date) => {
                cy.then(() => cy.request("POST", "/api/payments/pledges", {
                    type: "Payment", iMethod: "CASH", Date: date, FamilyID: String(familyId), FYID: 29,
                    tScanString: "", DepositID: id,
                    FundSplit: JSON.stringify([{ FundID: "1", Amount: 10, NonDeductible: 0, Comment: marker }]),
                }));
            });
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("iFYMonth", originalMonth);
        cy.restoreSystemConfig("iPDFOutputType", originalOutput);
        cy.restoreSystemConfig("bEnabledFinance", originalFinance);
        cy.makePrivateAdminAPICall("DELETE", `/api/deposits/${depositId}`, null, [200, 404]);
        cy.cleanupFamilies([familyId]);
        cy.cleanupPeople(people);
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/1/${classificationId}`, null, [200, 404]);
    });

    it("chooses its own classifications and downloads matching people, including those without a family", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/finance/reports");
        cy.get('#financeReports a.btn[href$="/finance/reports/voting-members"]').should("have.length", 1).click();
        cy.get("#votingClasses").select([String(classificationId)]);
        cy.get("#votingAsOf").clear().type("2025-10-31");
        cy.intercept("POST", "**/finance/reports/voting-members/download").as("report");
        cy.get("#createReport").click();
        cy.wait("@report").its("response.statusCode").should("eq", 200);
        download(criteria()).then((response) => expectCount(response, 6)).then((text) => {
            expect(text).to.include("Boundary & Équipe").and.include("MissingDates").and.include("AnnDonor");
            expect(text).not.to.include(`Deceased ${marker}`).and.not.to.include(`Inactive ${marker}`);
        });
        download(criteria({ MinimumAge: 18, MembershipMonths: 6 })).then((response) => expectCount(response, 1)).then((text) => {
            expect(text).to.include("Boundary & Équipe").and.not.to.include("TooYoung").and.not.to.include("RecentMember");
            expect(text).to.match(/People omitted because required dates are missing\s*:\s*3\b/);
        });
    });

    it("uses the selected fiscal years and never counts payments after the review date", () => {
        cy.setupPersonaSession("finance-only");
        download(criteria({ RequireDonationYears: 2 })).then((response) => expectCount(response, 2)).then((text) => {
            expect(text).to.include("AnnDonor").and.include("BenDonor").and.not.to.include("Boundary & Équipe");
        });
        download(criteria({ RequireDonationYears: 1 })).then((response) => {
            expect(response.headers["content-type"]).to.include("text/html");
            expect(response.body).to.include("No people match these filters");
            const doc = new DOMParser().parseFromString(response.body, "text/html");
            expect(doc.querySelector("#votingYears").value).to.eq("1");
            expect(doc.querySelector("#votingAsOf").value).to.eq("2025-10-31");
            expect(doc.querySelector("#votingClasses option:checked").value).to.eq(String(classificationId));
        });
    });

    it("omits members of inactive families and inactive classifications", () => {
        cy.setupAdminSession();
        cy.request("POST", `/api/family/${familyId}/activate/false`);
        download(criteria()).then((response) => expectCount(response, 4)).then((text) => {
            expect(text).not.to.include("AnnDonor").and.not.to.include("BenDonor");
        });
        cy.request("POST", `/api/family/${familyId}/activate/true`);
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/1/${classificationId}/inactive`, {}, 200);
        cy.setupAdminSession();
        download(criteria()).then((response) => {
            expect(response.headers["content-type"]).to.include("text/html");
            expect(response.body).to.include("No people match these filters");
        });
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/1/${classificationId}/inactive`, {}, 200);
    });

    it("rejects invalid dates and bounds while preserving the submitted filters", () => {
        cy.setupPersonaSession("finance-only");
        [{ AsOf: "2025-02-30" }, { AsOf: "2025-10-31\u0000" }, { MinimumAge: -1 }, { RequireDonationYears: 101 }, { MembershipMonths: "abc" }, { classList: [999999] }, { classList: [] }].forEach((invalid) => {
            download(criteria(invalid)).then((response) => {
                expect(response.status).to.eq(422);
                expect(response.headers["content-type"]).to.include("text/html");
                expect(response.body).to.include('id="votingReportMessage"');
            });
        });
    });

    it("preserves inline and attachment output with a safe PDF filename", () => {
        cy.setupAdminSession();
        ["0", "1"].forEach((value) => {
            cy.makePrivateAdminAPICall("POST", "admin/api/system/config/iPDFOutputType", { value }, 200);
            cy.setupAdminSession();
            download(criteria()).then((response) => {
                expect(response.headers["content-disposition"]).to.match(new RegExp(`^${value === "1" ? "attachment" : "inline"}; filename="VotingMembers[A-Za-z0-9._-]*\\.pdf"$`));
            });
        });
    });

    it("keeps staff without Finance, portal users, API keys, and disabled Finance out", () => {
        cy.clearAllCookies();
        download(criteria()).then((response) => {
            expect(response.status).to.be.oneOf([302, 401]);
            expect(response.headers["content-type"] || "").not.to.include("application/pdf");
        });
        ["office-staff", "zero-permission-staff", "portal-only"].forEach((persona) => {
            cy.setupPersonaSession(persona);
            download(criteria()).then((response) => {
                expect(response.status).to.be.oneOf([302, 403]);
                expect(response.headers["content-type"] || "").not.to.include("application/pdf");
            });
        });
        cy.makePrivateAdminAPICall("GET", "/finance/reports/voting-members/download", null, 403);
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnabledFinance", { value: "0" }, 200);
        cy.setupAdminSession();
        download(criteria()).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("access-denied?role=Finance");
        });
        cy.restoreSystemConfig("bEnabledFinance", originalFinance);
    });
});
