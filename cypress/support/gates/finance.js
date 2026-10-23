const allow = ["church-admin", "staff-all-but-admin", "finance-only", "finance-no-fundraisers"];
const deny = ["office-staff", "groups-only", "notes-only-staff", "zero-permission-staff"];

export const FINANCE_GATES = [
    {
        id: "finance-app",
        audience: "standard",
        classification: "finance",
        api: { method: "GET", url: "/finance/api/funds/categories" },
        page: "/finance/",
        allow,
        deny,
    },
    {
        id: "finance-donation-funds-api",
        audience: "standard",
        classification: "finance",
        api: { method: "GET", url: "/api/donation-funds" },
        allow,
        deny,
    },
    {
        id: "finance-fiscalyear-api",
        audience: "standard",
        classification: "finance",
        api: { method: "GET", url: "/api/fiscalyear" },
        allow,
        deny,
    },
    {
        id: "finance-payments-api",
        audience: "standard",
        classification: "finance",
        api: { method: "GET", url: "/api/payments/" },
        allow,
        deny,
    },
    {
        id: "finance-deposits-api",
        audience: "standard",
        classification: "finance",
        api: { method: "GET", url: "/api/deposits/open-count" },
        allow,
        deny,
    },
];
