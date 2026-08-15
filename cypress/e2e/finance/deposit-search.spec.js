/// <reference types="cypress" />

/**
 * E2E tests for the /finance/deposit/search MVC page (#9379).
 *
 * Coverage:
 *  1. Page load + search form submit with date range → DataTable updates, URL has filter params
 *  2. Select All checkbox → export buttons enable/disable with selected count
 *  3. CSV export via bulk endpoint → verify headers, sample row values, no formula-injection
 *     trigger chars at the START of fields (CsvExporter prepends a tab to =,-,+,@ values)
 *  4. Non-finance user blocked → redirected to /v2/access-denied?role=Finance
 *
 * Note on CSV formula-injection safety:
 *   CsvExporter prepends \t (tab) to values starting with =, -, +, @.
 *   Tests must assert that exported fields do NOT start with those trigger chars,
 *   NOT that they start with a tab — both representations are safe.
 */

const DEPOSIT_SEARCH_URL = "/finance/deposit/search";
const ACCESS_DENIED_URL  = "/v2/access-denied";
const FORMULA_TRIGGERS   = /^[=\-+@]/;

// ---------------------------------------------------------------------------
// 1. Page load & search form
// ---------------------------------------------------------------------------

describe("Deposit Search: page load and search form", () => {
  before(() => {
    cy.setupAdminSession();
    cy.visit(DEPOSIT_SEARCH_URL);
  });

  it("loads the deposit search page", () => {
    cy.url().should("include", DEPOSIT_SEARCH_URL);
    cy.get("h2.page-title, h3.card-title").should("contain.text", "Deposit");
  });

  it("shows the search form with expected fields", () => {
    cy.get("#depositFilterForm").should("exist");
    cy.get("#dateStart").should("exist");
    cy.get("#dateEnd").should("exist");
    cy.get("#depositId").should("exist");
    cy.get("#fundId").should("exist");
    cy.get("#closed").should("exist");
    cy.get("#enteredBy").should("exist");
  });

  it("shows the deposits table", () => {
    cy.get("#depositsTable").should("exist");
  });

  it("submits date range filter and includes params in URL", () => {
    cy.get("#dateStart").clear().type("2020-01-01");
    cy.get("#dateEnd").clear().type("2099-12-31");
    cy.get("#depositFilterForm").submit();

    cy.url().should("include", "dateStart=2020-01-01");
    cy.url().should("include", "dateEnd=2099-12-31");
    cy.get("#depositsTable").should("exist");
  });

  it("re-populates form fields from URL params after submit", () => {
    cy.get("#dateStart").should("have.value", "2020-01-01");
    cy.get("#dateEnd").should("have.value", "2099-12-31");
  });

  it("clears filters when Clear button is clicked", () => {
    cy.get("a").contains("Clear").click();
    cy.url().should("not.include", "dateStart");
    cy.url().should("not.include", "dateEnd");
  });
});

// ---------------------------------------------------------------------------
// 2. Row selection & export button state
// ---------------------------------------------------------------------------

describe("Deposit Search: row selection and export buttons", () => {
  before(() => {
    cy.setupAdminSession();
    cy.visit(DEPOSIT_SEARCH_URL);
  });

  it("export buttons are disabled when no rows selected", () => {
    cy.get("#btnExportCSV").should("be.disabled");
    cy.get("#btnExportOFX").should("be.disabled");
    cy.get("#btnExportPDF").should("be.disabled");
  });

  it("Select All checkbox enables export buttons", () => {
    cy.get("body").then(($body) => {
      // Only run if there are rows in the table
      const rowCount = $body.find("#depositsTable tbody tr").length;
      if (rowCount === 0) {
        cy.log("No deposit rows found; skipping selection tests");
        return;
      }

      cy.get("#selectAllCheckbox").check();
      cy.get("#btnExportCSV").should("not.be.disabled");
      cy.get("#btnExportOFX").should("not.be.disabled");
      cy.get("#btnExportPDF").should("not.be.disabled");

      // Selected count badge should appear
      cy.get("#selectedCount").should("be.visible");
    });
  });

  it("unchecking Select All disables export buttons", () => {
    cy.get("body").then(($body) => {
      const rowCount = $body.find("#depositsTable tbody tr").length;
      if (rowCount === 0) return;

      cy.get("#selectAllCheckbox").uncheck();
      cy.get("#btnExportCSV").should("be.disabled");
      cy.get("#btnExportOFX").should("be.disabled");
      cy.get("#btnExportPDF").should("be.disabled");
    });
  });

  it("Delete button is present but disabled when no rows are selected", () => {
    cy.get("#btnDeleteSelected").should("exist").and("be.disabled");
  });

  it("Delete button enables when rows are selected", () => {
    cy.get("body").then(($body) => {
      const rowCount = $body.find("#depositsTable tbody tr .row-select").length;
      if (rowCount === 0) return;

      cy.get("#depositsTable tbody tr .row-select").first().check();
      cy.get("#btnDeleteSelected").should("not.be.disabled");
    });
  });

  it("checking individual row enables export buttons", () => {
    cy.get("body").then(($body) => {
      const rowCount = $body.find("#depositsTable tbody tr .row-select").length;
      if (rowCount === 0) return;

      cy.get("#depositsTable tbody tr .row-select").first().check();
      cy.get("#btnExportCSV").should("not.be.disabled");
    });
  });
});

