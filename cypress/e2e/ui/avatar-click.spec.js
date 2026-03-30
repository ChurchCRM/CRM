/// <reference types="cypress" />

describe("Avatar Click Behavior - Photo Lightbox", () => {
  beforeEach(() => cy.setupStandardSession());

  describe("Dashboard Avatar Clicks", () => {
    it("opens lightbox when clicking avatar with photo in Latest Families table", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "People"); // Wait for dashboard to load

      // Find the Latest Families tab and activate it if needed
      cy.get("#latest-fam-tab").then(($tab) => {
        if (!$tab.hasClass("active")) {
          cy.get("#latest-fam-tab").click();
        }
      });

      // Find an avatar with the view-person-photo class (has actual photo)
      cy.get("#latestFamiliesDashboardItem").within(() => {
        cy.get(".view-person-photo").first().then(($img) => {
          const personId = $img.attr("data-person-id");
          if (personId) {
            // Click the avatar
            cy.wrap($img).click();

            // Verify lightbox appears
            cy.get("#photo-lightbox").should("be.visible");
            cy.get("#photo-lightbox img").should("have.attr", "src");

            // Verify lightbox can be closed
            cy.get("#photo-lightbox .fa-times").closest("button").click();
            cy.get("#photo-lightbox").should("not.exist");
          }
        });
      });
    });

    it("opens lightbox when clicking avatar with photo in Latest People table", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "People"); // Wait for dashboard to load

      // Find the Latest People tab
      cy.get("#latest-ppl-tab").then(($tab) => {
        if (!$tab.hasClass("active")) {
          cy.get("#latest-ppl-tab").click();
        }
      });

      // Find an avatar with the view-person-photo class
      cy.get("#latestPersonDashboardItem").within(() => {
        cy.get(".view-person-photo").first().then(($img) => {
          const personId = $img.attr("data-person-id");
          if (personId) {
            cy.wrap($img).click();

            // Verify lightbox appears
            cy.get("#photo-lightbox").should("be.visible");

            // Close by pressing Escape
            cy.get("body").type("{esc}");
            cy.get("#photo-lightbox").should("not.exist");
          }
        });
      });
    });

    it("opens lightbox when clicking avatar with photo in Birthday panel", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "Birthdays");

      // Look for avatar in birthday table
      cy.get("#PersonBirthdayDashboardItem").within(() => {
        cy.get(".view-person-photo").first().then(($img) => {
          const personId = $img.attr("data-person-id");
          if (personId) {
            cy.wrap($img).click();

            // Verify lightbox appears
            cy.get("#photo-lightbox").should("be.visible");

            // Close by clicking background
            cy.get("#photo-lightbox").click({ x: 10, y: 10 });
            cy.get("#photo-lightbox").should("not.exist");
          }
        });
      });
    });

    it("lightbox closes when clicking the close button", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "People");

      cy.get("#latestFamiliesDashboardItem").within(() => {
        cy.get(".view-person-photo").first().click({ force: true });
      });

      cy.get("#photo-lightbox").should("be.visible");

      // Click the X button in the lightbox
      cy.get("#photo-lightbox .fa-times").closest("button").click();
      cy.get("#photo-lightbox").should("not.exist");
    });

    it("lightbox doesn't close when clicking the image itself", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "People");

      cy.get("#latestFamiliesDashboardItem").within(() => {
        cy.get(".view-person-photo").first().click({ force: true });
      });

      cy.get("#photo-lightbox").should("be.visible");
      const initialDisplay = cy.get("#photo-lightbox").should("be.visible");

      // Click on the image
      cy.get("#photo-lightbox img").click();

      // Lightbox should still be visible
      cy.get("#photo-lightbox").should("be.visible");
    });
  });

  describe("Person Profile Avatar Clicks", () => {
    const personId = 2; // Person with family members

    it("opens lightbox when clicking family member avatar", () => {
      cy.visit(`PersonView.php?PersonID=${personId}`);

      // Find family members table
      cy.get(".table").then(($table) => {
        // Look for avatar with view-person-photo class in family members
        cy.get(".view-person-photo").first().then(($img) => {
          const memberId = $img.attr("data-person-id");
          if (memberId) {
            cy.wrap($img).click();

            // Verify lightbox appears
            cy.get("#photo-lightbox").should("be.visible");
            cy.get("#photo-lightbox .fa-times").closest("button").click();
            cy.get("#photo-lightbox").should("not.exist");
          }
        });
      });
    });

    it("upload button still works and is not blocked by click handlers", () => {
      cy.visit(`PersonView.php?PersonID=${personId}`);

      // Verify upload button exists and can be clicked
      cy.get("#uploadImageButton").should("exist");

      // The button should have no .view-person-photo class (to avoid interference)
      cy.get("#uploadImageButton img").should("not.have.class", "view-person-photo");
    });

    it("clicking person profile photo/initials opens Uppy upload dialog", () => {
      cy.visit(`PersonView.php?PersonID=${personId}`);

      // Click on the profile photo area (the upload button wrapping the avatar)
      cy.get("#uploadImageButton").click();

      // Verify the Uppy Dashboard modal appears
      cy.get(".uppy-Dashboard--modal", { timeout: 5000 }).should("be.visible");

      // Verify webcam button is present (Webcam plugin loaded)
      cy.get(".uppy-DashboardTab-btn").should("exist");

      // Close the modal
      cy.get(".uppy-Dashboard-close").click();
      cy.get(".uppy-Dashboard--modal").should("not.exist");
    });
  });

  describe("Family Profile Avatar Clicks", () => {
    const familyId = 1;

    it("opens lightbox when clicking Key People member avatar", () => {
      cy.visit(`v2/family/${familyId}`);

      // Find Key People table
      cy.get("table").within(() => {
        cy.get(".view-person-photo").first().then(($img) => {
          const personId = $img.attr("data-person-id");
          if (personId) {
            cy.wrap($img).click();

            // Verify lightbox appears
            cy.get("#photo-lightbox").should("be.visible");
            cy.get("#photo-lightbox .fa-times").closest("button").click();
            cy.get("#photo-lightbox").should("not.exist");
          }
        });
      });
    });

    it("main family photo upload button works without interference", () => {
      cy.visit(`v2/family/${familyId}`);

      // Verify upload trigger exists and is not a lightbox trigger
      cy.get("#uploadImageTrigger").should("exist");

      // The image should not have .view-family-photo class (to avoid interference)
      cy.get("#uploadImageTrigger img").should("not.have.class", "view-family-photo");
    });

    it("clicking family profile photo/initials opens Uppy upload dialog", () => {
      cy.visit(`v2/family/${familyId}`);

      // Click on the family photo area (the upload trigger wrapping the avatar)
      cy.get("#uploadImageTrigger").click();

      // Verify the Uppy Dashboard modal appears
      cy.get(".uppy-Dashboard--modal", { timeout: 5000 }).should("be.visible");

      // Verify webcam button is present (Webcam plugin loaded)
      cy.get(".uppy-DashboardTab-btn").should("exist");

      // Close the modal
      cy.get(".uppy-Dashboard-close").click();
      cy.get(".uppy-Dashboard--modal").should("not.exist");
    });

    it("opens lightbox when clicking family member in other member tables", () => {
      cy.visit(`v2/family/${familyId}`);

      // Look through all member tables for clickable avatars
      cy.get(".view-person-photo").then(($avatars) => {
        if ($avatars.length > 0) {
          // Click the first non-upload avatar
          cy.wrap($avatars[0]).click({ force: true });
          cy.get("#photo-lightbox").should("be.visible");
          cy.get("body").type("{esc}");
          cy.get("#photo-lightbox").should("not.exist");
        }
      });
    });
  });

  describe("Lightbox Behavior", () => {
    it("lightbox image loads with correct dimensions", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "People");

      cy.get("#latestFamiliesDashboardItem").within(() => {
        cy.get(".view-person-photo").first().click({ force: true });
      });

      cy.get("#photo-lightbox img").then(($img) => {
        $img.load(() => {
          cy.wrap($img).should("have.prop", "naturalWidth").and("be.greaterThan", 0);
          cy.wrap($img).should("have.prop", "naturalHeight").and("be.greaterThan", 0);
        });
      });

      cy.get("#photo-lightbox .fa-times").closest("button").click();
      cy.get("#photo-lightbox").should("not.exist");
    });

    it("lightbox has proper styling and is centered", () => {
      cy.visit("v2/dashboard");
      cy.contains("h3", "People");

      cy.get("#latestFamiliesDashboardItem").within(() => {
        cy.get(".view-person-photo").first().click({ force: true });
      });

      cy.get("#photo-lightbox").should("have.css", "position", "fixed");
      cy.get("#photo-lightbox").should("have.css", "display", "flex");
      cy.get("#photo-lightbox").should("have.css", "z-index", "9999");

      cy.get("#photo-lightbox .fa-times").closest("button").click();
    });
  });
});
