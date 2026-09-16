// ***********************************************
// Custom commands type definitions for Cypress
//
// This file must stay in sync with the real commands registered in
// cypress/support/api-commands.js and cypress/support/ui-commands.js.
// `npm run lint:cypress-commands` (also run in CI) fails when a command is
// declared here but not defined, or defined but not declared.
// ***********************************************

declare namespace Cypress {
  interface Chainable {
    // ---------------------------------------------------------------
    // Session / login commands (cypress/support/ui-commands.js)
    // ---------------------------------------------------------------

    /**
     * Create or reuse a cached login session
     * @param sessionName - Unique identifier for the cached session
     * @param username - Credential username
     * @param password - Credential password
     * @param options - Optional flags (forceLogin bypasses cached session)
     */
    setupLoginSession(
      sessionName: string,
      username: string,
      password: string,
      options?: { forceLogin?: boolean; validate?: () => void }
    ): Chainable<void>;

    /**
     * Ensure an admin session is active (optionally forcing a fresh login)
     */
    setupAdminSession(options?: { forceLogin?: boolean }): Chainable<void>;

    /**
     * Ensure a standard session is active (optionally forcing a fresh login)
     */
    setupStandardSession(options?: { forceLogin?: boolean }): Chainable<void>;

    /**
     * Ensure a no-finance user session is active (optionally forcing a fresh login)
     * Used to test that finance pages correctly deny access to non-finance users
     */
    setupNoFinanceSession(options?: { forceLogin?: boolean }): Chainable<void>;

    /**
     * Ensure a no-ManageFundraisers user session is active (optionally forcing a fresh login)
     * Used to test that fundraiser pages correctly deny access to users without ManageFundraisers permission
     */
    setupNoManageFundraisersSession(options?: { forceLogin?: boolean }): Chainable<void>;

    /**
     * Ensure a Finance-only (non-admin) user session is active.
     * finance.only user (id=904): Finance=1, Admin=0.
     * Used to verify Finance role can access fund CRUD and dashboard features without Admin.
     */
    setupFinanceOnlySession(options?: { forceLogin?: boolean }): Chainable<void>;

    /**
     * Ensure a ManageGroups-only (non-admin) user session is active.
     * managegroups.only user (id=905): ManageGroups=1, Admin=0.
     * Used to verify ManageGroups role can access Kiosk Manager without Admin.
     */
    setupManageGroupsOnlySession(options?: { forceLogin?: boolean }): Chainable<void>;

    /**
     * Log in with arbitrary credentials in a dedicated cached session.
     * Set expectSuccess to false to skip the CRM cookie validation when the
     * credentials are expected to be rejected.
     * @param username - The username to authenticate with
     * @param password - The password to authenticate with
     * @param sessionName - cy.session() cache key (default: 'custom-session')
     * @param expectSuccess - Whether a successful login is expected (default: true)
     */
    loginWithCredentials(
      username: string,
      password: string,
      sessionName?: string,
      expectSuccess?: boolean
    ): Chainable<void>;

    /**
     * Set the locale-admin user's ui.locale preference to localeValue and establish
     * an authenticated browser session for that user.
     *
     * localeValue must be the locale field from src/locale/locales.json
     * (e.g. 'ar_EG', 'zh_CN', 'de_DE' — NOT the poEditor code).
     *
     * Designed for locale smoke tests only; never touches system-wide sLanguage config.
     * @param localeValue - The locale field value from locales.json
     */
    setupLocaleAdminSession(localeValue: string): Chainable<void>;

    // ---------------------------------------------------------------
    // Generic helpers (cypress/support/ui-commands.js)
    // ---------------------------------------------------------------

    /**
     * Build a random string with prefix
     * @param prefixString - The prefix to prepend to the random string
     */
    buildRandom(prefixString: string): Chainable<string>;

    /**
     * Get element by test ID (data-cy or data-testid)
     * @param testId - The test ID to search for
     */
    getByTestId(testId: string): Chainable<JQuery<HTMLElement>>;

    // ---------------------------------------------------------------
    // API commands (cypress/support/api-commands.js)
    // ---------------------------------------------------------------

