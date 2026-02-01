describe('Attendance CSV Import', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display the attendance import page', () => {
        cy.visit('/AttendanceCSVImport.php');
        
        // Check page title
        cy.contains('Import Attendance Data').should('exist');
        
        // Check for file upload form
        cy.get('input[type="file"][name="CSVfile"]').should('exist');
        
        // Check for upload button
        cy.contains('Upload and Map Columns').should('exist');
    });

    it('should show CSV format instructions', () => {
        cy.visit('/AttendanceCSVImport.php');
        
        // Check for format documentation
        cy.contains('CSV Format').should('exist');
        cy.contains('PersonID, Date, Time').should('exist');
        cy.contains('Example').should('exist');
    });

    it('should validate file upload requirement', () => {
        cy.visit('/AttendanceCSVImport.php');
        
        // Try to submit without a file
        cy.get('button[name="UploadCSV"]').click();
        
        // Should still be on the same page or show error
        cy.url().should('include', 'AttendanceCSVImport.php');
    });

    it('should upload CSV and show mapping interface', () => {
        cy.visit('/AttendanceCSVImport.php');
        
        // Create a test CSV file
        const csvContent = 'PersonID,Date,Time\n1,2024-01-15,09:30:00\n2,2024-01-15,09:35:00';
        const fileName = 'test_attendance.csv';
        
        cy.get('input[type="file"][name="CSVfile"]').then(input => {
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const file = new File([blob], fileName, { type: 'text/csv' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input[0].files = dataTransfer.files;
        });
        
        cy.get('button[name="UploadCSV"]').click();
        
        // Should show column mapping interface
        cy.contains('Column Mapping').should('exist');
        cy.contains('Person ID (Member ID)').should('exist');
        cy.contains('Date').should('exist');
        cy.contains('Time').should('exist');
    });

    it('should allow event selection for import', () => {
        cy.visit('/AttendanceCSVImport.php');
        
        // Upload a CSV first
        const csvContent = 'PersonID,Date,Time\n1,2024-01-15,09:30:00';
        const fileName = 'test_attendance.csv';
        
        cy.get('input[type="file"][name="CSVfile"]').then(input => {
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const file = new File([blob], fileName, { type: 'text/csv' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input[0].files = dataTransfer.files;
        });
        
        cy.get('button[name="UploadCSV"]').click();
        
        // Check for event selection dropdown
        cy.get('select[name="EventId"]').should('exist');
        cy.get('select[name="EventId"]').should('contain', 'Sunday Service (New)');
        cy.get('select[name="EventId"]').should('contain', 'Fellowship Group (New)');
    });
});

describe('Person Attendance View', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display attendance tab on person profile', () => {
        // Visit a person profile (using person ID 1 as test)
        cy.visit('/PersonView.php?PersonID=1');
        
        // Check for Attendance tab
        cy.get('#nav-item-attendance').should('exist');
        cy.get('#nav-item-attendance').should('contain', 'Attendance');
    });

    it('should show attendance history when tab is clicked', () => {
        cy.visit('/PersonView.php?PersonID=1');
        
        // Click on Attendance tab
        cy.get('#nav-item-attendance').click();
        
        // Should show attendance section
        cy.get('#attendance').should('be.visible');
        cy.contains('Attendance History').should('exist');
        cy.contains('Total attendance records').should('exist');
    });

    it('should display attendance table or empty message', () => {
        cy.visit('/PersonView.php?PersonID=1');
        
        // Click on Attendance tab
        cy.get('#nav-item-attendance').click();
        
        // Should either show table or "no records" message
        cy.get('#attendance').within(() => {
            cy.get('body').then($body => {
                if ($body.find('#attendance-table').length > 0) {
                    // Table exists - check for expected columns
                    cy.get('#attendance-table thead').within(() => {
                        cy.contains('Event').should('exist');
                        cy.contains('Check-in Date/Time').should('exist');
                        cy.contains('Check-out Date/Time').should('exist');
                    });
                } else {
                    // No table - should show info message
                    cy.contains('No attendance records found').should('exist');
                }
            });
        });
    });
});
