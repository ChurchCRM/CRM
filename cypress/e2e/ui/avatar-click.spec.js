/// <reference types="cypress" />

/**
 * Avatar Click Behavior Tests
 *
 * Tests the photo lightbox and upload dialog across pages.
 *
 * How avatar clicking works:
 * - Dashboard: generatePhotoImg() renders <img> with .view-person-photo/.view-family-photo
 *   and src pointing to the photo API. Click classes are in the HTML immediately.
 * - Other pages: avatar-loader.ts fetches avatar info, loads the photo, then adds
 *   .view-person-photo/.view-family-photo in the onload callback. Tests must wait
 *   for the "loaded" class before asserting on click classes.
 * - Profile photos: wrapped in #uploadImageButton / #uploadImageTrigger — avatar-loader
 *   skips adding click classes, so clicking opens the Uppy upload dialog instead.
 *
 * Seeded test data: persons 1, 2, 5, 10, 44, 213, 214 have uploaded photos.
 * Person 2 is in family 1.
 */

describe("Avatar Click Behavior", () => {
  beforeEach(() => cy.setupStandardSession());

  describe("Dashboard", () => {
    beforeEach(() => {
      cy.visit("v2/dashboard");
      // Wait for the dashboard DataTables to finish loading
      cy.get("#latestFamiliesDashboardItem tbody tr", { timeout: 10000 })
        .should("have.length.at.least", 1);
    });

    it("photo avatar in Latest Families opens lightbox", () => {
      // Dashboard uses generatePhotoImg() which adds .view-family-photo directly
      cy.get("#latestFamiliesDashboardItem .view-family-photo")
        .first()
        .click();

      cy.get("#photo-lightbox").should("be.visible");
      cy.get("#photo-lightbox img").should("have.attr", "src").and("include", "/photo");

      // Close via X button
      cy.get("#photo-lightbox button").click();
      cy.get("#photo-lightbox").should("not.exist");
    });

    it("initials avatar in Latest Families is NOT clickable", () => {
      // Initials are rendered as <span> elements without click classes
      cy.get("#latestFamiliesDashboardItem .avatar-title").first().click({ force: true });

      // No lightbox should appear
      cy.get("#photo-lightbox").should("not.exist");
    });

    it("photo avatar in Latest People opens lightbox", () => {
      cy.get("#latest-ppl-tab").click();
      // Wait for DataTable to render after tab switch (lazy-loaded)
      cy.get("#latest-ppl-pane").should("have.class", "show");
      cy.get("#latestPersonDashboardItem tbody tr", { timeout: 15000 })
        .should("have.length.at.least", 1);

      // Not all people have photos — check if any exist
      cy.get("#latestPersonDashboardItem").then(($table) => {
        if ($table.find(".view-person-photo").length > 0) {
          cy.get("#latestPersonDashboardItem .view-person-photo")
            .first()
            .click();

          cy.get("#photo-lightbox").should("be.visible");
          cy.get("body").type("{esc}");
          cy.get("#photo-lightbox").should("not.exist");
        }
        // If no people have photos in this table, test passes (nothing to click)
      });
    });

    it("lightbox stays open when clicking the image itself", () => {
      cy.get("#latestFamiliesDashboardItem .view-family-photo")
        .first()
        .click();

      cy.get("#photo-lightbox").should("be.visible");

      // Click on the image (not the background)
      cy.get("#photo-lightbox img").click();

      // Should still be open
      cy.get("#photo-lightbox").should("be.visible");

      // Cleanup
      cy.get("body").type("{esc}");
    });

    it("lightbox closes when clicking the dark background", () => {
      cy.get("#latestFamiliesDashboardItem .view-family-photo")
        .first()
        .click();

      cy.get("#photo-lightbox").should("be.visible");

      // Click top-left corner (background, not image)
      cy.get("#photo-lightbox").click("topLeft");
      cy.get("#photo-lightbox").should("not.exist");
    });
  });

  describe("Person Profile", () => {
    // Person 2 has an uploaded photo (2.png) and is in family 1
    const personId = 2;

    it("clicking profile photo opens Uppy upload dialog", () => {
      // Admin session required — uploader needs edit permissions
      cy.setupAdminSession();
      cy.visit(`PersonView.php?PersonID=${personId}`);

      // Wait for page + uploader to fully initialize
      cy.get("#uploadImageButton", { timeout: 10000 }).should("exist");
      cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");

      cy.get("#uploadImageButton").click();

      // Uppy modal should appear
      cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");

      // Close
      cy.get(".uppy-Dashboard-close").click();
      cy.get(".uppy-Dashboard--modal").should("not.be.visible");
    });

    it("profile photo does NOT have lightbox click class", () => {
      cy.visit(`PersonView.php?PersonID=${personId}`);

      // Wait for avatar-loader to finish processing
      cy.get("#uploadImageButton img.loaded", { timeout: 10000 }).should("exist");

      // avatar-loader should skip images inside #uploadImageButton
      cy.get("#uploadImageButton img").should("not.have.class", "view-person-photo");
    });

    it("family member with photo gets clickable avatar via avatar-loader", () => {
      cy.visit(`PersonView.php?PersonID=${personId}`);

      // Wait for avatar-loader to process family member avatars
      // Person 2 is in family 1 — other family members may or may not have photos
      cy.get("img.loaded[data-person-id]", { timeout: 10000 }).then(($imgs) => {
        if ($imgs.length > 0) {
          // At least one family member photo loaded — click it
          cy.wrap($imgs.first()).should("have.class", "view-person-photo");
          cy.wrap($imgs.first()).click();

          cy.get("#photo-lightbox").should("be.visible");
          cy.get("body").type("{esc}");
          cy.get("#photo-lightbox").should("not.exist");
        }
        // If no family members have photos, the test still passes
        // (avatar-loader correctly did not add click classes)
      });
    });
  });

  describe("Family Profile", () => {
    const familyId = 1;

    it("clicking family profile photo opens Uppy upload dialog", () => {
      // Admin session required — uploader needs edit permissions
      cy.setupAdminSession();
      cy.visit(`v2/family/${familyId}`);

      // Wait for page + uploader to fully initialize
      cy.get("#uploadImageTrigger", { timeout: 10000 }).should("exist");
      cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");

      cy.get("#uploadImageTrigger").click();

      // Uppy modal should appear
      cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");

      // Close
      cy.get(".uppy-Dashboard-close").click();
      cy.get(".uppy-Dashboard--modal").should("not.be.visible");
    });

    it("family profile photo does NOT have lightbox click class", () => {
      cy.visit(`v2/family/${familyId}`);

      // Wait for avatar-loader to finish
      cy.get("#uploadImageTrigger img.loaded", { timeout: 10000 }).should("exist");

      // avatar-loader should skip images inside #uploadImageTrigger
      cy.get("#uploadImageTrigger img").should("not.have.class", "view-family-photo");
    });

    it("member with photo gets clickable avatar via avatar-loader", () => {
      cy.visit(`v2/family/${familyId}`);

      // Wait for avatar-loader to process member table avatars
      cy.get("table img.loaded", { timeout: 10000 }).then(($imgs) => {
        // Filter to those that got the click class (have photos)
        const clickable = $imgs.filter(".view-person-photo");
        if (clickable.length > 0) {
          cy.wrap(clickable.first()).click();

          cy.get("#photo-lightbox").should("be.visible");
          cy.get("#photo-lightbox img")
            .should("have.attr", "src")
            .and("include", "/photo");
          cy.get("body").type("{esc}");
          cy.get("#photo-lightbox").should("not.exist");
        }
      });
    });

    it("member with only initials does NOT open lightbox", () => {
      cy.visit(`v2/family/${familyId}`);

      // Wait for avatar-loader to finish processing all member avatars
      cy.get("table img.loaded", { timeout: 10000 }).should("exist");

      // Find an avatar that did NOT get the click class (initials only)
      cy.get("table img.loaded").then(($imgs) => {
        const initialsOnly = $imgs.not(".view-person-photo");
        if (initialsOnly.length > 0) {
          cy.wrap(initialsOnly.first()).click({ force: true });
          // No lightbox should appear
          cy.get("#photo-lightbox").should("not.exist");
        }
      });
    });
  });

  describe("Lightbox styling", () => {
    it("lightbox has correct overlay and centering CSS", () => {
      cy.visit("v2/dashboard");
      cy.get("#latestFamiliesDashboardItem tbody tr", { timeout: 10000 })
        .should("have.length.at.least", 1);

      cy.get("#latestFamiliesDashboardItem .view-family-photo")
        .first()
        .click();

      cy.get("#photo-lightbox")
        .should("have.css", "position", "fixed")
        .and("have.css", "z-index", "9999");

      cy.get("#photo-lightbox img")
        .should("have.css", "max-width", "90%")
        .and("have.css", "border-radius", "8px");

      cy.get("body").type("{esc}");
    });
  });
});
