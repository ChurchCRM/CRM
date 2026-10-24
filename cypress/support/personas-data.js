// Seed users by what they are for, not by the flag that makes them so.
// Specs ask for a persona (see personas.js); renaming a seed user or retiring a
// flag is a one-place change here. Design: docs/design/authz-test-strategy.md.
//
// apiKey / login are Cypress env keys when a config defines them, otherwise a
// seed literal ({ username, password }); every seed password is "changeme".
// `flags` documents the user_usr values in cypress/data/seed.sql today.

const SEED_PASSWORD = "changeme";

export const PERSONAS = {
    "church-admin": {
        principal: "staff",
        apiKey: "admin.api.key",
        login: { usernameKey: "admin.username", passwordKey: "admin.password" },
        flags: "Admin",
    },
    "staff-all-but-admin": {
        principal: "staff",
        apiKey: "user.api.key",
        login: { usernameKey: "standard.username", passwordKey: "standard.password" },
        flags: "every record, group, finance, fundraiser and notes flag; not Admin",
    },
    "office-staff": {
        principal: "staff",
        apiKey: "nofinance.api.key",
        login: { usernameKey: "nofinance.username", passwordKey: "nofinance.password" },
        flags: "Add Records, Edit Records, Manage My Ministries",
    },
    "finance-only": {
        principal: "staff",
        apiKey: "finance.only.api.key",
        login: { usernameKey: "finance.only.username", passwordKey: "finance.only.password" },
        flags: "Finance",
    },
    "finance-no-fundraisers": {
        principal: "staff",
        apiKey: "nofundraiser.api.key",
        login: { usernameKey: "nofundraiser.username", passwordKey: "nofundraiser.password" },
        flags: "Delete Records, Finance",
    },
    "groups-only": {
        principal: "staff",
        apiKey: "managegroups.only.api.key",
        login: { usernameKey: "managegroups.only.username", passwordKey: "managegroups.only.password" },
        flags: "Manage Groups",
    },
    "notes-only-staff": {
        principal: "staff",
        apiKey: "plainauth.api.key",
        login: { username: "john.plainauth@example.com", password: SEED_PASSWORD },
        flags: "Notes",
    },
    "zero-permission-staff": {
        principal: "staff",
        apiKey: "noperm.api.key",
        login: { username: "noperm.user", password: SEED_PASSWORD },
        flags: "none",
    },
    "portal-only": {
        principal: "portal",
        apiKey: "limited.api.key",
        login: { username: "limited.user", password: SEED_PASSWORD },
        flags: "Edit Self",
        // Edit Self without staff flags is confined to /portal; the internal API answers 403.
        confinedToPortal: true,
    },
    // Edit Self plus a dormant Notes flag: still a self-service account, never staff.
    "portal-only-dormant-flags": {
        principal: "portal",
        apiKey: "selfedit.plus.notes.api.key",
        login: { username: "lena.black.editself.notes@example.com", password: SEED_PASSWORD },
        flags: "Edit Self, Notes",
        confinedToPortal: true,
    },
};

export const personaNames = Object.keys(PERSONAS);

export function personaDefinition(name) {
    const persona = PERSONAS[name];
    if (!persona) {
        throw new Error(`Unknown persona "${name}". Known personas: ${personaNames.join(", ")}.`);
    }
    return persona;
}
