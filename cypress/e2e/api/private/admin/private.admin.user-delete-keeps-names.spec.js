/// <reference types="cypress" />

/**
 * #8672: deleting a staff login keeps the name on everything that person did.
 *
 * A user id is the person id, so records that store "who did this" keep
 * pointing at the person after the login is gone. This spec gives a seeded
 * person a throwaway admin login, has that login write a note, edit a person,
 * disable someone's sign-in and queue an email, deletes the login, and reads
 * each place that shows the author's name.
 */
const STAFF = 81; // Isobel Cooper: no seeded login, used by no other spec
const NOTE_ON = 84; // Randall Riley
const EDITED = 85; // Bernard Riley
const SIGN_IN_DISABLED = 87; // Scott Riley
const EMAILED = 82; // Jesse Cooper

const tag = `keeps-names-${Date.now()}`;

describe("Deleting a user keeps the person's name on what they did (#8672)", () => {
    let staffKey;
    let staffName;
    let noteId;

    const asStaff = (method, url, body, expectedStatus = 200) => cy.makePrivateAPICall(staffKey, method, url, body, expectedStatus);
    const admin = (method, url, body = null, expectedStatus = 200) => cy.makePrivateAdminAPICall(method, url, body, expectedStatus);

    before(() => {
        admin("DELETE", `/admin/api/user/${STAFF}/`, null, [200, 404]);
        admin("GET", `/api/person/${STAFF}`).then(({ body }) => {
            staffName = body.FullName;
            expect(staffName, "the staff person's name").to.be.a("string").and.not.be.empty;
        });

        // Give the person an administrator login through the User Editor.
        cy.setupAdminSession({ forceLogin: true });
        cy.visit(`admin/system/users/new?personId=${STAFF}`);
        cy.get("input[name=csrf_token]")
            .invoke("val")
            .then((csrfToken) =>
                cy.request({
                    method: "POST",
                    url: "/admin/system/users/new",
                    form: true,
                    body: { csrf_token: csrfToken, PersonID: STAFF, UserName: tag, accessMode: "admin" },
                }),
            );
        admin("POST", `/api/user/${STAFF}/apikey/regen`).then(({ body }) => {
            staffKey = body.apiKey;
        });

        // What the staff member does before they leave.
        cy.then(() => {
            asStaff("POST", `/api/person/${NOTE_ON}/note`, { text: `${tag} note` }, 201).then(({ body }) => {
                noteId = body.note.id;
            });
            asStaff("POST", `/api/person/${EDITED}/activate/false`);
            asStaff("POST", `/api/person/${EDITED}/activate/true`);
            asStaff("POST", `/admin/api/person/${SIGN_IN_DISABLED}/sign-in/disable`, { reason: tag });
            asStaff("POST", "/api/email/send", { personIds: [EMAILED], subject: `${tag} email`, body: "From a staff member who left" }).then(
                ({ body }) => {
                    if (body.batch) {
                        asStaff("POST", `/api/email/batches/${body.batch}/send`);
                    }
                },
            );
        });

        // The response names the deleted user by their person name.
        // staffName is set by an earlier command, so read it inside the callback.
        cy.then(() =>
            admin("DELETE", `/admin/api/user/${STAFF}/`).then(({ body }) => {
                expect(body.user, "deleted user's name").to.eq(staffName);
            }),
        );
    });

    after(() => {
        admin("DELETE", `/admin/api/user/${STAFF}/`, null, [200, 404]);
        admin("POST", `/admin/api/person/${SIGN_IN_DISABLED}/sign-in/enable`, null, [200, 409]);
        admin("POST", `/api/person/${EDITED}/activate/true`);
    });

    it("refuses the deleted login's API key", () => {
        asStaff("GET", `/api/person/${NOTE_ON}`, null, 401);
    });

    it("shows the author on the note in the timeline", () => {
        admin("GET", `/api/timeline/person/${NOTE_ON}`).then(({ body }) => {
            const note = body.timeline.find((item) => item.text === `${tag} note`);
            expect(note, "the note").to.exist;
            expect(note.header).to.eq(`by ${staffName}`);
        });
    });

    it("shows the editor on the timeline entries their edit wrote", () => {
        admin("GET", `/api/timeline/person/${EDITED}`).then(({ body }) => {
            const byStaff = body.timeline.filter((item) => item.header === `by ${staffName}`);
            expect(byStaff, "entries by the deleted user").to.have.length.of.at.least(2);
        });
    });

    it("shows who disabled a person's sign-in", () => {
        admin("GET", `/api/person/${SIGN_IN_DISABLED}/sign-in`).its("body").then((status) => {
            expect(status.signInDisabled).to.be.true;
            expect(status.disabledBy).to.deep.eq({ id: STAFF, name: staffName });
        });
    });

    it("shows the administrator on the sign-in audit", () => {
        admin("GET", `/admin/api/member-portal/audit?personId=${SIGN_IN_DISABLED}&length=100`).then(({ body }) => {
            const row = body.rows.find((entry) => entry.event === "sign_in_disabled" && entry.actorId === STAFF);
            expect(row, "the sign_in_disabled row").to.exist;
            expect(row.actorName).to.eq(staffName);
        });
    });

    it("shows the sender in the email history", () => {
        admin("GET", `/api/email/log?sentBy=${STAFF}&q=${encodeURIComponent(tag)}`).then(({ body }) => {
            expect(body.rows, "the email row").to.have.length(1);
            expect(body.rows[0].sentBy).to.eq(staffName);
        });
    });

    it("names the author when an administrator deletes the note", () => {
        admin("DELETE", `/api/note/${noteId}`);
        admin("GET", `/api/timeline/person/${NOTE_ON}`).then(({ body }) => {
            expect(body.timeline.some((item) => item.text === `Note by ${staffName} deleted`)).to.be.true;
        });
    });
});
