const allow = ["church-admin", "staff-all-but-admin", "office-staff"];
const deny = ["finance-only", "finance-no-fundraisers", "groups-only", "notes-only-staff", "zero-permission-staff"];

const row = (id, url) => ({
    id,
    audience: "standard",
    classification: "people-records",
    api: { method: "GET", url },
    allow,
    deny,
});

export const PEOPLE_GATES = [
    row("people-properties-person-definitions-api", "/api/people/properties/person"),
    row("people-properties-family-definitions-api", "/api/people/properties/family"),
    row("people-properties-person-api", "/api/people/properties/person/2"),
    row("people-properties-family-api", "/api/people/properties/family/1"),
];

// Per-route record gates. A refused call stops in the middleware, so every refusal is safe to
// send to a seeded record. A route that mutates is denyOnly: the runner sends it to the refused
// personas and leaves the allowed ones to the functional specs.
const editAllow = ["church-admin", "staff-all-but-admin", "office-staff"];
const editDeny = ["finance-no-fundraisers", "groups-only", "zero-permission-staff"];
const deleteAllow = ["church-admin", "staff-all-but-admin", "finance-no-fundraisers"];
const deleteDeny = ["office-staff", "zero-permission-staff"];

const recordRow = (id, method, url, gate, body = {}) => ({
    id,
    audience: "standard",
    classification: gate === "delete" ? "record-delete" : "record-edit",
    api: { method, url, body },
    denyOnly: true,
    allow: gate === "delete" ? deleteAllow : editAllow,
    deny: gate === "delete" ? deleteDeny : editDeny,
});

export const RECORD_GATES = [
    recordRow("person-photo-upload-api", "POST", "/api/person/2/photo", "edit"),
    recordRow("person-activate-api", "POST", "/api/person/2/activate/false", "edit"),
    recordRow("person-role-api", "POST", "/api/person/2/role/1", "edit"),
    recordRow("person-approve-review-api", "POST", "/api/person/2/approve-review", "edit"),
    recordRow("person-add-to-cart-api", "POST", "/api/person/2/addToCart", "edit"),
    recordRow("person-photo-delete-api", "DELETE", "/api/person/2/photo", "delete"),
    recordRow("person-delete-api", "DELETE", "/api/person/2", "delete"),
    recordRow("family-photo-upload-api", "POST", "/api/family/1/photo", "edit"),
    recordRow("family-photo-delete-api", "DELETE", "/api/family/1/photo", "edit"),
    recordRow("family-verify-api", "POST", "/api/family/1/verify", "edit"),
    recordRow("family-verify-url-api", "GET", "/api/family/1/verify/url", "edit", null),
    recordRow("family-verify-now-api", "POST", "/api/family/1/verify/now", "edit"),
    recordRow("family-activate-api", "POST", "/api/family/1/activate/false", "edit"),
    recordRow("family-approve-review-api", "POST", "/api/family/1/approve-review", "edit"),
    recordRow("family-geocode-api", "POST", "/api/family/1/geocode", "edit"),
    recordRow("family-delete-api", "DELETE", "/api/family/1", "delete"),
    recordRow("persons-duplicate-emails-api", "GET", "/api/persons/duplicate/emails", "edit", null),
    recordRow("persons-self-register-approve-api", "POST", "/api/persons/self-register/approve", "edit"),
    recordRow("families-self-verify-api", "GET", "/api/families/self-verify", "edit", null),
    recordRow("families-pending-self-verify-api", "GET", "/api/families/pending-self-verify", "edit", null),
    recordRow("families-verify-email-preview-api", "GET", "/api/families/verify-email-preview", "edit", null),
];

const emailAllow = ["church-admin", "staff-all-but-admin", "office-staff"];
const emailDeny = ["finance-no-fundraisers", "groups-only", "zero-permission-staff"];

const emailRow = (id, probe) => ({
    id,
    audience: "standard",
    classification: "email",
    ...probe,
    ...(probe.api ? { denyOnly: true } : {}),
    allow: emailAllow,
    deny: emailDeny,
});

export const EMAIL_GATES = [
    emailRow("email-send-api", { api: { method: "POST", url: "/api/email/send", body: {} } }),
    emailRow("email-preview-api", { api: { method: "POST", url: "/api/email/preview", body: {} } }),
    emailRow("email-batch-api", { api: { method: "GET", url: "/api/email/batches/00000000000000000000000000000000" } }),
    emailRow("email-batch-send-api", {
        api: { method: "POST", url: "/api/email/batches/00000000000000000000000000000000/send", body: {} },
    }),
    emailRow("cart-emails-api", { api: { method: "GET", url: "/api/cart/emails" } }),
    emailRow("people-emails-api", { api: { method: "GET", url: "/api/people/emails" } }),
    emailRow("group-emails-api", { api: { method: "GET", url: "/api/groups/1/emails" } }),
    emailRow("group-sundayschool-emails-api", { api: { method: "GET", url: "/api/groups/1/sundayschool/emails" } }),
    emailRow("people-email-page", { page: "/people/email/dashboard" }),
    emailRow("people-text-page", { page: "/people/text/dashboard" }),
];
