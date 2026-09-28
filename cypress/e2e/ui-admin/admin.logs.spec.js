describe('Admin System Logs - UI Tests', () => {
  // The download test used to fetch whatever log file happened to be first in
  // the table. On a long-lived dev instance with ORM debug logging that is
  // hundreds of megabytes, and cy.request() buffers the whole body, so the
  // spec hung instead of failing (issue #9779). The spec now creates the log
  // it downloads: it clears the log directory, makes one request whose URL
  // carries a unique marker, and checks the advertised size before asking for
  // any bytes.
  const MARKER = `logs-spec-${Date.now()}`;

  // Generous, but four orders of magnitude below the 269 MB file that hung the
  // original test. Nothing this spec produces comes close.
  const MAX_LOG_KB = 5 * 1024;

  const LOG_LEVEL_URL = '/admin/api/system/config/sLogLevel';
  const SET_LOG_LEVEL_URL = '/admin/api/system/logs/loglevel';
  const DEBUG_LOG_LEVEL = 100;

  // Captured in before() and restored in after(). Cypress aliases do not survive
  // from before() to after(), so this has to be a closure-level binding.
  let originalLevel;

  before(() => {
    // Force DEBUG just long enough for the marker request to be recorded, then
    // put the configured level back so the rest of the spec logs normally.
    cy.makePrivateAdminAPICall('GET', LOG_LEVEL_URL, null, 200).then((response) => {
      originalLevel = response.body.value;

      cy.makePrivateAdminAPICall(
        'POST',
        SET_LOG_LEVEL_URL,
        { value: DEBUG_LOG_LEVEL },
        200,
      );

      // Start from an empty log directory — this is what bounds the download.
      cy.makePrivateAdminAPICall('DELETE', '/admin/api/system/logs', null, 200);

      // Every app-log line records the request URL in its context, so a 404 on
      // a marked path writes a small, identifiable log we can assert on.
      cy.makePrivateAdminAPICall('GET', `/api/person/999999/${MARKER}`, null, 404);

      cy.makePrivateAdminAPICall(
        'POST',
        SET_LOG_LEVEL_URL,
        { value: Number(originalLevel) },
        200,
      );
    });
  });

  // Safety net. Every call in before() is a queued Cypress command, so an
  // unexpected status on any of them aborts the queue and the restore above is
  // never reached — leaving the server at DEBUG for every spec that follows.
  // Mocha runs after() even when before() fails, and re-posting the level the
  // happy path already restored is a no-op.
  after(() => {
    if (originalLevel !== undefined) {
      cy.makePrivateAdminAPICall(
        'POST',
        SET_LOG_LEVEL_URL,
        { value: Number(originalLevel) },
        200,
      );
    }
  });

  beforeEach(() => {
    cy.setupAdminSession();
  });

  it('Should display page header', () => {
    cy.visit('admin/system/logs');
    
    // Verify page header exists (from layout framework)
    cy.contains('System Logs').should('be.visible');
  });

  it('Should display quick settings button', () => {
    cy.visit('admin/system/logs');

    // Quick Settings were moved to the header settings panel — verify settings assets load
    cy.get('link[href*="system-settings-panel.min.css"]').should('exist');
    cy.get('script[src*="system-settings-panel.min.js"]').should('exist');
  });

  it('Should display stat cards with Log Level always present', () => {
    cy.visit('admin/system/logs');
    
    // Log Level card should always be shown
    cy.get('.card-sm').should('exist');
    cy.contains('Log Level').should('be.visible');
    
    // Check for file/size/delete cards when logs exist
    cy.get('.card-body').then(($body) => {
      if ($body.find('#logFilesTable').length > 0) {
        cy.contains('Log Files').should('be.visible');
        cy.contains('Total Size').should('be.visible');
        cy.get('#deleteAllLogs').should('exist');
      }
    });
  });

  it('Should display system logs section with table or no-logs message', () => {
    cy.visit('admin/system/logs');
    
    cy.get('.card-body, .alert').then(($body) => {
      if ($body.find('.alert-info').length > 0) {
        // No logs case - alert shown directly without card header
        cy.get('.alert-info').should('contain', 'No log files found');
      } else if ($body.find('#logFilesTable').length > 0) {
        // Logs exist case - card with header and table
        cy.get('.card').contains('Log Files').should('exist');
        cy.get('#logFilesTable').should('be.visible');
        cy.get('#logFilesTable thead th').should('have.length', 4);
        cy.get('#logFilesTable thead th').eq(0).should('contain', 'Log File');
        cy.get('#logFilesTable thead th').eq(1).should('contain', 'Size');
        cy.get('#logFilesTable thead th').eq(2).should('contain', 'Last Modified');
        cy.get('#logFilesTable thead th').eq(3).should('contain', 'Actions');

        // Verify action buttons exist in the first table row
        cy.get('#logFilesTable tbody tr').first().within(() => {
          cy.get('.view-log').should('exist');
          cy.get('.download-log').should('exist');
          cy.get('.delete-log').should('exist');
        });

        // Verify delete all button exists when logs are present
        cy.get('#deleteAllLogs').should('exist');
      }
    });
  });

  it('Should open log viewer modal when viewing a log file', () => {
    cy.visit('admin/system/logs');

    // Skip when no log files exist (table is conditionally rendered in PHP)
    cy.get('body').then(($body) => {
      if ($body.find('#logFilesTable tbody tr').length === 0) {
        // Assert that there are indeed no log rows before skipping modal check
        expect($body.find('#logFilesTable tbody tr').length).to.eq(0);
        cy.log('No log files found — skipping log viewer modal test');
        return;
      }

      // Open the dropdown and click View — wait for Bootstrap dropdown to render with .show class
      cy.get('#logFilesTable tbody tr').first().within(() => {
        cy.get('button[data-bs-toggle="dropdown"], .dropdown-toggle').first().click();
      });
      
      // Wait for Bootstrap to show the dropdown menu before interacting with its items
      cy.get('.dropdown-menu.show').within(() => {
        cy.get('.view-log').first().should('be.visible').click();
      });

      // Verify modal is displayed and has the proper title
      cy.get('#logViewerModal').should('be.visible');
      cy.get('#logViewerModalLabel').should('contain', 'Log File Viewer');
    });
  });

  it('Should download the log file it created, bounded by the advertised size', () => {
    cy.visit('admin/system/logs');

    // before() guarantees at least the application log exists, so an empty
    // table is a failure, not a reason to skip.
    cy.get('#logFilesTable tbody tr').should('have.length.at.least', 1);

    cy.get('#logFilesTable tbody tr')
      .then(($rows) => {
        const appLog = [...$rows]
          .map((row) => ({
            name: row.querySelector('.download-log')?.getAttribute('data-log-name'),
            // Size column renders as "12.34 KB" via number_format(), which adds
            // thousands separators once a file passes 1000 KB.
            sizeKb: Number.parseFloat(
              (row.children[1].textContent || '').replace(/,/g, ''),
            ),
          }))
          .find((entry) => entry.name && entry.name.endsWith('-app.log'));

        expect(appLog, 'application log row in the table').to.not.be.undefined;
        // Read the size off the page and refuse to download an unbounded file.
        // This is the check that would have failed fast on the 269 MB log
        // instead of hanging.
        // .a('number') alone would pass for NaN (typeof NaN === 'number'), and
        // the bound below would then fail with "expected NaN to be less than
        // 5120" instead of naming the real problem: an unparseable size cell.
        // satisfy(Number.isFinite) says that directly and, unlike
        // `.and.not.to.be.NaN`, keeps the chain positive — chai's negation flag
        // survives .and, so a `not` here would invert the lessThan that follows.
        expect(appLog.sizeKb, `size of ${appLog.name} in KB`)
          .to.be.a('number')
          .and.to.satisfy(Number.isFinite)
          .and.to.be.lessThan(MAX_LOG_KB);

        return cy.wrap(appLog.name, { log: false });
      })
      .then((logName) => {
        cy.window().then((win) => {
          const origin =
            win.location && win.location.origin
              ? win.location.origin
              : Cypress.config('baseUrl');
          const rootPath = win.CRM && win.CRM.root ? win.CRM.root : '/';
          const relative =
            rootPath +
            (rootPath.endsWith('/') ? '' : '/') +
            'admin/api/system/logs/' +
            encodeURIComponent(logName) +
            '/download';
          const url = new URL(relative, origin).toString();

          cy.request({
            url,
            encoding: 'utf8',
            failOnStatusCode: false,
            // An explicit timeout so an oversized file fails the test rather
            // than hanging the run.
            timeout: 20000,
          }).then((resp) => {
            expect(resp.status, `download status for ${logName} (${url})`).to.equal(200);

            const headers = resp.headers || {};
            const cd = headers['content-disposition'] || headers['Content-Disposition'];
            expect(cd, 'Content-Disposition header present').to.be.a('string');
            expect(cd.toLowerCase(), 'Content-Disposition mentions attachment').to.include(
              'attachment',
            );
            expect(cd, 'Content-Disposition names the file').to.include(logName);

            // The route sets Content-Length from the raw file, but Apache
            // gzips text/plain, so what arrives is the compressed length and
            // it will not match the decoded body. Assert both are present and
            // inside the bound rather than that they are equal.
            const contentLength = Number(
              headers['content-length'] || headers['Content-Length'],
            );
            expect(contentLength, 'Content-Length header').to.be.greaterThan(0);
            expect(contentLength, 'Content-Length within the bound').to.be.lessThan(
              MAX_LOG_KB * 1024,
            );
            expect(resp.body.length, 'decoded body within the bound')
              .to.be.greaterThan(10)
              .and.to.be.lessThan(MAX_LOG_KB * 1024);

            // First bytes: a Monolog line opens with an ISO-8601 timestamp.
            expect(resp.body.slice(0, 200), 'first log line').to.match(
              /^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/,
            );
            // And it is the log this spec created, not whatever was lying around.
            expect(resp.body, 'log contains the marker written in before()').to.include(
              MARKER,
            );
          });
        });
      });
  });

});
