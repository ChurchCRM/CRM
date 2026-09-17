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
 *   - photos are served by the portal itself, because a member session may
 *     not read `/api/person/{id}/photo`, and a photo outside the member's
 *     own family is 404, never 403
 *
 * Personas: Lena Black (user 100, person 100, family 20, role 2 = Spouse, an
 * adult of her family) and limited.user (user 4, person 4, family 1, role 4 =
 * Other Relative, not an adult of his).
 */
describe("Member Portal API — /api/portal/me and /api/portal/family", () => {
    const adultUser = "lena.black.editself.notes@example.com";
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

    describe("Photos — GET /api/portal/me/photo and /api/portal/family/members/{id}/photo", () => {
        // A member session is confined to /portal and /api/portal by
        // AuthMiddleware::isLimitedAccessAllowedPath(), so /api/person/{id}/photo
        // — where the portal used to point every <img src> — answers it with 403
        // and the avatar renders broken. The portal serves its own photo bytes
        // instead; these tests are the contract for that.
        //
        // Seeded photo files live in cypress/data/images/people, which the test
        // stack bind-mounts as Images/Person. Lena (person 100) deliberately has
        // no tracked fixture: the tests below upload her one through the portal,
        // the same way the member does, so nothing here overwrites a tracked file
        // (issue #9777). Samantha (person 102, the same family) does have one and
        // is only ever read.
        const PNG_1PX =
            "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

        const FAMILY_MEMBER_WITH_PHOTO = 102; // Samantha Black, family 20
        const FAMILY_MEMBER_WITHOUT_PHOTO = 103; // Serenity Black, family 20
        const OUTSIDE_PERSON_WITH_PHOTO = 44; // Rhonda Diaz, family 9
        const MISSING_PERSON = 987654;

        /** GET an image URL on the current session, without failing on 404. */
        const getPhoto = (url) => cy.request({ url, encoding: "binary", failOnStatusCode: false });

        const expectImage = (response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.match(/^image\//);
            expect(response.body.length, "the response carries image bytes").to.be.greaterThan(0);
        };

        /** Give the acting member a photo through the portal's own upload route. */
        const uploadOwnPhoto = () => {
            withCsrfToken((token) => {
                portalPost("/api/portal/me/photo", { imgBase64: PNG_1PX }, token, 200);
            });
        };

        it("refuses an API-key caller, like every other portal route", () => {
            cy.makePrivateAdminAPICall("GET", "/api/portal/me/photo", null, 403);
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/portal/family/members/${FAMILY_MEMBER_WITH_PHOTO}/photo`,
                null,
                403,
            );
        });

        it("serves the member their own photo, right after they upload it", () => {
            portalLogin(adultUser);
            uploadOwnPhoto();
            getPhoto("/api/portal/me/photo").then(expectImage);
        });

        it("hands out a photo URL the member's own session can actually fetch", () => {
            // The regression this block exists for: the profile used to carry
            // /api/person/{id}/photo, which the member's own session may not read.
            portalLogin(adultUser);
            uploadOwnPhoto();
            cy.request("/api/portal/me").then((profile) => {
                const photoUrl = profile.body.profile.photoUrl;
                expect(photoUrl, "the profile carries a photo URL").to.match(/\/api\/portal\/me\/photo/);
                getPhoto(photoUrl).then(expectImage);
            });
        });

        it("404s for a member who has never uploaded a photo", () => {
            portalLogin(nonAdultUser); // person 4 has no photo file
            getPhoto("/api/portal/me/photo").its("status").should("eq", 404);
        });

        it("serves the photo of somebody in the member's own family", () => {
            portalLogin(adultUser);
            getPhoto(`/api/portal/family/members/${FAMILY_MEMBER_WITH_PHOTO}/photo`).then(expectImage);
        });

        it("hands out family photo URLs the member's own session can fetch", () => {
            portalLogin(adultUser);
            cy.request("/api/portal/family").then((family) => {
                const withPhotos = family.body.members.filter((member) => member.photoUrl);
                expect(withPhotos.length, "at least one seeded family member has a photo").to.be.greaterThan(0);
                for (const member of withPhotos) {
                    expect(member.photoUrl).to.match(/\/api\/portal\/(me|family\/members\/\d+)\/photo/);
                    getPhoto(member.photoUrl).then(expectImage);
                }
            });
        });

        it("404s for a member of the family who has no photo", () => {
            portalLogin(adultUser);
            getPhoto(`/api/portal/family/members/${FAMILY_MEMBER_WITHOUT_PHOTO}/photo`).its("status").should("eq", 404);
        });

        it("404s — not 403 — for a person outside the member's family", () => {
            // 404 rather than 403 on purpose: a 403 would confirm that the id
            // names a real person who has a photo, which is exactly what a
            // member outside that family must not learn.
            portalLogin(adultUser);
            getPhoto(`/api/portal/family/members/${OUTSIDE_PERSON_WITH_PHOTO}/photo`).its("status").should("eq", 404);
        });

        it("404s for a person id that does not exist, the same way", () => {
            portalLogin(adultUser);
            getPhoto(`/api/portal/family/members/${MISSING_PERSON}/photo`).its("status").should("eq", 404);
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
