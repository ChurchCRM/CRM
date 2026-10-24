import { STAFF } from "./personas";

export const EVENT_GATES = [
    {
        id: "event-app",
        audience: "standard",
        classification: "events",
        page: "/event/dashboard",
        allow: STAFF,
        deny: [],
    },
];
