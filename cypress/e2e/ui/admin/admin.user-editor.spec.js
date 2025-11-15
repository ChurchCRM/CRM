describe("User Editor - ORM Migration Tests", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Should edit user permissions and persist via ORM", () => {
        // Edit existing admin user (PersonID 1 always exists)
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");
        
        // Modify a permission
        cy.get('#Finance').check();
        cy.get('#SaveButton').click();
        
        // Wait for page to load
        cy.wait(500);
        
        // Reload page and verify ORM loaded the updated value
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#Finance').should('be.checked');
        
        // Uncheck to clean up
        cy.get('#Finance').uncheck();
        cy.get('#SaveButton').click();
        cy.wait(500);
    });

    it("Should handle ORM user update with multiple permission changes", () => {
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");
        
        // Update multiple fields at once (tests ORM object state management)
        cy.get('#AddRecords').check();
        cy.get('#EditRecords').check();
        cy.get('#Notes').check();
        cy.get('#SaveButton').click();
        
        cy.wait(500);
        
        // Reload and verify ORM persisted all changes
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#AddRecords').should('be.checked');
        cy.get('#EditRecords').should('be.checked');
        cy.get('#Notes').should('be.checked');
        
        // Clean up
        cy.get('#AddRecords').uncheck();
        cy.get('#EditRecords').uncheck();
        cy.get('#Notes').uncheck();
        cy.get('#SaveButton').click();
        cy.wait(500);
    });

    it("Should update username via ORM", () => {
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");
        
        // Get original username
        cy.get('#UserName').invoke('val').then((originalUsername) => {
            const newUsername = 'admin_orm_test';
            
            // Update username
            cy.get('#UserName').clear().type(newUsername);
            cy.get('#SaveButton').click();
            cy.wait(500);
            
            // Verify ORM persisted the change
            cy.visit('UserEditor.php?PersonID=1');
            cy.get('#UserName').should('have.value', newUsername);
            
            // Reset to original
            cy.get('#UserName').clear().type(originalUsername);
            cy.get('#SaveButton').click();
            cy.wait(500);
        });
    });

    it("Should persist user style selection via ORM", () => {
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");
        
        // Change style
        cy.get('#Style').select('skin-purple');
        cy.get('#SaveButton').click();
        cy.wait(500);
        
        // Reload and verify ORM loaded the style
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#Style').should('have.value', 'skin-purple');
        
        // Reset to default
        cy.get('#Style').select('skin-blue');
        cy.get('#SaveButton').click();
        cy.wait(500);
    });
});
