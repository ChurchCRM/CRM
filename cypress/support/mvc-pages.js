// Pages of every MVC app that sits behind AuthMiddleware, first entry being the app's home.
// kiosk, external, session and setup are public or use their own authentication.

export const MVC_PAGES = {
    v2: ["/v2/dashboard", "/v2/search"],
    people: [
        "/people/",
        "/people/dashboard",
        "/people/list",
        "/people/family",
        "/people/verify",
        "/people/email",
        "/people/text",
        "/people/reports",
        "/people/map",
        "/people/photos",
        "/people/self-register",
        "/people/duplicate",
        "/people/missing",
        "/people/to-family",
    ],
    groups: ["/groups/", "/groups/dashboard", "/groups/reports", "/groups/sundayschool/dashboard"],
    finance: ["/finance/", "/finance/dashboard", "/finance/reports", "/finance/funds", "/finance/pledge/new"],
    event: ["/event/", "/event/dashboard", "/event/types", "/event/audit", "/event/calendars"],
    fundraiser: ["/fundraiser/", "/fundraiser/editor"],
    ministries: ["/ministries/", "/ministries/dashboard"],
    plugins: ["/plugins/management"],
    admin: [
        "/admin/",
        "/admin/people",
        "/admin/export",
        "/admin/system/users",
        "/admin/system/users/new",
        "/admin/system/options",
        "/admin/system/backup",
        "/admin/system/logs",
        "/admin/system/debug",
        "/admin/system/upgrade",
        "/admin/system/church-info",
    ],
};

export const LEGACY_PAGES = [
    "/PersonEditor.php?PersonID=2",
    "/FamilyEditor.php?FamilyID=1",
    "/SystemSettings.php",
    "/LettersAndLabels.php",
    "/DirectoryReports.php",
    "/Reports/ClassList.php",
];
