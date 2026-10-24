// The member sign-in audit as Admin → Member Portal → Audit reads it
// (GET /admin/api/member-portal/audit), in its own cookie jar.

import { inOwnCookieJar } from "./cookie-jar";

const auditPage = (params) => {
    let rows = [];
    return inOwnCookieJar(() =>
        cy
            .makePrivateAdminAPICall("GET", `/admin/api/member-portal/audit?${new URLSearchParams({ length: "100", ...params })}`, null, 200)
            .then(({ body }) => {
                rows = body.rows;
            }),
    ).then(() => rows);
};

/** The id of the newest audit row; read what follows it with auditRowsSince. */
export const newestAuditId = () => auditPage({ length: "1" }).then((rows) => (rows[0] ? rows[0].id : 0));

/** The audit rows written after `afterId` that match `filters` (personId, event), oldest first. */
export const auditSince = (afterId, filters = {}) => auditPage(filters).then((rows) => rows.filter((row) => row.id > afterId).reverse());

/** The person's audit rows written after `afterId`, oldest first. */
export const auditRowsSince = (personId, afterId) => auditSince(afterId, { personId: String(personId) });

/** The person's Portal sign-in status, as the person page shows it to an administrator. */
export const signInStatus = (personId) => {
    let status = null;
    return inOwnCookieJar(() =>
        cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/sign-in`, null, 200).then(({ body }) => {
            status = body;
        }),
    ).then(() => status);
};
