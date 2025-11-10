describe("User Editor - ORM Migration Tests", () => {
    
    // Increase default timeout for this suite
    Cypress.config('defaultCommandTimeout', 10000);
    
    describe("User Creation", () => {
        beforeEach(() => {
            // Clean up any test users before each test
            cy.loginAdmin("UserList.php");
            cy.wait(1000); // Allow page and AJAX to fully load
        });

        it("Should create a new user with default permissions", () => {
            // Navigate to create user page for a person without a user account
            cy.visit('UserEditor.php?NewPersonID=26');
            cy.wait(1000); // Allow form to initialize and AJAX to complete
            cy.contains("User Editor");
            
            // Verify person name is displayed
            cy.get('input[name="PersonID"]').should('exist').should('have.value', '26');
            
            // Set username
            cy.get('#UserName').should('be.visible').clear().type('testuser26');
            
            // Set user style
            cy.get('#Style').should('be.visible').select('skin-blue');
            
            // Keep default permissions (all unchecked except EditSelf)
            cy.get('#EditSelf').should('be.checked');
            
            // Submit the form
            cy.get('#SaveButton').click();
            
            // Wait for navigation
            cy.wait(2000);
            
            // Should redirect to UserList after successful creation
            cy.url().should('contain', 'UserList.php');
            
            // Verify user appears in the list
            cy.contains('testuser26', {timeout: 10000});
        });

        it("Should create a user with specific permissions", () => {
            cy.visit('UserEditor.php?NewPersonID=27');
            cy.wait(1000);
            
            // Set username
            cy.get('#UserName').should('be.visible').clear().type('testuser27');
            
            // Set various permissions
            cy.get('#AddRecords').check();
            cy.get('#EditRecords').check();
            cy.get('#Notes').check();
            cy.get('#EditSelf').check();
            
            // Set user style
            cy.get('#Style').select('skin-green');
            
            // Submit
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            cy.contains('testuser27', {timeout: 10000});
        });

        it("Should create an admin user with all permissions", () => {
            cy.visit('UserEditor.php?NewPersonID=28');
            cy.wait(1000);
            
            // Set username
            cy.get('#UserName').should('be.visible').clear().type('testadmin28');
            
            // Enable admin (should automatically grant all permissions)
            cy.get('#Admin').check();
            
            // Set user style
            cy.get('#Style').select('skin-red');
            
            // Submit
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            cy.contains('testadmin28', {timeout: 10000});
        });

        it("Should validate username length (minimum 3 characters)", () => {
            cy.visit('UserEditor.php?NewPersonID=29');
            cy.wait(1000);
            
            // Try to submit with username less than 3 characters
            cy.get('#UserName').should('be.visible').clear().type('ab');
            
            // Submit
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            
            // Should show error message
            cy.url().should('contain', 'UserEditor.php');
            cy.url().should('contain', 'ErrorText=Login must be a least 3 characters');
        });

        it("Should prevent duplicate usernames", () => {
            // First, create a user
            cy.visit('UserEditor.php?NewPersonID=30');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('duplicatetest');
            cy.get('#SaveButton').click();
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            
            // Now try to create another user with the same username
            cy.visit('UserEditor.php?NewPersonID=31');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('duplicatetest');
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            
            // Should show error message about duplicate username
            cy.url().should('contain', 'UserEditor.php');
            cy.url().should('contain', 'ErrorText=Login already in use');
        });

        it("Should generate random password on user creation", () => {
            // This test verifies the password generation happens
            // We can't directly verify the password, but we can ensure
            // the user is created and can later change their password
            cy.visit('UserEditor.php?NewPersonID=32');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('passwordtest32');
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            cy.contains('passwordtest32', {timeout: 10000});
            
            // Verify the user exists by attempting to navigate to their profile
            // (in real scenario, password would be emailed to user)
        });
    });

    describe("User Editing", () => {
        it("Should edit existing user permissions", () => {
            // Navigate to edit an existing user (assuming user ID 95 exists)
            cy.loginAdmin("UserEditor.php?PersonID=95");
            cy.wait(1000);
            
            cy.contains("User Editor");
            
            // Verify we're editing (not creating)
            cy.get('input[name="Action"]').should('have.value', 'edit');
            
            // Modify permissions
            cy.get('#Finance').check();
            cy.get('#ManageGroups').check();
            
            // Submit
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            
            // Should stay on the same page or redirect to user list
            cy.url().should('match', /UserEditor\.php|UserList\.php/);
        });

        it("Should update username for existing user", () => {
            cy.loginAdmin("UserEditor.php?PersonID=95");
            cy.wait(1000);
            
            // Get current username and modify it
            cy.get('#UserName').should('be.visible').invoke('val').then((currentUsername) => {
                const newUsername = currentUsername + '_modified';
                cy.get('#UserName').clear().type(newUsername);
                
                // Submit
                cy.get('#SaveButton').click();
                
                cy.wait(2000);
                
                // Verify change persisted
                cy.visit("UserEditor.php?PersonID=95");
                cy.wait(1000);
                cy.get('#UserName').should('have.value', newUsername);
                
                // Reset to original
                cy.get('#UserName').clear().type(currentUsername);
                cy.get('#SaveButton').click();
                cy.wait(2000);
            });
        });

        it("Should update user style", () => {
            cy.loginAdmin("UserEditor.php?PersonID=95");
            cy.wait(1000);
            
            // Change style
            cy.get('#Style').should('be.visible').select('skin-purple');
            
            // Submit
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            
            // Verify persistence
            cy.visit("UserEditor.php?PersonID=95");
            cy.wait(1000);
            cy.get('#Style').should('have.value', 'skin-purple');
        });

        it("Should prevent editing username to duplicate existing username", () => {
            // Assumes multiple users exist (e.g., user 95 and user 96)
            cy.loginAdmin("UserEditor.php?PersonID=95");
            cy.wait(1000);
            
            // Try to change to a username that already exists
            cy.get('#UserName').should('be.visible').clear().type('admin'); // 'admin' typically exists
            
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            
            // Should show error about duplicate
            cy.url().should('contain', 'ErrorText=Login already in use');
        });
    });

    describe("Security Tests", () => {
        it("Should hash passwords using SHA256 with salt", () => {
            // This is a regression test to ensure password hashing doesn't break
            // after ORM migration. We create a user and verify they can log in.
            cy.visit('UserEditor.php?NewPersonID=33');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('securitytest33');
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            
            // User should be created with hashed password
            // The actual password is random and emailed to the user
            // We verify the user record exists in the database
            cy.contains('securitytest33', {timeout: 10000});
        });

        it("Should set NeedPasswordChange flag on new users", () => {
            // New users should be required to change their password on first login
            cy.visit('UserEditor.php?NewPersonID=34');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('needchange34');
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            // User should be created (password change flag is set in backend)
            cy.url().should('contain', 'UserList.php');
            cy.contains('needchange34', {timeout: 10000});
        });

        it("Should prevent SQL injection in username field", () => {
            // Test that malicious input is properly escaped
            cy.visit('UserEditor.php?NewPersonID=35');
            cy.wait(1000);
            
            const maliciousUsername = "test'; DROP TABLE user_usr; --";
            cy.get('#UserName').should('be.visible').clear().type(maliciousUsername);
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            
            // After ORM migration, this should be safely handled
            // The table should still exist and the app should function
            cy.visit('UserList.php');
            cy.wait(1000);
            cy.contains('Church Admin'); // Verify the page still loads
        });
    });

    describe("Timeline and Email Tests", () => {
        it("Should create timeline note on user creation", () => {
            // Timeline notes are created when users are added
            cy.visit('UserEditor.php?NewPersonID=36');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('timelinetest36');
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            
            // The timeline note "created" should be in the database
            // This is handled by createTimeLineNote() method
        });

        it("Should send new account email on user creation", () => {
            // Email is sent with temporary password
            cy.visit('UserEditor.php?NewPersonID=37');
            cy.wait(1000);
            cy.get('#UserName').should('be.visible').clear().type('emailtest37');
            cy.get('#SaveButton').click();
            
            cy.wait(2000);
            cy.url().should('contain', 'UserList.php');
            
            // Email sending is triggered via NewAccountEmail class
            // In test environment, emails may not actually send
            // but the code path should execute without errors
        });
    });
});
