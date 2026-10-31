// The member sign-in audit as Admin → Member Portal → Audit reads it
// (GET /admin/api/member-portal/audit), in its own cookie jar.

import { inOwnCookieJar } from "./cookie-jar";

const auditPage = (params) => {
    let body = null;
    return inOwnCookieJar(() =>
        cy
            .makePrivateAdminAPICall("GET", `/admin/api/member-portal/audit?${new URLSearchParams({ length: "100", ...params })}`, null, 200)
            .then((response) => {
                body = response.body;
            }),
    ).then(() => body);
};

const allPages = (params, rows = []) =>
    auditPage({ ...params, start: String(rows.length) }).then((page) => {
        const read = rows.concat(page.rows);
        return page.rows.length === 0 || read.length >= page.filtered ? read : allPages(params, read);
    });

/** The id of the newest audit row; read what follows it with auditRowsSince. */
export const newestAuditId = () => auditPage({ length: "1" }).then(({ rows }) => (rows[0] ? rows[0].id : 0));

/** The audit rows written after `afterId` that match `filters` (personId, event), oldest first. */
export const auditSince = (afterId, filters = {}) => allPages({ ...filters, afterId: String(afterId) }).then((rows) => rows.reverse());

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
