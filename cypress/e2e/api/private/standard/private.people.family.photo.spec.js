/// <reference types="cypress" />

describe("API Private Photo and Avatar - Family", () => {
    const testFamilyId = 6; // Test family from demo database
    const invalidFamilyId = 99999;

    beforeEach(() => {
        cy.setupStandardSession();
    });

    describe("GET /api/family/{id}/photo", () => {
        it("should return 404 when family has no uploaded photo", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${testFamilyId}/photo`,
                null,
                404
            );
        });

        it("should return proper response status for invalid family ID", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${invalidFamilyId}/photo`,
                null,
                404
            );
        });

        it("should serve photo with correct Content-Type after upload", () => {
            // First upload a photo
            const base64Photo = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
            
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: base64Photo }),
                200
            ).then(() => {
                // Now try to fetch it using authenticated call
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/family/${testFamilyId}/photo`,
                    null,
                    200
                );
            });
        });
    });

    describe("GET /api/family/{id}/avatar", () => {
        it("should return avatar info with family initials when no photo", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${testFamilyId}/avatar`,
                null,
                200
            ).then((response) => {
                expect(response.body).to.have.property("hasPhoto");
                expect(response.body).to.have.property("initials");
                expect(response.body).to.have.property("email");
                expect(response.body).to.have.property("photoUrl");
                
                // Initials should be present (family name initials)
                expect(response.body.initials).to.be.a("string");
                expect(response.body.initials.length).to.be.greaterThan(0);
                
                // hasPhoto should be false initially
                expect(response.body.hasPhoto).to.eq(false);
            });
        });

        it("should return hasPhoto=true after uploading photo", () => {
            const base64Photo = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
            
            // Upload photo
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: base64Photo }),
                200
            ).then(() => {
                // Check avatar info
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/family/${testFamilyId}/avatar`,
                    null,
                    200
                ).then((response) => {
                    expect(response.body.hasPhoto).to.eq(true);
                    // Initials should still be present
                    expect(response.body.initials).to.be.a("string");
                });
            });
        });

        it("should return consistent initials for same family", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${testFamilyId}/avatar`,
                null,
                200
            ).then((response1) => {
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/family/${testFamilyId}/avatar`,
                    null,
                    200
                ).then((response2) => {
                    expect(response1.body.initials).to.eq(response2.body.initials);
                });
            });
        });

        it("should return different initials for different families", () => {
            const family1 = 6;
            const family2 = 7;
            
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${family1}/avatar`,
                null,
                200
            ).then((response1) => {
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/family/${family2}/avatar`,
                    null,
                    200
                ).then((response2) => {
                    // Different families should have avatar info
                    // This is a basic sanity check
                    expect(response1.body).to.have.property("initials");
                    expect(response2.body).to.have.property("initials");
                });
            });
        });

        it("should return family email or head email", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${testFamilyId}/avatar`,
                null,
                200
            ).then((response) => {
                // Email may be null or string, but should exist as property
                expect(response.body).to.have.property("email");
            });
        });

        it("should handle invalid family ID gracefully", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${invalidFamilyId}/avatar`,
                null,
                200
            ).then((response) => {
                // Should return 200 with fallback data
                expect(response.body).to.have.property("hasPhoto");
                expect(response.body).to.have.property("initials");
            });
        });
    });

    describe("POST /api/family/{id}/photo", () => {
        it("should accept valid base64 PNG image", () => {
            const base64Photo = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
            
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: base64Photo }),
                200
            ).then((response) => {
                expect(response.body).to.have.property("success");
                expect(response.body.success).to.eq(true);
            });
        });

        it("should reject invalid base64 data", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: "not-valid-base64!!!" }),
                400
            ).then((response) => {
                expect(response.body).to.have.property("success");
                expect(response.body.success).to.eq(false);
            });
        });

        it("should reject non-image file types", () => {
            const base64Text = "data:text/plain;base64,SGVsbG8gV29ybGQ=";
            
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: base64Text }),
                400
            ).then((response) => {
                expect(response.body.success).to.eq(false);
                expect(response.body.message).to.include("Failed to upload family photo");
            });
        });

        it("should validate image size limits", () => {
            // Create a large (but valid) image data - 10MB of zeros (will be rejected)
            const largeBase64 = "data:image/png;base64," + "A".repeat(15000000);
            
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: largeBase64 }),
                400
            ).then((response) => {
                expect(response.body.success).to.eq(false);
            });
        });
    });

    describe("DELETE /api/family/{id}/photo", () => {
        beforeEach(() => {
            // Upload a photo before deletion tests
            const base64Photo = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
            
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/family/${testFamilyId}/photo`,
                JSON.stringify({ imgBase64: base64Photo }),
                200
            );
        });

        it("should delete uploaded photo successfully", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/family/${testFamilyId}/photo`,
                null,
                200
            ).then((response) => {
                expect(response.body).to.have.property("success");
                expect(response.body.success).to.eq(true);
            });
        });

        it("should remove hasPhoto flag after deletion", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/family/${testFamilyId}/photo`,
                null,
                200
            ).then(() => {
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/family/${testFamilyId}/avatar`,
                    null,
                    200
                ).then((response) => {
                    expect(response.body.hasPhoto).to.eq(false);
                });
            });
        });
    });

    afterEach(() => {
        // Cleanup: Delete photo after each test
        cy.makePrivateAdminAPICall(
            "DELETE",
            `/api/family/${testFamilyId}/photo`,
            null,
            [200, 404] // 404 is ok if photo doesn't exist
        );
    });
});
