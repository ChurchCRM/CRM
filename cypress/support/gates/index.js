import { ADMIN_GATES } from "./admin";
import { EVENT_GATES } from "./event";
import { FINANCE_GATES } from "./finance";
import { FUNDRAISER_GATES } from "./fundraiser";
import { GROUPS_GATES } from "./groups";
import { NOTES_GATES } from "./notes";

export const GATES = [
    ...ADMIN_GATES,
    ...FINANCE_GATES,
    ...FUNDRAISER_GATES,
    ...GROUPS_GATES,
    ...NOTES_GATES,
    ...EVENT_GATES,
];
