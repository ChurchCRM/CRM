// Chrome's virtual authenticator for the passkey specs (#10463, design §17
// "Passkeys"), through the DevTools protocol of the browser Cypress drives.
// It acts as this device's platform authenticator: it keeps discoverable
// credentials, verifies the user and answers without a prompt.

const devtools = (command, params = {}) =>
    cy.wrap(null, { log: false }).then(() => Cypress.automation("remote:debugger:protocol", { command, params }));

export const addVirtualAuthenticator = () =>
    devtools("WebAuthn.enable", { enableUI: false })
        .then(() =>
            devtools("WebAuthn.addVirtualAuthenticator", {
                options: {
                    protocol: "ctap2",
                    transport: "internal",
                    hasResidentKey: true,
                    hasUserVerification: true,
                    isUserVerified: true,
                    automaticPresenceSimulation: true,
                },
            }),
        )
        .its("authenticatorId");

export const removeVirtualAuthenticator = (authenticatorId) =>
    devtools("WebAuthn.removeVirtualAuthenticator", { authenticatorId }).then(() => devtools("WebAuthn.disable"));

export const authenticatorCredentials = (authenticatorId) =>
    devtools("WebAuthn.getCredentials", { authenticatorId }).its("credentials");

/** The authenticator refuses the next ceremonies, as a member pressing Cancel would. */
export const setUserVerified = (authenticatorId, isUserVerified) =>
    devtools("WebAuthn.setUserVerified", { authenticatorId, isUserVerified });
