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

  // Initialize "Check-in By" toggle from localStorage
  const savedCheckinBy = localStorage.getItem("kioskCheckinByEnabled") === "true";
  $("#checkinByToggle").prop("checked", savedCheckinBy);
  kiosk.setCheckinByEnabled(savedCheckinBy);
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

// "Check-in By" toggle
$(document).on("change", "#checkinByToggle", function () {
  const enabled = ($(this) as JQuery<HTMLInputElement>).is(":checked");
  localStorage.setItem("kioskCheckinByEnabled", String(enabled));
  kiosk.setCheckinByEnabled(enabled);
});

// Family member selected in "Check-in By" modal
$(document).on("click", ".checkinByMemberBtn", (event) => {
  const memberId = Number($(event.currentTarget).data("memberid"));
  const checkinModal = document.getElementById("checkinByModal");
  if (checkinModal) window.bootstrap.Modal.getOrCreateInstance(checkinModal).hide();
  kiosk.resolveCheckinByModal(Number.isNaN(memberId) || memberId <= 0 ? null : memberId);
});

// "Skip" button in "Check-in By" modal
$(document).on("click", "#checkinBySkipBtn", () => {
  const checkinModal = document.getElementById("checkinByModal");
  if (checkinModal) window.bootstrap.Modal.getOrCreateInstance(checkinModal).hide();
  kiosk.resolveCheckinByModal(null);
});

// "Check-in By" modal dismissed without making a selection (X / Escape / backdrop click)
$(document).on("hidden.bs.modal", "#checkinByModal", () => {
  kiosk.cancelCheckinByModal();
});
