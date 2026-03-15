/**
 * Kiosk Event Handlers
 *
 * Event handlers for the kiosk interface.
 * These bind to DOM elements and delegate to the kiosk JSOM.
 */

import { kiosk } from "./kiosk-jsom";

// Listen for any click event on the document
$(document).on("click", function () {
  // Sadly, we can't enter full screen on load, but we can do
  // it the first time anything is clicked.
  kiosk.enterFullScreen();
});

$(function () {
  kiosk.startEventLoop();
});

$(document).on("click", ".widget-user-header", function (event) {
  const personId = $(event.currentTarget).data("personid");
  kiosk.displayPersonInfo(personId);
});

$(document).on("click", ".parentAlertButton", function (event) {
  const personId = $(event.currentTarget).data("personid");
  kiosk.triggerNotification(personId);
});

$(document).on("click", ".checkinButton", function (event) {
  const personId = $(event.currentTarget).data("personid");
  kiosk.checkInPerson(personId);
});

$(document).on("click", ".checkoutButton", function (event) {
  const personId = $(event.currentTarget).data("personid");
  kiosk.checkOutPerson(personId);
});

$(document).on("click", "#refreshBtn", function (event) {
  event.preventDefault();
  kiosk.updateActiveClassMembers();
});

$(document).on("click", "#alertAllBtn", function (event) {
  event.preventDefault();
  kiosk.alertAll();
});

$(document).on("click", "#checkoutAllBtn", function (event) {
  event.preventDefault();
  kiosk.checkOutAll();
});
