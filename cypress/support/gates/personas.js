export const STAFF = [
    "church-admin",
    "staff-all-but-admin",
    "office-staff",
    "finance-only",
    "finance-no-fundraisers",
    "groups-only",
    "notes-only-staff",
    "zero-permission-staff",
];

export const except = (personas, ...names) => personas.filter((name) => !names.includes(name));
