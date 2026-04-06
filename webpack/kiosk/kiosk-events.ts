/**
 * Kiosk Event Handlers
 *
 * Event handlers for the kiosk interface.
 * These bind to DOM elements and delegate to the kiosk JSOM.
 */

import { kiosk } from "./kiosk-jsom";

// Listen for any click event on the document
$(document).on("click", () => {
  // Sadly, we can't enter full screen on load, but we can do
  // it the first time anything is clicked.
  kiosk.enterFullScreen();
});

$(() => {
  kiosk.startEventLoop();
});

$(document).on("click", ".widget-user-header", (event) => {
  const personId = $(event.currentTarget).data("personid");
  kiosk.displayPersonInfo(personId);
});

$(document).on("click", ".parentAlertButton", (event) => {
  const personId = $(event.currentTarget).data("personid");
  kiosk.triggerNotification(personId);
});

$(document).on("click", ".checkinButton", (event) => {
  const personId = $(event.currentTarget).data("personid");
  kiosk.checkInPerson(personId);
});

$(document).on("click", ".checkoutButton", (event) => {
  const personId = $(event.currentTarget).data("personid");
  kiosk.checkOutPerson(personId);
});

$(document).on("click", "#refreshBtn", (event) => {
  event.preventDefault();
  kiosk.updateActiveClassMembers();
});

$(document).on("click", "#alertAllBtn", (event) => {
  event.preventDefault();
  kiosk.alertAll();
});

$(document).on("click", "#checkoutAllBtn", (event) => {
  event.preventDefault();
  kiosk.checkOutAll();
});
