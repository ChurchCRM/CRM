//first, define the function that will render the active members

// Listen for any click event on the document
$(document).on('click', function () {
    // Sadly, we can't enter full screen on load, but we can do
    // it the first time anything is clicked.
    window.CRM.kiosk.enterFullScreen();
});

$(function () {
    window.CRM.kiosk.startEventLoop();
});

$(document).on("click", ".widget-user-header", function (event) {
    var personId = $(event.currentTarget).data("personid");
    window.CRM.kiosk.displayPersonInfo(personId);
});

$(document).on("click", ".parentAlertButton", function (event) {
    var personId = $(event.currentTarget).data("personid");
    window.CRM.kiosk.triggerNotification(personId);
});

$(document).on("click", ".checkinButton", function (event) {
    var personId = $(event.currentTarget).data("personid");
    window.CRM.kiosk.checkInPerson(personId);
});

$(document).on("click", ".checkoutButton", function (event) {
    var personId = $(event.currentTarget).data("personid");
    window.CRM.kiosk.checkOutPerson(personId);
});
