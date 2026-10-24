const allow = ["church-admin", "staff-all-but-admin", "groups-only"];
const deny = ["finance-only", "finance-no-fundraisers", "office-staff", "notes-only-staff", "zero-permission-staff"];

export const GROUPS_GATES = [
    { id: "groups-app", audience: "standard", classification: "groups", page: "/groups/dashboard", allow, deny },
    {
        id: "kiosk-api",
        audience: "standard",
        classification: "kiosk-devices",
        api: { method: "GET", url: "/kiosk/api/devices" },
        page: "/kiosk/admin",
        allow,
        deny,
    },
];
