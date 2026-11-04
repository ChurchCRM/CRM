import { STAFF, except } from "./personas";

export const ADMIN_GATES = [
    {
        id: "admin-app",
        audience: "admin",
        classification: "configuration",
        api: { method: "GET", url: "/admin/api/system/config/sChurchName" },
        // The /admin root is the authenticated admin dashboard.
        page: "/admin/system/users",
        allow: ["church-admin"],
        deny: except(STAFF, "church-admin"),
    },
];
