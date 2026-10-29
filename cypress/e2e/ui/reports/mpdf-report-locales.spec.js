/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * A report renders in the signed-in user's language. Envelope Numbers is the simplest mPDF report,
 * and the seed has no envelopes, so it shows the translated empty-state message.
 *
 * Latin locales are read back as text. Other scripts must come out with an embedded font: a PDF with
 * no font file for non-Latin text is the "empty squares" failure.
 */
const ADMIN_PERSON = 1;
const LOCALE_URL = `/api/user/${ADMIN_PERSON}/setting/ui.locale`;
const REPORT = "finance/reports/envelope-numbers";
const MESSAGE = "No records were returned from the previous report. Please adjust your filters or date range and try again.";

const LATIN = ["fr_FR", "es_ES"];
const OTHER_SCRIPTS = ["ar_EG", "he_IL", "zh_CN", "ja_JP", "ko_KR", "th_TH", "hi_IN", "ru_RU", "el_GR"];

const setLocale = (value) =>
    cy.makePrivateAPICall(Cypress.testEnv("admin.api.key"), "POST", LOCALE_URL, { value }, 200);

const translation = (locale) =>
    cy.readFile(`src/locale/textdomain/${locale}/LC_MESSAGES/messages.po`).then((po) => {
        const quoted = MESSAGE.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        const match = new RegExp(`^msgid "${quoted}"\\nmsgstr "(.*)"$`, "m").exec(po);
        return match ? match[1] : "";
    });

const fetchReport = () => cy.request({ url: REPORT, encoding: "binary" });

describe("mPDF reports follow the user's language", () => {
    beforeEach(() => cy.setupAdminSession());
    after(() => setLocale("en_US"));

    LATIN.forEach((locale) => {
        it(`${locale}: the message is translated in the PDF`, () => {
            setLocale(locale);
            translation(locale).then((msgstr) => {
                expect(msgstr, `${locale} catalogue has the message`).to.not.eq("");
                fetchReport().then((response) => {
                    expect(response.headers["content-type"]).to.include("application/pdf");
                    return pdfText(response.body).then((lines) => {
                        expect(lines.join(" ")).to.include(msgstr.slice(0, 20));
                    });
                });
            });
        });
    });

    OTHER_SCRIPTS.forEach((locale) => {
        it(`${locale}: a valid PDF with an embedded font for the script`, () => {
            setLocale(locale);
            translation(locale).then((msgstr) => {
                fetchReport().then((response) => {
                    expect(response.headers["content-type"]).to.include("application/pdf");
                    expect(response.body.slice(0, 5)).to.eq("%PDF-");
                    if (msgstr !== "") {
                        expect(response.body).to.include("/FontFile2");
                    }
                });
            });
        });
    });
});
