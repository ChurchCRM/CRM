/// <reference types="cypress" />

/**
 * Member Portal API (MP4, #9865) — `/api/portal/*`.
 *
 * The portal's API is session-only by design: `PortalApiMiddleware` refuses an
 * API key outright, so these tests establish a real browser session with the
 * login form and then use `cy.request()`, which shares that session's cookie.
 * No `x-api-key` is sent on those calls, so the PHP session survives them.
 *
 * What is under test (design §5.2, P11, P12):
 *   - the acting person is the session and nothing else: a `personId`,
 *     `familyId` or `admin` in the body is ignored, not obeyed and not 400
 *   - the family is the actor's own family; another family is untouched
 *   - only an adult of the family may write to it (403 otherwise)
 *   - an API-key caller is refused
 *   - every write leaves a timeline note
 *   - `<script>` in a field is stripped before it is stored
 *
 * Personas: Lena Black (user 100, person 100, family 20, role 2 = Spouse, an
 * adult of her family) and limited.user (user 4, person 4, family 1, role 4 =
 * Other Relative, not an adult of his).
 */
describe("Member Portal API — /api/portal/me and /api/portal/family", () => {
    const adultUser = "lena.black.editself.notes@exampl";
    const nonAdultUser = "limited.user";
    const password = "changeme";

    const LENA_PERSON_ID = 100;
    const LENA_FAMILY_ID = 20;
    const OTHER_FAMILY_ID = 1;

    /** Sign in with the login form so cy.request() inherits a real session. */
    const portalLogin = (username) => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(username);
        cy.get("input[name=Password]").type(password + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/portal");
    };

    /**
     * The session-wide CSRF token, read out of a rendered portal form the same
     * way a browser would.
     */
    const withCsrfToken = (callback) => {
        cy.request("/portal/profile/edit").then((response) => {
            const match = response.body.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i);
            expect(match, "csrf_token hidden field present in the portal form").to.not.be.null;
            callback(match[1]);
        });
    };

    const portalPost = (url, body, token, expectedStatus) =>
        cy
            .request({
                method: "POST",
                url,
                body,
                failOnStatusCode: false,
                headers: { "content-type": "application/json", "X-CSRF-Token": token },
            })
            .then((response) => {
                expect(response.status).to.eq(expectedStatus);
                return response;
            });

    describe("Who may call it", () => {
        it("refuses an API-key caller, even an administrator's key", () => {
            cy.makePrivateAdminAPICall("GET", "/api/portal/me", null, 403);
        });

        it("refuses an Edit-Self account's API key too", () => {
            // AuthMiddleware already blocks an EditSelf-exclusive key before
            // the portal middleware is reached; either way the answer is 403.
            cy.makePrivateEditSelfAPICall("GET", "/api/portal/me", null, 403);
        });

        it("answers a signed-in member with their own record", () => {
            portalLogin(adultUser);
            cy.request("/api/portal/me").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.profile.id).to.eq(LENA_PERSON_ID);
                expect(response.body.profile.familyId).to.eq(LENA_FAMILY_ID);
                expect(response.body.profile.firstName).to.eq("Lena");
            });
        });

        it("rejects a write with no CSRF token", () => {
            portalLogin(adultUser);
            cy.request({
                method: "POST",
                url: "/api/portal/me",
                body: { cellPhone: "(206) 555-0000" },
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            })
                .its("status")
                .should("eq", 403);
        });
    });

    describe("POST /api/portal/me — the allow-list", () => {
        it("saves an allow-listed field and reports what changed", () => {
            const phone = `(206) 555-${String(Date.now()).slice(-4)}`;
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/me", { cellPhone: phone }, token, 200).then((response) => {
                    expect(response.body.success).to.eq(true);
                    expect(response.body.updated).to.deep.eq(["cellPhone"]);
                    expect(response.body.profile.cellPhone).to.eq(phone);
                });
            });
        });

        it("ignores personId, familyId and admin in the body instead of obeying or rejecting them", () => {
            const phone = `(206) 555-${String(Date.now()).slice(-4)}`;
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost(
                    "/api/portal/me",
                    {
                        personId: 1,
                        id: 1,
                        familyId: OTHER_FAMILY_ID,
                        famId: OTHER_FAMILY_ID,
                        admin: true,
                        EnteredBy: 1,
                        cellPhone: phone,
                    },
                    token,
                    200,
                ).then((response) => {
                    // The allow-listed field went through …
                    expect(response.body.updated).to.deep.eq(["cellPhone"]);
                    // … and nothing else moved: still Lena, still family 20.
                    expect(response.body.profile.id).to.eq(LENA_PERSON_ID);
                    expect(response.body.profile.familyId).to.eq(LENA_FAMILY_ID);
                });
            });

            // Person 1 (the seeded Church Admin) was not touched.
            cy.makePrivateAdminAPICall("GET", "/api/person/1", null, 200).then((response) => {
                expect(response.body.CellPhone || "").to.not.eq(phone);
            });
        });

        it("strips markup out of a submitted value", () => {
            const attack = '<script>alert("xss")</script>Portal';
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/me", { workPhone: attack }, token, 200).then((response) => {
                    expect(response.body.profile.workPhone).to.not.contain("<script");
                    expect(response.body.profile.workPhone).to.not.contain("</script");
                });
            });
        });

        it("refuses a first name the person record cannot accept", () => {
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/me", { firstName: "L" }, token, 400).then((response) => {
                    expect(response.body.success).to.eq(false);
                    expect(response.body.failures).to.be.an("array").and.not.be.empty;
                });
            });
            // The stored record is unchanged.
            cy.makePrivateAdminAPICall("GET", `/api/person/${LENA_PERSON_ID}`, null, 200).then((response) => {
                expect(response.body.FirstName).to.eq("Lena");
            });
        });

        it("writes a Member Portal note on the person", () => {
            const phone = `(206) 555-${String(Date.now()).slice(-4)}`;
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/me", { cellPhone: phone }, token, 200);
            });

            // The portal note is a type='edit' audit entry, so it belongs to
            // the timeline rather than to /notes (which lists type='note' only).
            cy.makePrivateAdminAPICall("GET", `/api/timeline/person/${LENA_PERSON_ID}`, null, 200).then((response) => {
                expect(JSON.stringify(response.body.timeline)).to.contain("Edited via the Member Portal");
            });
        });
    });

    describe("POST /api/portal/family — the adult-of-the-family rule", () => {
        it("lets an adult save the family address, ignoring a familyId in the body", () => {
            const address = `${String(Date.now()).slice(-5)} Avondale Ave`;
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost(
                    "/api/portal/family",
                    { familyId: OTHER_FAMILY_ID, id: OTHER_FAMILY_ID, address1: address },
                    token,
                    200,
                ).then((response) => {
                    expect(response.body.family.id).to.eq(LENA_FAMILY_ID);
                    expect(response.body.family.address1).to.eq(address);
                });
            });

            // The family named in the body is untouched — there was never a
            // parameter that could have reached it.
            cy.makePrivateAdminAPICall("GET", `/api/family/${OTHER_FAMILY_ID}`, null, 200).then((response) => {
                expect(response.body.Address1).to.not.eq(address);
            });
        });

        it("refuses a member of the family who is not one of its adults", () => {
            portalLogin(nonAdultUser);
            cy.request("/api/portal/family").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.canEdit).to.eq(false);
            });
            withCsrfToken((token) => {
                portalPost("/api/portal/family", { address1: "999 Nope St" }, token, 403).then((response) => {
                    expect(response.body.success).to.eq(false);
                });
            });

            cy.makePrivateAdminAPICall("GET", `/api/family/${OTHER_FAMILY_ID}`, null, 200).then((response) => {
                expect(response.body.Address1).to.not.eq("999 Nope St");
            });
        });

        it("writes a Member Portal note on the family", () => {
            const zip = String(Date.now()).slice(-5);
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/family", { zip }, token, 200);
            });

            cy.makePrivateAdminAPICall("GET", `/api/timeline/family/${LENA_FAMILY_ID}`, null, 200).then((response) => {
                expect(JSON.stringify(response.body.timeline)).to.contain("Edited via the Member Portal");
            });
        });
    });

    describe("POST /api/portal/family/confirm", () => {
        it("refuses a change request with no comment", () => {
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/family/confirm", { result: "change-needed" }, token, 400);
            });
        });

        it("records a no-change confirmation as the verify flow's own note", () => {
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/family/confirm", { result: "no-change" }, token, 200).then((response) => {
                    expect(response.body.success).to.eq(true);
                });
            });

            cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((response) => {
                const notes = response.body.families || [];
                expect(notes.length, "the verify dashboard lists the portal confirmation").to.be.greaterThan(0);
                expect(notes.some((note) => note.FamId === LENA_FAMILY_ID)).to.eq(true);
            });
        });
    });

    describe("POST /api/portal/family/members", () => {
        it("creates a pending self-registration in the actor's own family", () => {
            const firstName = `Api${String(Date.now()).slice(-6)}`;
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost(
                    "/api/portal/family/members",
                    { firstName, lastName: "Black", role: 3, familyId: OTHER_FAMILY_ID },
                    token,
                    200,
                ).then((response) => {
                    expect(response.body.success).to.eq(true);
                    expect(response.body.personId).to.be.greaterThan(0);

                    cy.makePrivateAdminAPICall("GET", `/api/person/${response.body.personId}`, null, 200).then(
                        (person) => {
                            expect(person.body.FirstName).to.eq(firstName);
                            // The actor's family, not the one named in the body.
                            expect(person.body.FamId).to.eq(LENA_FAMILY_ID);
                            // Pending, never live: Person::SELF_REGISTER is -1.
                            expect(person.body.EnteredBy).to.eq(-1);
                        },
                    );
                });
            });
        });

        it("refuses a member who is not an adult of the family", () => {
            portalLogin(nonAdultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/family/members", { firstName: "Nope", lastName: "Nope" }, token, 403);
            });
        });

        it("refuses a name the person record cannot accept", () => {
            portalLogin(adultUser);
            withCsrfToken((token) => {
                portalPost("/api/portal/family/members", { firstName: "A", lastName: "B" }, token, 400).then(
                    (response) => {
                        expect(response.body.failures).to.be.an("array").and.not.be.empty;
                    },
                );
            });
        });
    });
});