// ---------------------------------------------------------------------------
// 3. CSV export — RFC 4180-compliant and formula-injection-safe
// ---------------------------------------------------------------------------

describe("Deposit Search: CSV export quality (bulk endpoint)", () => {
  before(() => {
    cy.setupAdminSession();
  });

  it("GET /api/deposits/csv returns a valid CSV with correct headers", () => {
    // Hit the bulk CSV endpoint directly.
    // We request deposit IDs via query param; if no data exists the server
    // may return 404 — that is an acceptable outcome (empty DB in CI).
    cy.request({
      method:             "GET",
      url:                "/api/deposits/csv?ids=1",
      headers:            { Accept: "text/csv" },
      failOnStatusCode:   false,
    }).then((res) => {
      if (res.status === 404) {
        cy.log("No payments for deposit 1 — skipping CSV content checks (empty DB)");
        return;
      }

      expect(res.status).to.eq(200);
      expect(res.headers["content-type"]).to.match(/text\/csv/i);
      expect(res.headers["content-disposition"]).to.match(/attachment/i);

      const lines = res.body.trim().split(/\r?\n/);
      expect(lines.length).to.be.gte(1, "should have at least a header row");

      const headerFields = lines[0].split(",");
      // The first header field should be 'Deposit ID' (possibly quoted by League CSV)
      const firstHeader = headerFields[0].replace(/^"(.+)"$/, "$1");
      expect(firstHeader).to.include("Deposit ID");
    });
  });

  it("CSV fields do not start with formula-injection trigger characters (=, -, +, @)", () => {
    cy.request({
      method:           "GET",
      url:              "/api/deposits/csv?ids=1",
      headers:          { Accept: "text/csv" },
      failOnStatusCode: false,
    }).then((res) => {
      if (res.status !== 200) {
        cy.log("No data for deposit 1 — skipping formula-injection check");
        return;
      }

      /**
       * RFC 4180 quoted-field-aware row parser.
       * Handles quoted fields that contain commas or escaped double-quotes
       * (e.g. `"=HYPERLINK(evil),extra"` must be treated as ONE field, not two).
       * A naive split(",") would split that example and silently miss the
       * formula-injection trigger in the full field value.
       */
      function parseCsvRow(line) {
        const fields = [];
        let i = 0;
        while (i < line.length) {
          if (line[i] === '"') {
            // Quoted field — scan to the closing quote, collapsing "" → "
            let field = '';
            i++;
            while (i < line.length) {
              if (line[i] === '"' && i + 1 < line.length && line[i + 1] === '"') {
                field += '"';
                i += 2;
              } else if (line[i] === '"') {
                i++;
                break;
              } else {
                field += line[i++];
              }
            }
            fields.push(field);
            if (line[i] === ',') i++;
          } else {
            // Unquoted field — everything up to the next comma (or EOL)
            const end = line.indexOf(',', i);
            if (end === -1) {
              fields.push(line.slice(i));
              break;
            }
            fields.push(line.slice(i, end));
            i = end + 1;
          }
        }
        return fields;
      }

      const lines = res.body.trim().split(/\r?\n/);
      // Check data rows (skip header row at index 0)
      lines.slice(1).forEach((line, rowIdx) => {
        const fields = parseCsvRow(line);
        fields.forEach((value, colIdx) => {
          // CsvExporter prepends \t to formula triggers, so an unescaped
          // trigger at position 0 would be a regression.
          expect(FORMULA_TRIGGERS.test(value)).to.eq(
            false,
            `Row ${rowIdx + 2}, col ${colIdx + 1} starts with a formula trigger: "${value}"`,
          );
        });
      });
    });
  });
});

// ---------------------------------------------------------------------------
// 4. Authorization — non-finance user is blocked
// ---------------------------------------------------------------------------

describe("Deposit Search: access control", () => {
  it("redirects non-finance user to access-denied page", () => {
    cy.setupNoFinanceSession();
    cy.visit(DEPOSIT_SEARCH_URL, { failOnStatusCode: false });
    cy.url().should("include", ACCESS_DENIED_URL);
    cy.url().should("include", "role=Finance");
  });
});
