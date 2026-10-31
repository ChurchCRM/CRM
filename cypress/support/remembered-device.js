// Ends the grace of the tokens a person's remembered devices just replaced, so a
// spec need not wait out the minute (#10463, design §6.2). It moves the end back
// a day rather than to the database's clock, which may not be the church's time
// zone; the server still decides what an ended grace means.
Cypress.Commands.add("endDeviceTokenGrace", (personId) =>
    cy
        .dbQuery(
            "UPDATE person_device_pdv SET pdv_PreviousValidUntil = pdv_PreviousValidUntil - INTERVAL 1 DAY WHERE pdv_per_ID = ? AND pdv_PreviousValidUntil IS NOT NULL",
            [personId],
        )
        .then(({ error }) => {
            expect(error, "ending the device token grace").to.eq(null);
        }),
);
