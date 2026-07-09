/// <reference types="cypress" />

describe("API Private Admin OptionManager", () => {
    const listId = 1; // Classifications list

    it("GET returns options array with expected fields", () => {
        cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((resp) => {
            expect(resp.body).to.be.an("array");
            expect(resp.body.length).to.be.greaterThan(0);
            expect(resp.body[0]).to.have.property("optionId");
            expect(resp.body[0]).to.have.property("optionName");
            expect(resp.body[0]).to.have.property("optionSequence");
        });
    });

    it("POST creates a new option and returns it", () => {
        const name = "APITestOption_" + Date.now();
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
            expect(resp.body).to.have.property("optionId");
            expect(resp.body.optionName).to.eq(name);
            expect(resp.body.optionSequence).to.be.a("number");
        });
    });

    it("POST rejects duplicate names with 400", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name: "Member" }, 400);
    });

    it("POST rejects empty name with 400", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name: "" }, 400);
    });

    it("PATCH renames an option", () => {
        const name = "PatchMe_" + Date.now();
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
            const optionId = resp.body.optionId;
            const newName = "Patched_" + Date.now();
            cy.makePrivateAdminAPICall("PATCH", `/admin/api/options/${listId}/${optionId}`, { name: newName }, 200).then((patchResp) => {
                expect(patchResp.body.optionName).to.eq(newName);
            });
        });
    });

    it("DELETE removes an option and resequences", () => {
        const name = "DeleteMe_" + Date.now();
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
            const optionId = resp.body.optionId;
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${listId}/${optionId}`, null, 200);

            // Verify it no longer exists
            cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((getResp) => {
                const found = getResp.body.find((o) => o.optionId === optionId);
                expect(found).to.be.undefined;
            });
        });
    });

    it("reorder moves an option up", () => {
        cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((resp) => {
            if (resp.body.length >= 2) {
                const second = resp.body[1];
                const originalSeq = second.optionSequence;
                cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}/${second.optionId}/reorder`, { direction: "up" }, 200);

                // Verify the sequence changed
                cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((afterResp) => {
                    const movedOption = afterResp.body.find((o) => o.optionId === second.optionId);
                    expect(movedOption.optionSequence).to.eq(originalSeq - 1);
                });
            }
        });
    });
});
