const allow = ["church-admin", "staff-all-but-admin"];
const deny = [
    "finance-no-fundraisers",
    "finance-only",
    "office-staff",
    "groups-only",
    "notes-only-staff",
    "zero-permission-staff",
];

export const FUNDRAISER_GATES = [
    { id: "fundraiser-app", audience: "standard", classification: "fundraising", page: "/fundraiser/", allow, deny },
    {
        id: "fundraisers-api",
        audience: "standard",
        classification: "fundraising",
        api: { method: "GET", url: "/api/fundraisers" },
        allow,
        deny,
    },
];
