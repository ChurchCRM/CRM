// Pages of every staff MVC app that sits behind AuthMiddleware, first entry being the app's home.
// kiosk, external, session and setup are public or use their own authentication. portal is the
// member's own app: its pages take a member session, and /portal/login, /portal/theme and
// /portal/email/confirm are public.

export const MVC_PAGES = {
    v2: ["/v2/dashboard", "/v2/search"],
    account: ["/account", "/account/password", "/account/2fa"],
    people: [
        "/people/",
        "/people/dashboard",
        "/people/list",
        "/people/family",
        "/people/verify",
        "/people/email",
        "/people/text",
        "/people/reports",
        "/people/reports/letters-and-labels",
        "/people/map",
        "/people/photos",
        "/people/self-register",
        "/people/email/duplicate",
        "/people/email/missing",
        "/people/cart/to-family",
    ],
    groups: ["/groups/", "/groups/dashboard", "/groups/reports", "/groups/sundayschool/dashboard"],
    finance: ["/finance/", "/finance/reports", "/finance/reports/giving-statements", "/finance/reports/year-end", "/finance/funds", "/finance/pledge/new"],
    event: ["/event/dashboard", "/event/types", "/event/audit", "/event/calendars"],
    fundraiser: ["/fundraiser/", "/fundraiser/editor"],
    ministries: ["/ministries/", "/ministries/dashboard"],
    plugins: ["/plugins/management"],
    admin: [
        "/admin/",
        "/admin/people",
        "/admin/export",
        "/admin/export/csv",
        "/admin/system/users",
        "/admin/system/users/new",
        "/admin/system/options",
        "/admin/system/backup",
        "/admin/system/logs",
        "/admin/system/debug",
        "/admin/system/upgrade",
        "/admin/system/church-info",
        "/admin/property-types/new",
    ],
};

export const LEGACY_PAGES = [
    "/PersonEditor.php?PersonID=2",
    "/FamilyEditor.php?FamilyID=1",
    "/SystemSettings.php",
    "/DirectoryReports.php",
];
