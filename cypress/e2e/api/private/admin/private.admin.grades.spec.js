/// <reference types="cypress" />

// School grades (#10189). The seed holds the US grades at list 6, option ID = level:
// Pre-K 4, Kindergarten 5, 1st 6 ... 12th 17. People are created and graded in the
// person editor and read back through the API.
describe("API Private Admin - School grades", () => {
    const GRADES_LIST = 6;
    const SETTINGS = ["iGradePromotionMonth", "iGradePromotionDay", "sLastGradePromotionAt", "sLastGradePromotionResult", "sGradePromotionSetDate"];
    const saved = {};
    const people = {};
    let timeZone;

    const status = () =>
        cy.makePrivateAdminAPICall("GET", "/admin/api/grades/promotion", null, 200).then((resp) => resp.body);
    const promoteNow = (expectedLastAt, expectedStatus = 200) =>
        cy.makePrivateAdminAPICall("POST", "/admin/api/grades/promotion", { expectedLastAt }, expectedStatus);
    const runTimerJobs = () => cy.makePrivateAdminAPICall("POST", "/api/background/timerjobs", { force: true }, 200);
    const gradeOptions = () =>
        cy.makePrivateAdminAPICall("GET", `/admin/api/options/${GRADES_LIST}`, null, 200).then((resp) => resp.body);
    const preview = (key) =>
        cy.makePrivateAdminAPICall("GET", `/admin/api/grades/presets/${key}/preview`, null, 200).then((resp) => resp.body);
    const applyPreset = (key, expectedStatus = 200) =>
        preview(key).then((plan) =>
            cy.makePrivateAdminAPICall("POST", `/admin/api/grades/presets/${key}`, { expectedState: plan.state }, expectedStatus),
        );

    const createPerson = (key, level, deceased = false) => {
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type("Cypress");
        cy.get("#LastName").type(`Grade ${key}`);
        if (level !== null) {
            cy.get("#Grade").select(String(level));
        }
        if (deceased) {
            cy.get("#IsDeceased").check();
            cy.get("#DateDeceased").clear().type("2020-01-01");
        }
        cy.get("button[name='PersonSubmit']").click();
        cy.url()
            .should("match", /\/people\/view\/\d+/)
            .then((url) => {
                people[key] = Number(url.match(/\/people\/view\/(\d+)/)[1]);
            });
    };
    const setGrade = (key, level) => {
        cy.visit(`/PersonEditor.php?PersonID=${people[key]}`);
        cy.get("#Grade").select(level === null ? "" : String(level));
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("include", `/people/view/${people[key]}`);
    };
    const gradeOf = (key) => cy.makePrivateAdminAPICall("GET", `/api/person/${people[key]}`, null, 200).its("body.GradeId");
    const gradeNotes = (key) =>
        cy
            .makePrivateAdminAPICall("GET", `/api/timeline/person/${people[key]}`, null, 200)
            .then((resp) => resp.body.timeline.filter((item) => item.type === "grade"));

    const today = () => {
        const parts = new Intl.DateTimeFormat("en-CA", { timeZone, year: "numeric", month: "numeric", day: "numeric" }).formatToParts(new Date());
        const value = (type) => Number(parts.find((part) => part.type === type).value);
        return { year: value("year"), month: value("month"), day: value("day") };
    };

    before(() => {
        SETTINGS.forEach((name) => cy.getSystemConfig(name).then((value) => (saved[name] = value)));
        cy.getSystemConfig("sTimeZone").then((value) => (timeZone = value || "America/Detroit"));
        cy.setupAdminSession();
        createPerson("first", 6);
        createPerson("last", 17);
        createPerson("deceased", 6, true);
        createPerson("none", null);
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        applyPreset("us");
        SETTINGS.forEach((name) => cy.restoreSystemConfig(name, saved[name]));
        cy.then(() => cy.cleanupPeople(Object.values(people)));
    });

    it("reports the promotion settings and what a promotion would do", () => {
        status().then((body) => {
            expect(body).to.have.all.keys("month", "day", "nextDate", "nextDateText", "lastAt", "lastRunText", "promotedThisYear", "lastResult", "preview");
            expect(body.preview.moved).to.be.at.least(1);
            expect(body.preview.finished).to.be.at.least(1);
        });
    });

    it("Promote Now moves each grade up one, clears the last grade and skips deceased people", () => {
        setGrade("first", 6);
        setGrade("last", 17);
        status().then((body) => {
            promoteNow(body.lastAt).then((resp) => {
                expect(resp.body.result.trigger).to.eq("manual");
                expect(resp.body.status.lastAt).to.eq(resp.body.result.ranAt);
            });
        });
        gradeOf("first").should("eq", 7);
        gradeOf("last").should("be.null");
        gradeOf("deceased").should("eq", 6);
        gradeOf("none").should("be.null");
        gradeNotes("first").then((notes) => {
            expect(notes[0].text).to.eq("Grade changed from 1st to 2nd by the yearly promotion");
            expect(notes[0].header).to.contain("Church Admin");
        });
        gradeNotes("last").then((notes) => expect(notes[0].text).to.eq("Finished 12th, so the yearly promotion cleared the grade"));
        gradeNotes("deceased").should("have.length", 0);
    });

    it("escapes grade names in the promotion's timeline notes", () => {
        cy.makePrivateAdminAPICall("PATCH", `/admin/api/options/${GRADES_LIST}/6`, { name: "1st & 2nd" }, 200);
        setGrade("first", 6);
        status().then((body) => promoteNow(body.lastAt));
        gradeNotes("first").then((notes) => {
            expect(notes[0].text).to.eq("Grade changed from 1st &amp; 2nd to 2nd by the yearly promotion");
        });
        cy.makePrivateAdminAPICall("PATCH", `/admin/api/options/${GRADES_LIST}/6`, { name: "1st" }, 200);
    });

    it("refuses a second Promote Now sent from the same page", () => {
        setGrade("first", 6);
        status().then((body) => {
            promoteNow(body.lastAt);
            promoteNow(body.lastAt, 409);
        });
        gradeOf("first").should("eq", 7);
    });

    it("the yearly job promotes once on or after the promotion date", () => {
        const { year, month, day } = today();
        setGrade("first", 6);
        cy.restoreSystemConfig("iGradePromotionMonth", String(month));
        cy.restoreSystemConfig("iGradePromotionDay", String(day));
        cy.restoreSystemConfig("sLastGradePromotionAt", `${year - 1}-06-01 00:00:00`);

        runTimerJobs();
        gradeOf("first").should("eq", 7);
        gradeNotes("first").then((notes) => expect(notes[0].header).to.contain("Scheduled Task"));
        status().its("lastResult.trigger").should("eq", "automatic");

        runTimerJobs();
        gradeOf("first").should("eq", 7);
        status().its("nextDate").should("match", new RegExp(`^${year + 1}-`));
    });

    it("a promotion date that has already passed when it is chosen waits for next year", () => {
        const { year, month, day } = today();
        if (month === 1 && day === 1) {
            return;
        }
        setGrade("first", 6);
        cy.restoreSystemConfig("sLastGradePromotionAt", `${year - 1}-06-01 00:00:00`);
        cy.restoreSystemConfig("iGradePromotionMonth", String(day > 1 ? month : month - 1));
        cy.restoreSystemConfig("iGradePromotionDay", String(day > 1 ? day - 1 : 28));

        runTimerJobs();
        gradeOf("first").should("eq", 6);
        status().its("nextDate").should("match", new RegExp(`^${year + 1}-`));
    });

    it("re-saving an unchanged promotion date keeps the day it was set", () => {
        cy.restoreSystemConfig("iGradePromotionMonth", "6");
        cy.restoreSystemConfig("sGradePromotionSetDate", "2020-01-01");
        cy.restoreSystemConfig("iGradePromotionMonth", "6");
        cy.getSystemConfig("sGradePromotionSetDate").should("eq", "2020-01-01");
    });

    it("the yearly job waits for the date and stays off without a month", () => {
        const { year, month, day } = today();
        setGrade("first", 6);
        cy.restoreSystemConfig("sLastGradePromotionAt", `${year - 1}-06-01 00:00:00`);

        if (month < 12 || day < 31) {
            cy.restoreSystemConfig("iGradePromotionMonth", String(month < 12 ? month + 1 : 12));
            cy.restoreSystemConfig("iGradePromotionDay", String(month < 12 ? 1 : 31));
            runTimerJobs();
            gradeOf("first").should("eq", 6);
        }

        cy.restoreSystemConfig("iGradePromotionMonth", "0");
        runTimerJobs();
        gradeOf("first").should("eq", 6);
        status().its("nextDate").should("be.null");
    });

    it("lists the grade names of each country", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/grades/presets", null, 200).then((resp) => {
            expect(resp.body.current).to.eq("us");
            expect(resp.body.presets.map((preset) => preset.key)).to.include.members(["us", "canada", "england-wales", "ireland", "philippines"]);
        });
    });

    it("switches to another country's names without changing anyone's grade", () => {
        setGrade("first", 6);
        preview("england-wales").then((plan) => {
            expect(plan.renamed).to.have.length(14);
            expect(plan.added).to.deep.eq(["Nursery"]);
            expect(plan.removed).to.have.length(0);
            cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/england-wales", { expectedState: plan.state }, 200);
        });

        gradeOf("first").should("eq", 6);
        gradeOptions().then((options) => {
            expect(options[0]).to.include({ optionId: 3, optionName: "Nursery", optionSequence: 1 });
            expect(options.find((option) => option.optionId === 6).optionName).to.eq("Year 2");
        });
    });

    it("keeps a grade the church added and clears people in a grade the new names lack", () => {
        let customId;
        applyPreset("england-wales");
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${GRADES_LIST}`, { name: "Cypress College" }, 200).then((resp) => {
            customId = resp.body.optionId;
            expect(customId).to.be.at.least(100);
        });
        setGrade("none", 3);

        preview("us").then((plan) => {
            expect(plan.removed).to.deep.eq([{ name: "Nursery", people: 1 }]);
            expect(plan.cleared).to.eq(1);
            cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/us", { expectedState: plan.state }, 200);
        });

        gradeOf("none").should("be.null");
        gradeNotes("none").then((notes) => expect(notes[0].text).to.eq("Grade Nursery cleared when the grade names changed to United States"));
        gradeOptions().then((options) => {
            expect(options[0].optionName).to.eq("Pre-K");
            expect(options.at(-1)).to.include({ optionId: customId, optionName: "Cypress College" });
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${GRADES_LIST}/${customId}`, null, 200);
        });
    });

    it("refuses grade names whose preview no longer matches, and changes nothing", () => {
        applyPreset("england-wales");
        setGrade("none", null);
        preview("us").then((plan) => {
            expect(plan.cleared).to.eq(0);
            setGrade("none", 3);
            cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/us", { expectedState: plan.state }, 409);
            cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/us", {}, 409);
        });
        gradeOf("none").should("eq", 3);
        gradeOptions().its(0).its("optionName").should("eq", "Nursery");
        setGrade("none", null);
        applyPreset("us");
    });

    it("clears a deleted grade from the people who had it", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/options/${GRADES_LIST}`, { name: `Cypress Temp ${Date.now()}` }, 200).then((resp) => {
            setGrade("none", resp.body.optionId);
            gradeOf("none").should("eq", resp.body.optionId);
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${GRADES_LIST}/${resp.body.optionId}`, null, 200);
        });
        gradeOf("none").should("be.null");
    });

    it("returns 404 for grade names it does not have", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/grades/presets/atlantis/preview", null, 404);
        cy.makePrivateAdminAPICall("POST", "/admin/api/grades/presets/atlantis", null, 404);
    });

    it("is for administrators only", () => {
        cy.makePrivateUserAPICall("GET", "/admin/api/grades/promotion", null, 403);
        cy.makePrivateUserAPICall("POST", "/admin/api/grades/promotion", { expectedLastAt: "" }, 403);
        cy.makePrivateUserAPICall("POST", "/admin/api/grades/presets/england-wales", null, 403);
    });
});
