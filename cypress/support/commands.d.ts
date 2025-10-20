// ***********************************************
// Custom commands type definitions for Cypress
// This file provides TypeScript support for custom commands
// ***********************************************

declare namespace Cypress {
  interface Chainable {
    /**
     * Login as admin user
     * @param location - The location to navigate to after login
     * @param checkMatchingLocation - Whether to verify the location after login
     */
    loginAdmin(location: string, checkMatchingLocation?: boolean): Chainable<void>;

    /**
     * Login as standard user
     * @param location - The location to navigate to after login
     * @param checkMatchingLocation - Whether to verify the location after login
     */
    loginStandard(location: string, checkMatchingLocation?: boolean): Chainable<void>;

    /**
     * Generic login command
     * @param username - The username to login with
     * @param password - The password to login with
     * @param location - The location to navigate to after login
     * @param checkMatchingLocation - Whether to verify the location after login
     */
    login(
      username: string,
      password: string,
      location: string,
      checkMatchingLocation?: boolean
    ): Chainable<void>;

    /**
     * Build a random string with prefix
     * @param prefixString - The prefix to prepend to the random string
     */
    buildRandom(prefixString: string): Chainable<string>;

    /**
     * Wait for page to be fully loaded
     */
    waitForPageLoad(): Chainable<void>;

    /**
     * Get element by test ID (data-cy or data-testid)
     * @param testId - The test ID to search for
     */
    getByTestId(testId: string): Chainable<JQuery<HTMLElement>>;

    /**
     * Make API request with admin privileges
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     */
    makePrivateAdminAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number
    ): Chainable<any>;

    /**
     * Make API request with user privileges
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     */
    makePrivateUserAPICall(
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number
    ): Chainable<any>;

    /**
     * Make API request with specific API key
     * @param key - API key to use
     * @param method - HTTP method
     * @param url - Request URL
     * @param body - Request body
     * @param expectedStatus - Expected status code (default: 200)
     */
    makePrivateAPICall(
      key: string,
      method: string,
      url: string,
      body?: any,
      expectedStatus?: number
    ): Chainable<any>;

    /**
     * Modern API request command with enhanced error handling
     * @param options - Request options (same as cy.request)
     */
    apiRequest(options: any): any;

    /**
     * Create a person with specific birthday data for testing
     * @param personData - Object containing name, month, day, year for the person
     */
    createPersonWithBirthday(personData: {
      name: string;
      month: number;
      day: number;
      year?: number | null;
    }): void;

    /**
     * Delete a person by searching for their name
     * @param name - The name of the person to delete
     */
    deletePersonByName(name: string): void;

    /**
     * Create multiple people via CSV import to bypass UI validation
     * @param peopleData - Object containing person data with birth date info
     */
    createPeopleViaCSV(peopleData: Record<string, any>): void;
  }
}