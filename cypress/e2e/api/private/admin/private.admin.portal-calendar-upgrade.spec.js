/// <reference types="cypress" />

/**
 * The 7.8.3 upgrade retires the Member Portal's "Show the church calendar"
 * switch (bPortalShowCalendar): the portal shows its calendar while at least one
 * calendar is shared. The application no longer reads or writes the switch, so
 * its pre-upgrade row is a fixture written in SQL, after the calendars are
 * shared through the API; the test then runs the shipped block of
 * src/mysql/upgrade/7.8.3.sql (task db:upgradeBlock).
 */
const BLOCK = { script: "7.8.3.sql", block: "member portal calendar follows the shared calendars" };
const CHURCH_CALENDAR = { type: "calendar", id: 1 };

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const runBlock = () => cy.task("db:upgradeBlock", BLOCK).its("error").should("be.null");

/** The pre-upgrade switch; null leaves no row, as an install that never turned it off has. */
const setOldSwitch = (value) => {
    db("DELETE FROM config_cfg WHERE cfg_name = 'bPortalShowCalendar'");
    if (value !== null) {
        db("INSERT INTO config_cfg (cfg_name, cfg_value) VALUES ('bPortalShowCalendar', ?)", [value]);
    }
};

const oldSwitchRows = () =>
    db("SELECT COUNT(*) AS n FROM config_cfg WHERE cfg_name = 'bPortalShowCalendar'").then((rows) => Number(rows[0].n));

describe("7.8.3 upgrade: the portal calendar follows the shared calendars", () => {
    let savedCalendars;

    before(() => {
        cy.getMemberPortalCalendars().then((calendars) => {
            savedCalendars = calendars;
        });
    });

    after(() => {
        db("DELETE FROM config_cfg WHERE cfg_name = 'bPortalShowCalendar'");
        cy.then(() => cy.shareMemberPortalCalendars(savedCalendars));
    });

    /** The installation before the upgrade: the church calendar shared, and the old switch. */
    const beforeTheUpgrade = (oldSwitch) => {
        beforeEach(() => {
            cy.shareMemberPortalCalendars([CHURCH_CALENDAR]);
            setOldSwitch(oldSwitch);
        });
    };

    for (const off of ["0", ""]) {
        describe(`where the switch was off (${JSON.stringify(off)})`, () => {
            beforeTheUpgrade(off);

            it("keeps the calendar hidden", () => {
                runBlock();
                oldSwitchRows().should("eq", 0);
                cy.getMemberPortalCalendars().should("deep.eq", []);

                cy.signInAsMember(100);
                cy.request({ url: "/portal/calendar", failOnStatusCode: false }).its("status").should("eq", 404);
            });
        });
    }

    describe("run again", () => {
        beforeTheUpgrade("0");

        it("is safe to run again: a calendar shared after the upgrade stays shared", () => {
            runBlock();

            cy.shareMemberPortalCalendars([CHURCH_CALENDAR]);
            runBlock();
            oldSwitchRows().should("eq", 0);
            cy.getMemberPortalCalendars().should("deep.eq", [CHURCH_CALENDAR]);
        });
    });

    for (const [label, on] of [
        ["switched on", "1"],
        ["never turned off", null],
    ]) {
        describe(`where the switch was ${label}`, () => {
            beforeTheUpgrade(on);

            it("keeps the shared calendars", () => {
                runBlock();
                oldSwitchRows().should("eq", 0);
                cy.getMemberPortalCalendars().should("deep.eq", [CHURCH_CALENDAR]);
            });
        });
    }
});