    /**
     * Make API request with admin privileges
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     * @param timeoutMs - Optional per-request timeout override
     */
    makePrivateAdminAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request with user privileges
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     * @param timeoutMs - Optional per-request timeout override
     */
    makePrivateUserAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as Finance-only user (grace.financeonly, Finance=1, Admin=0).
     * Used to verify Finance-role-but-not-Admin can access /finance/api/funds CRUD.
     */
    makePrivateFinanceOnlyAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as ManageGroups-only user (kyle.kioskonly, ManageGroups=1, Admin=0).
     * Used to verify ManageGroups-role can access /kiosk/api/* endpoints.
     */
    makePrivateManageGroupsOnlyAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as a Finance=0 user.
     * Used to assert that finance endpoints deny access to non-finance users.
     */
    makePrivateNoFinanceAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as a Finance=1 / ManageFundraisers=0 user (seed user per_ID=96).
     * Used to assert that fundraiser endpoints require the ManageFundraisers permission.
     */
    makePrivateNoManageFundraisersAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as a plain authenticated user (john.plainauth, no EditSelf/EditRecords/Admin)
     * Used for testing that read access is available to all authenticated users by default.
     * User has Notes=1 (passes AuthMiddleware) but no edit or admin capabilities.
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     * @param timeoutMs - Optional per-request timeout override
     */
    makePrivatePlainAuthAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as an EditSelf-only user (amanda.black, family 20)
     * Used for testing family-scope authorization (GHSA-jjcj-h3cm-p7x7)
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     * @param timeoutMs - Optional per-request timeout override
     */
    makePrivateEditSelfAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Regression sentinel: EditSelf+Notes user (user 100, Lena Black, family 20).
     * Post-PR#9016 the user is blocked by AuthMiddleware (403). Once EditSelf
     * exclusivity is relaxed, avatar/nav/photo should assert 200 (FamilyReadMiddleware)
     * vs 403 (FamilyMiddleware) for a non-own family.
     */
    makePrivateEditSelfPlusNotesAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as limited.user (id=4) — usr_Notes=0, usr_Admin=0,
     * usr_EditRecords=0, usr_EditSelf=1. An EditSelf-ONLY user, blocked by
     * AuthMiddleware::isEditSelfExclusive() → always returns 403.
     * Use ONLY for Notes-gated 403 assertions.
     *
     * NOT a zero-permission user — that is noperm.user (id=901, EditSelf=0),
     * which now passes the gate with read-only access (read-default policy).
     */
    makePrivateLimitedAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request as judith.matthews (id=95) — usr_EditRecords=1,
     * usr_Notes=0, usr_Admin=0. Passes AuthMiddleware (has EditRecords) but
     * canReadNotes() returns false. Use for timeline "200 but notes stripped"
     * assertions.
     */
    makePrivateEditRecordsAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * MenuOptions-only user (id=902): usr_MenuOptions=1, all other perm flags 0,
     * non-admin, non-EditSelf. Used to verify the EditRecords gate on person/family
     * property routes (GHSA-4wmp-3v34-g7q8). Passes MenuOptions middleware but is
     * blocked by EditRecordsRoleAuthMiddleware — expects 403 on record-level routes.
     */
    makePrivateMenuOptionsAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Make API request with specific API key
     * @param key - API key to use
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     * @param timeoutMs - Optional per-request timeout override
     */
    makePrivateAPICall(
      key: string,
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number | number[],
      timeoutMs?: number
    ): Chainable<any>;

    /**
     * Modern API request command with enhanced error handling
     * @param options - Request options (same as cy.request)
     */
    apiRequest(options: any): Chainable<any>;

    // ---------------------------------------------------------------
    // Test-data helpers (cypress/support/ui-commands.js)
    // ---------------------------------------------------------------

    /**
     * Create a person with specific birthday data for testing
     * @param personData - Object containing name, month, day, year for the person
     */
    createPersonWithBirthday(personData: {
      name: string;
      month: number;
      day: number;
      year?: number | null;
    }): Chainable<void>;

    /**
     * Delete a person by searching for their name
     * @param name - The name of the person to delete
     */
    deletePersonByName(name: string): Chainable<void>;

    /**
     * Create multiple people via CSV import to bypass UI validation
     * @param peopleData - Object containing person data with birth date info
     */
    createPeopleViaCSV(peopleData: Record<string, any>): Chainable<void>;

    // ---------------------------------------------------------------
    // TomSelect commands (cypress/support/ui-commands.js)
    // ---------------------------------------------------------------

