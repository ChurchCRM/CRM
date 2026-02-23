describe('Kiosk Device Routes - Variable Scoping', () => {
    // This test suite verifies that the kiosk device routes properly handle
    // the $getKioskFromCookie variable scoping fix

    it('should load kiosk device initialization without errors', () => {
        // Verify the kiosk initialization code structure
        // This is a static check that the scope resolution is correct
        cy.readFile('src/kiosk/routes/device.php').then((content) => {
            // Verify that device.php uses the $getKioskFromCookie variable correctly
            expect(content).to.include('$getKioskFromCookie');
            
            // Verify it's properly scoped within the group closure
            expect(content).to.include('$app->group(\'/device\'');
            
            // Verify that the variable is captured via use() in nested closures
            expect(content).to.include('use ($getKioskFromCookie)');
        });
    });

    it('should verify GLOBALS access pattern for kiosk helper', () => {
        // Verify the index.php uses GLOBALS to make the helper available
        cy.readFile('src/kiosk/index.php').then((content) => {
            // Verify that getKioskFromCookie is defined as a closure
            expect(content).to.include('$getKioskFromCookie = function');
            
            // Verify that it's made available via GLOBALS
            expect(content).to.include('$GLOBALS[\'getKioskFromCookie\']');
        });
    });

    it('should properly scope kiosk helper in device.php main group', () => {
        cy.readFile('src/kiosk/routes/device.php').then((content) => {
            // Verify the fix: accessing from GLOBALS inside the group closure
            expect(content).to.include(
                '$getKioskFromCookie = $GLOBALS[\'getKioskFromCookie\']'
            );
        });
    });

    it('should ensure all routes use the scoped getKioskFromCookie', () => {
        cy.readFile('src/kiosk/routes/device.php').then((content) => {
            // Count occurrences of route handlers that should use the function
            const heartbeatMatch = content.match(/get\('\/heartbeat'.*?use \(\$getKioskFromCookie\)/s);
            const checkinMatch = content.match(/post\('\/checkin'.*?use \(\$getKioskFromCookie\)/s);
            const checkoutMatch = content.match(/post\('\/checkout'.*?use \(\$getKioskFromCookie\)/s);

            expect(heartbeatMatch).to.exist;
            expect(checkinMatch).to.exist;
            expect(checkoutMatch).to.exist;
        });
    });
});
