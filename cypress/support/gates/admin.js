import { STAFF, except } from "./personas";

export const ADMIN_GATES = [
    {
        id: "admin-app",
        audience: "admin",
        classification: "configuration",
        api: { method: "GET", url: "/admin/api/system/config/sChurchName" },
        page: "/admin/",
        allow: ["church-admin"],
        deny: except(STAFF, "church-admin"),
    },
];
