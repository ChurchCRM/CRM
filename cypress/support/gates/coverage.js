// Every PHP file under src/ that attaches a role-auth middleware, and how its gate is tested.
//   rows:    ids of rows in GATES
//   spec:    an existing spec that already pins the gate
//   pending: why no row exists yet
// gate-coverage.spec.js fails on an unlisted file or a stale entry, and on more pending entries than MAX_PENDING.

export const MAX_PENDING = 12;

const APP_GATE = { rows: ["admin-app"], note: "redundant with the admin app gate" };
const ADD_EVENT_ROLE = { pending: "AddEvents and CalendarWrite need a persona with bAddEvent" };
const RECORD_ROUTES = { pending: "the Manage Groups and Email gates on group routes need a group fixture" };
const VOLUNTEER = "cypress/e2e/api/private/standard/private.volunteer";

export const COVERAGE = {
    "src/admin/index.php": { rows: ["admin-app"] },
    "src/admin/routes/api/import.php": APP_GATE,
    "src/admin/routes/api/system/system-config.php": APP_GATE,
    "src/admin/routes/api/user-admin.php": APP_GATE,
    "src/admin/routes/import.php": APP_GATE,

    "src/finance/index.php": { rows: ["finance-app"] },
    "src/api/routes/finance/finance-deposits.php": { rows: ["finance-deposits-api"] },
    "src/api/routes/finance/finance-donation-funds.php": { rows: ["finance-donation-funds-api"] },
    "src/api/routes/finance/finance-fiscalyear.php": { rows: ["finance-fiscalyear-api"] },
    "src/api/routes/finance/finance-payments.php": { rows: ["finance-payments-api"] },
    "src/api/routes/finance/finance-fundraisers.php": { rows: ["fundraisers-api"] },
    "src/fundraiser/index.php": { rows: ["fundraiser-app"] },

    "src/groups/index.php": { rows: ["groups-app"] },
    "src/kiosk/routes/admin.php": { rows: ["kiosk-api"] },
    "src/kiosk/routes/api/kiosks.php": { rows: ["kiosk-api"] },
    "src/api/routes/people/groups-properties.php": { spec: "cypress/e2e/api/private/standard/private.groups.properties.spec.js" },
    "src/api/routes/people/groups-form-props.php": { pending: "Manage Groups per-route gate needs a group fixture" },

    "src/api/routes/people/notes.php": {
        rows: ["notes-api", "notes-family-api"],
        spec: "cypress/e2e/api/private/standard/private.notes.visibility.spec.js",
        note: "the single-note group needs a note id",
    },
    "src/api/routes/people/people-properties.php": {
        rows: [
            "people-properties-person-definitions-api",
            "people-properties-family-definitions-api",
            "people-properties-person-api",
            "people-properties-family-api",
        ],
    },
    "src/api/routes/people/people-families.php": { rows: ["families-self-verify-api", "families-pending-self-verify-api", "families-verify-email-preview-api"] },
    "src/api/routes/people/people-family.php": { rows: ["family-photo-upload-api", "family-photo-delete-api", "family-verify-api", "family-verify-url-api", "family-verify-now-api", "family-activate-api", "family-approve-review-api", "family-geocode-api", "family-delete-api"] },
    "src/api/routes/people/people-groups.php": RECORD_ROUTES,
    "src/api/routes/people/people-person.php": { rows: ["person-photo-upload-api", "person-activate-api", "person-role-api", "person-approve-review-api", "person-add-to-cart-api", "person-photo-delete-api", "person-delete-api"] },
    "src/api/routes/people/people-sign-in-email.php": { rows: ["sign-in-email-api"] },
    "src/api/routes/people/people-persons.php": { rows: ["persons-duplicate-emails-api", "persons-self-register-approve-api", "people-emails-api"] },
    "src/api/routes/map.php": { spec: "cypress/e2e/api/private/standard/private.map.geocode-all.spec.js" },
    "src/api/routes/cart.php": { rows: ["cart-emails-api"] },
    "src/api/routes/email.php": { rows: ["email-send-api", "email-preview-api", "email-batch-api", "email-batch-send-api"] },
    "src/people/routes/email.php": { rows: ["people-email-page"] },
    "src/people/routes/text.php": { rows: ["people-text-page"] },
    "src/people/routes/sign-in-email.php": { rows: ["sign-in-email-page"] },
    "src/people/routes/reports.php": { spec: "cypress/e2e/ui/reports/people-reports.staff-access.spec.js" },

    "src/event/index.php": { rows: ["event-app"] },
    "src/event/routes/audit.php": ADD_EVENT_ROLE,
    "src/event/routes/editor.php": ADD_EVENT_ROLE,
    "src/event/routes/event.php": ADD_EVENT_ROLE,
    "src/event/routes/list-events.php": ADD_EVENT_ROLE,
    "src/event/routes/repeat-editor.php": ADD_EVENT_ROLE,
    "src/event/routes/types.php": ADD_EVENT_ROLE,
    "src/api/routes/calendar/calendar.php": ADD_EVENT_ROLE,
    "src/api/routes/calendar/events.php": ADD_EVENT_ROLE,

    "src/api/routes/ministries/ministries-assignment.php": { spec: `${VOLUNTEER}.assignment.spec.js` },
    "src/api/routes/ministries/ministries-dashboard.php": { spec: `${VOLUNTEER}.dashboard.spec.js` },
    "src/api/routes/ministries/ministries-events.php": { spec: `${VOLUNTEER}.ministry-events.spec.js` },
    "src/api/routes/ministries/ministries-schedule.php": { spec: `${VOLUNTEER}.schedule.spec.js` },
    "src/api/routes/ministries/ministries-scopes.php": { spec: `${VOLUNTEER}.authorization.spec.js` },
    "src/api/routes/ministries/ministries-setup.php": { spec: `${VOLUNTEER}.setup-api.spec.js` },
    "src/ministries/routes/dashboard.php": { spec: "cypress/e2e/ui/groups/volunteer-v2.coordinator.spec.js" },
    "src/ministries/routes/ministry.php": { spec: "cypress/e2e/ui/groups/volunteer-v2.coordinator.spec.js" },
    "src/ministries/routes/occurrence.php": { spec: "cypress/e2e/ui/groups/volunteer-v2.coordinator.spec.js" },

    "src/plugins/index.php": { spec: "cypress/e2e/ui/plugins/plugin-management.spec.js" },
    "src/plugins/core/custom-links/routes/routes.php": { pending: "plugin routes need a plugin fixture" },
    "src/plugins/core/external-backup/routes/routes.php": { pending: "plugin routes need a plugin fixture" },
};