    /**
     * Wait for TomSelect to initialize on an element
     * @param selector - CSS selector for the original select element
     */
    tomSelectReady(selector: string): Chainable<void>;

    /**
     * Select an option in a TomSelect dropdown by visible text
     * @param selector - CSS selector for the original select element
     * @param text - The text of the option to select
     */
    tomSelectByText(selector: string, text: string): Chainable<void>;

    /**
     * Select an option in a TomSelect dropdown by value (programmatically).
     * An empty string clears the selection.
     * @param selector - CSS selector for the original select element
     * @param value - The value (or values) of the option to select
     */
    tomSelectByValue(
      selector: string,
      value: string | number | Array<string | number>
    ): Chainable<void>;

    /**
     * Type and search in a TomSelect with remote/AJAX data
     * @param selector - CSS selector for the original select element
     * @param searchText - Text to search for
     * @param resultText - Text of the result to click (clicks the first result when omitted)
     */
    tomSelectSearch(
      selector: string,
      searchText: string,
      resultText?: string | null
    ): Chainable<void>;

    /**
     * Verify TomSelect is initialized on an element
     * @param selector - CSS selector for the original select element
     */
    tomSelectIsInitialized(selector: string): Chainable<void>;

    /**
     * Clear a TomSelect selection
     * @param selector - CSS selector for the original select element
     */
    tomSelectClear(selector: string): Chainable<void>;

    /**
     * Get the selected item elements of a TomSelect control
     * @param selector - CSS selector for the original select element
     */
    tomSelectGetSelected(selector: string): Chainable<JQuery<HTMLElement>>;

    // ---------------------------------------------------------------
    // Legacy select2 aliases — thin wrappers over the tomSelect* commands
    // ---------------------------------------------------------------

    /** @deprecated Use tomSelectByText */
    select2ByText(selector: string, text: string): Chainable<void>;

    /** @deprecated Use tomSelectByValue */
    select2ByValue(
      selector: string,
      value: string | number | Array<string | number>
    ): Chainable<void>;

    /** @deprecated Use tomSelectSearch */
    select2Search(
      selector: string,
      searchText: string,
      resultText?: string | null
    ): Chainable<void>;

    /** @deprecated Use tomSelectClear */
    select2Clear(selector: string): Chainable<void>;

    /** @deprecated Use tomSelectGetSelected */
    select2GetSelected(selector: string): Chainable<JQuery<HTMLElement>>;

    /** @deprecated Use tomSelectIsInitialized */
    select2HasTheme(selector: string): Chainable<void>;

    // ---------------------------------------------------------------
    // Quill editor commands (cypress/support/ui-commands.js)
    // ---------------------------------------------------------------

    /**
     * Type text into a Quill editor (contenteditable div) via Quill's setContents API
     * @param editorId - The HTML ID of the Quill editor container (without #)
     * @param text - The text to insert
     */
    typeInQuill(editorId: string, text: string): Chainable<void>;

    /**
     * Get text content from a Quill editor
     * @param editorId - The HTML ID of the Quill editor container (without #)
     */
    getQuillText(editorId: string): Chainable<string>;

    /**
     * Clear content from a Quill editor
     * @param editorId - The HTML ID of the Quill editor container (without #)
     */
    clearQuill(editorId: string): Chainable<void>;

    // ---------------------------------------------------------------
    // Misc UI commands (cypress/support/ui-commands.js)
    // ---------------------------------------------------------------

    /**
     * Set a Bootstrap Datepicker value by typing and blurring to trigger the change event
     * @param selector - The CSS selector for the datepicker input
     * @param dateString - The date string in MM/DD/YYYY format
     */
    setDatePickerValue(selector: string, dateString: string): Chainable<void>;

    /**
     * Wait for ChurchCRM locales (i18next) to be fully loaded
     * @param timeout - Maximum time to wait in milliseconds (default: 10000)
     */
    waitForLocales(timeout?: number): Chainable<void>;

    /**
     * Wait for a Notyf notification with specific text
     * Ensures locales are loaded first (for i18next translations) and verifies notification content
     * @param expectedText - The text to find in the notification
     * @param options - Optional config { timeout: 5000 }
     */
    waitForNotification(expectedText: string, options?: { timeout?: number }): Chainable<void>;
  }
}
