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

const bytesOf = (base64) => Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
const base64UrlOf = (bytes) =>
    btoa(String.fromCharCode(...new Uint8Array(bytes)))
        .replace(/\+/g, "-")
        .replace(/\//g, "_")
        .replace(/=+$/, "");

/** WebCrypto signs ECDSA as r || s; WebAuthn sends it DER-encoded. */
const derSignature = (raw) => {
    const integer = (half) => {
        let start = 0;
        while (start < half.length - 1 && half[start] === 0) {
            start++;
        }
        const value = half.slice(start);
        return value[0] & 0x80 ? [0x02, value.length + 1, 0, ...value] : [0x02, value.length, ...value];
    };
    const body = [...integer(raw.slice(0, 32)), ...integer(raw.slice(32))];
    return new Uint8Array([0x30, body.length, ...body]);
};

/** The virtual authenticator makes an Ed25519 key when the site offers EdDSA first, else a P-256 one. */
const signerFor = async (pkcs8) => {
    const subtle = window.crypto.subtle;
    try {
        const key = await subtle.importKey("pkcs8", pkcs8, { name: "Ed25519" }, false, ["sign"]);
        return async (data) => new Uint8Array(await subtle.sign({ name: "Ed25519" }, key, data));
    } catch {
        const key = await subtle.importKey("pkcs8", pkcs8, { name: "ECDSA", namedCurve: "P-256" }, false, ["sign"]);
        return async (data) => derSignature(new Uint8Array(await subtle.sign({ name: "ECDSA", hash: "SHA-256" }, key, data)));
    }
};

/**
 * An assertion signed with a virtual authenticator credential's private key and
 * a chosen signature counter, as a synced passkey (always 0) or a cloned key
 * (a counter that went back) would send it.
 */
export const signedAssertion = (credential, { challenge, origin, rpId, signCount }) =>
    cy.wrap(
        (async () => {
            const subtle = window.crypto.subtle;
            const sign = await signerFor(bytesOf(credential.privateKey));
            const clientData = new TextEncoder().encode(JSON.stringify({ type: "webauthn.get", challenge, origin, crossOrigin: false }));
            const rpIdHash = new Uint8Array(await subtle.digest("SHA-256", new TextEncoder().encode(rpId)));
            const counter = [24, 16, 8, 0].map((shift) => (signCount >>> shift) & 0xff);
            const authenticatorData = new Uint8Array([...rpIdHash, 0x05, ...counter]);
            const clientDataHash = new Uint8Array(await subtle.digest("SHA-256", clientData));
            const signature = await sign(new Uint8Array([...authenticatorData, ...clientDataHash]));
            return {
                credentialId: base64UrlOf(bytesOf(credential.credentialId)),
                clientDataJSON: base64UrlOf(clientData),
                authenticatorData: base64UrlOf(authenticatorData),
                signature: base64UrlOf(signature),
                userHandle: base64UrlOf(bytesOf(credential.userHandle)),
            };
        })(),
        { log: false },
    );
