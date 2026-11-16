// RFC 6238 authenticator codes (SHA-1, 6 digits, 30 s), computed in the
// browser with Web Crypto so a spec can enroll and confirm 2FA like an app.
const BASE32 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

const base32ToBytes = (secret) => {
    let bits = "";
    for (const char of secret.replace(/=+$/, "").toUpperCase()) {
        const value = BASE32.indexOf(char);
        if (value < 0) {
            throw new Error(`Not a base32 character: ${char}`);
        }
        bits += value.toString(2).padStart(5, "0");
    }
    const bytes = new Uint8Array(Math.floor(bits.length / 8));
    for (let i = 0; i < bytes.length; i++) {
        bytes[i] = Number.parseInt(bits.slice(i * 8, i * 8 + 8), 2);
    }
    return bytes;
};

const totpAsync = async (secret, now = Date.now()) => {
    const counter = Math.floor(now / 1000 / 30);
    const message = new ArrayBuffer(8);
    new DataView(message).setUint32(4, counter);
    const key = await crypto.subtle.importKey("raw", base32ToBytes(secret), { name: "HMAC", hash: "SHA-1" }, false, ["sign"]);
    const hmac = new Uint8Array(await crypto.subtle.sign("HMAC", key, message));
    const offset = hmac[hmac.length - 1] & 0x0f;
    const code = ((hmac[offset] & 0x7f) << 24) | (hmac[offset + 1] << 16) | (hmac[offset + 2] << 8) | hmac[offset + 3];
    return String(code % 1_000_000).padStart(6, "0");
};

/** Yields the current 6-digit code for a base32 secret. */
export const totp = (secret) => cy.wrap(totpAsync(secret), { log: false });
