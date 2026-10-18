/**
 * Kiosk JSOM (JavaScript Object Model)
 *
 * Main kiosk logic for event check-in and check-out.
 * Requires: moment.js (loaded globally), jQuery
 */

import Avatar from "avatar-initials";
import type {
  ActiveClassMembersResponse,
  AjaxOptions,
  CheckinByPerson,
  ClassMember,
  FamilyMember,
  FamilyMembersResponse,
  HeartbeatResponse,
  KioskAssignment,
  KioskJSOM,
  PersonApiData,
  RegisterGuestResponse,
} from "./types";

// Declare moment as global (loaded via CDN in header)
declare const moment: typeof import("moment");

/** Bold wrapper for a UI label; markup stays out of the translatable phrase. */
function strong(text: string): string {
  return `<strong>${escapeHtml(text)}</strong>`;
}

/** i18next.t() for phrases whose {{values}} are already-escaped markup from strong(). */
function tHtml(key: string, values: Record<string, string>): string {
  return i18next.t(key, { ...values, interpolation: { escapeValue: false } });
}

// Helper to access window.CRM safely
function getCRM(): CRMNamespace {
  return window.CRM ?? {};
}

// Module-level state (avoids global window.CRM pollution)
const kioskState = {
  notificationsEnabled: false,
  checkinByEnabled: false,
  kioskAssignmentId: undefined as KioskAssignment | undefined,
  countdownInterval: undefined as ReturnType<typeof setInterval> | undefined,
  kioskName: undefined as string | undefined,
  refreshTimer: undefined as ReturnType<typeof setTimeout> | undefined,
  lastInteractionAt: 0,
};

// Silent poll: refresh every 20s, but wait while the screen is in use.
const POLL_INTERVAL_MS = 20_000;
const BUSY_RETRY_MS = 3_000;
const IDLE_BEFORE_POLL_MS = 10_000;

// Pending callback for the "Check-in By" modal
let pendingCheckinByCallback: ((member: FamilyMember | null) => void) | null = null;
// The family members fetched for the current modal session
let currentFamilyMembers: FamilyMember[] = [];
// Monotonically-increasing token; each showCheckinByModal() call increments it.
// The XHR .done() handler checks the token before writing currentFamilyMembers
// so rapid double-tap on touch screens cannot corrupt state with a stale response.
let currentFamilyRequestToken = 0;

/**
 * HTML escape helper to prevent XSS
 */
function escapeHtml(text: string | null | undefined): string {
  if (text === null || text === undefined) return "";
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
}

/**
 * Make API request to kiosk device endpoints
 */
function APIRequest(options: AjaxOptions): JQuery.jqXHR {
  const ajaxOptions: JQuery.AjaxSettings = {
    method: options.method || "GET",
    url: `${getCRM().root}/kiosk/device/${options.path}`,
    dataType: "json",
    contentType: "application/json",
    data: options.data,
  };
  return $.ajax(ajaxOptions);
}

/**
 * Get photo URL for a person (roster member — class child)
 */
function getPhotoUrl(personId: number): string {
  return `${getCRM().root}/kiosk/device/activeClassMember/${personId}/photo`;
}

/**
 * Get photo URL for a family member (guardian) of a roster child.
 * Uses a separate endpoint that authorizes by family relationship
 * rather than roster membership — required because guardians are
 * adults, not class members.
 */
function getFamilyPhotoUrl(childPersonId: number, memberId: number): string {
  return `${getCRM().root}/kiosk/device/activeClassMember/${childPersonId}/family/${memberId}/photo`;
}

/** Generated-initials avatar (same library as the rest of the app); colour is stable per person. */
function applyInitials($img: JQuery, name: string, personId: number, size: number): void {
  const words = name.trim().split(/\s+/).filter(Boolean);
  const initials =
    words.length > 1 ? `${words[0].charAt(0)}${words[words.length - 1].charAt(0)}` : (words[0] ?? "?").charAt(0);
  Avatar.from($img[0] as HTMLImageElement, {
    useGravatar: false,
    initials: initials.toUpperCase(),
    size,
    color: "#ffffff",
    background: `hsl(${(personId * 47) % 360}, 65%, 40%)`,
    fontSize: Math.floor(size * 0.4),
    fontWeight: 600,
    fontFamily: "'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
  });
}

/** Uploaded photo when there is one; initials when there isn't or the photo fails to load. */
function buildAvatarImg(
  name: string,
  personId: number,
  hasPhoto: boolean,
  photoUrl: string,
  size: number,
  className?: string,
): JQuery {
  const $img = $("<img>", { class: className, alt: name });
  if (hasPhoto) {
    // Bind before setting src; inline onerror is blocked by the project CSP.
    $img.one("error", () => applyInitials($img, name, personId, size));
    $img.attr("src", photoUrl);
  } else {
    applyInitials($img, name, personId, size);
  }
  return $img;
}

function buildGuardianAvatar(person: CheckinByPerson, className: string, size: number, photoUrl: string): JQuery {
  return buildAvatarImg(
    `${person.FirstName} ${person.LastName}`,
    person.Id,
    person.hasPhoto,
    photoUrl,
    size,
    className,
  );
}

/**
 * Update (or clear) the "Checked in by" line on a roster child's card.
 *
 * Shows a small avatar + name when checker is not null; clears the line
 * when null (e.g. after check-out or when no checker was recorded).
 * Accepts the roster child's personId so it can build the correct
 * guardian photo URL (authorized by family relationship, not roster
 * membership).
 */
function updateCheckinByLine(childPersonId: number, checker: CheckinByPerson | null): void {
  const $byLine = $(`#personId-${childPersonId} .kiosk-member-checkin-by`);
  if ($byLine.length === 0) return;

  if (!checker) {
    $byLine.empty();
    return;
  }

  $byLine.empty();

  $byLine.append(
    buildGuardianAvatar(checker, "checkin-by-inline-photo", 40, getFamilyPhotoUrl(childPersonId, checker.Id)),
    $("<span>").text(`${checker.FirstName} ${checker.LastName}`),
  );
}

/**
 * Render a class member card in the appropriate section
 */
function renderClassMember(classMember: ClassMember): void {
  const existingDiv = $(`#personId-${classMember.personId}`);
  if (existingDiv.length > 0) {
    // Card already exists - update data attributes in case familyId or other data changed
    existingDiv.data("familyId", classMember.familyId || null);
    // Update the checked-in-by line for existing cards (e.g. on refresh)
    updateCheckinByLine(classMember.personId, classMember.checkedInBy ?? null);
  } else {
    // Create member row
    const memberRow = $("<div>", {
      id: `personId-${classMember.personId}`,
      class: `kiosk-member${classMember.isGuest ? " kiosk-member-guest" : ""}`,
      "data-familyId": classMember.familyId || null,
    });

    // Avatar
    const avatarDiv = $("<div>", { class: "kiosk-member-avatar" });
    avatarDiv.append(
      buildAvatarImg(
        classMember.displayName,
        classMember.personId,
        classMember.hasPhoto,
        getPhotoUrl(classMember.personId),
        96,
      ),
    );

    // Info section
    const infoDiv = $("<div>", { class: "kiosk-member-info" });

    // Name with optional birthday cake for upcoming/recent birthdays
    const nameDiv = $("<div>", { class: "kiosk-member-name" });
    nameDiv.text(classMember.displayName);
    if (classMember.birthdayUpcoming || classMember.birthdayRecent) {
      nameDiv.append(
        $("<i>", {
          class: "fa-solid fa-cake-candles ms-2",
          style: "color: #e83e8c;",
          title: classMember.birthdayUpcoming ? i18next.t("Birthday coming up!") : i18next.t("Recent birthday!"),
        }),
      );
    }
    // Guest badge
    if (classMember.isGuest) {
      nameDiv.append(
        $("<span>", {
          class: "badge bg-orange-lt text-orange ms-2 kiosk-guest-badge",
          text: i18next.t("Guest"),
        }),
      );
    }
    infoDiv.append(nameDiv);

    // Show age if available
    if (classMember.age !== null && classMember.age >= 0) {
      infoDiv.append(
        $("<div>", {
          class: "kiosk-member-age",
          text: `${classMember.age} yrs`,
        }),
      );
    }

    // Checked-in-by line (initially empty; populated when checked in)
    infoDiv.append($("<div>", { class: "kiosk-member-checkin-by" }));

    // Actions - icon-only buttons
    const actionsDiv = $("<div>", { class: "kiosk-member-actions" });

    const checkinBtn = $("<button>", {
      class: "kiosk-btn kiosk-btn-checkin checkinButton",
      "data-personid": classMember.personId,
      title: i18next.t("Check In"),
    }).append($("<i>", { class: "fa-solid fa-right-to-bracket" }));

    actionsDiv.append(checkinBtn);

    // Only show alert button for checked-in students when notifications are enabled AND they have a family
    if (classMember.status === 1 && kioskState.notificationsEnabled && classMember.familyId !== null) {
      const alertBtn = $("<button>", {
        class: "kiosk-btn kiosk-btn-alert parentAlertButton",
        "data-personid": classMember.personId,
        title: i18next.t("Parent Alert"),
      }).append($("<i>", { class: "fa-solid fa-bell" }));
      actionsDiv.append(alertBtn);
    }

    memberRow.append(avatarDiv).append(infoDiv).append(actionsDiv);

    // Add to appropriate section based on status
    if (classMember.status === 1) {
      $("#checkedInList").append(memberRow);
    } else {
      $("#notCheckedInList").append(memberRow);
    }

    // Render initial checked-in-by info (from server on first load / full refresh)
    if (classMember.checkedInBy) {
      updateCheckinByLine(classMember.personId, classMember.checkedInBy);
    }
  }

  // Update visual state
  if (classMember.status === 1) {
    setCheckedIn(classMember.personId);
  } else {
    setCheckedOut(classMember.personId);
  }
}

/**
 * Update the member counts in the UI
 */
function updateMemberCounts(): void {
  const checkedIn = $("#checkedInList .kiosk-member").length;
  const notCheckedIn = $("#notCheckedInList .kiosk-member").length;

  $("#checkedInCount").text(checkedIn);
  $("#notCheckedInCount").text(notCheckedIn);
  $("#checkedInSectionCount").text(checkedIn);
  $("#notCheckedInSectionCount").text(notCheckedIn);

  // Show/hide Checkout All button based on checked-in count
  if (checkedIn > 0) {
    $("#checkoutAllBtn").show();
  } else {
    $("#checkoutAllBtn").hide();
  }

  // Show/hide Alert All button based on checked-in count, notifications enabled, and members with families
  let checkedInWithFamilies = 0;
  $("#checkedInList .kiosk-member").each(function () {
    const familyId = ($(this).data() as { familyId?: number })?.familyId;
    if (familyId) {
      checkedInWithFamilies++;
    }
  });

  if (kioskState.notificationsEnabled && checkedInWithFamilies > 0) {
    $("#alertAllBtn").show();
  } else {
    $("#alertAllBtn").hide();
  }

  // Show/hide empty state messages
  if (checkedIn === 0) {
    if ($("#checkedInList .kiosk-empty").length === 0) {
      $("#checkedInList").html(
        '<div class="kiosk-empty">' +
          '<i class="fa-solid fa-user-clock"></i>' +
          `<p>${i18next.t("No one checked in yet")}</p>` +
          "</div>",
      );
    }
  } else {
    $("#checkedInList .kiosk-empty").remove();
  }

  if (notCheckedIn === 0) {
    if ($("#notCheckedInList .kiosk-empty").length === 0) {
      $("#notCheckedInList").html(
        '<div class="kiosk-empty">' +
          '<i class="fa-solid fa-check-double text-success"></i>' +
          `<p>${i18next.t("Everyone is here!")}</p>` +
          "</div>",
      );
    }
  } else {
    $("#notCheckedInList .kiosk-empty").remove();
  }
}

/**
 * Render the birthday section banner
 */
function renderBirthdaySection(birthdayPeople: ClassMember[]): void {
  const birthdayList = $("#birthdayList");
  const birthdayBanner = $("#birthdayBanner");
  birthdayList.empty();

  if (!birthdayPeople || birthdayPeople.length === 0) {
    // Hide banner when no birthdays
    birthdayBanner.hide();
    $(".kiosk-section").removeClass("has-birthday-banner");
    $("#birthdayCount").text("0");
    return;
  }

  // Show banner and adjust section heights
  birthdayBanner.show();
  $(".kiosk-section").addClass("has-birthday-banner");

  // Sort by birthday (upcoming first, then recent)
  const monthNames = ["", ...moment.monthsShort()];

  // Separate upcoming and recent
  const upcoming = birthdayPeople.filter((p) => p.birthdayUpcoming);
  const recent = birthdayPeople.filter((p) => p.birthdayRecent && !p.birthdayUpcoming);

  // Render all birthday cards in a horizontal layout
  upcoming.forEach((person) => {
    birthdayList.append(renderBirthdayCard(person, monthNames, "upcoming"));
  });
  recent.forEach((person) => {
    birthdayList.append(renderBirthdayCard(person, monthNames, "recent"));
  });

  $("#birthdayCount").text(birthdayPeople.length);
}

/**
 * Render a birthday card
 */
function renderBirthdayCard(person: ClassMember, monthNames: string[], cardType: string): JQuery {
  let cardClass = "birthday-card";
  // Use server-provided birthdayToday flag (calculated with correct timezone)
  const isToday = person.birthdayToday;

  if (isToday) {
    cardClass += " today";
  } else if (cardType === "upcoming") {
    cardClass += " upcoming";
  } else if (cardType === "recent") {
    cardClass += " recent";
  }

  const card = $("<div>", { class: cardClass });

  // Avatar
  const avatarDiv = $("<div>", { class: "birthday-avatar" });
  avatarDiv.append(
    buildAvatarImg(person.displayName, person.personId, person.hasPhoto, getPhotoUrl(person.personId), 72),
  );
  card.append(avatarDiv);

  // Info
  const infoDiv = $("<div>", { class: "birthday-info" });
  infoDiv.append($("<div>", { class: "birthday-name", text: person.firstName }));

  const birthMonthIndex = person.birthMonth ?? 0;
  let dateText = `${monthNames[birthMonthIndex]} ${person.birthDay}`;
  let dateClass = "birthday-date";
  if (isToday) {
    dateText = `🎉 ${i18next.t("Today!")}`;
    dateClass += " today";
  }
  infoDiv.append($("<div>", { class: dateClass, text: dateText }));
  card.append(infoDiv);

  // Age badge (if age is available and it's upcoming/today)
  if (person.age !== null && (cardType === "upcoming" || isToday)) {
    const turningAge = isToday ? person.age : person.age + 1;
    card.append($("<span>", { class: "birthday-age-badge", text: i18next.t("Turning {{age}}", { age: turningAge }) }));
  }

  return card;
}

/**
 * Update active class members from API
 */
function updateActiveClassMembers(): void {
  APIRequest({
    path: "activeClassMembers",
  })
    .done((data: ActiveClassMembersResponse) => {
      // Walk-in guests need a linked group and an open event; the server enforces it too.
      $("#registerGuestBtn").toggleClass("d-none", !data?.GroupName);

      if (!data?.People || data.People.length === 0) {
        // No members found - show helpful debug info
        $("#classMemberContainer").html(renderNoMembersMessage());
        return;
      }

      // Store notifications enabled flag for use in rendering
      kioskState.notificationsEnabled = data.notificationsEnabled || false;

      // Clear loading state on first load
      if ($("#notCheckedInList .fa-spinner").length > 0) {
        $("#checkedInList").empty();
        $("#notCheckedInList").empty();
        $("#birthdayList").empty();
      }

      // Update group name in headers if provided
      if (data.GroupName) {
        $(".kiosk-group-name").text(data.GroupName);
      }

      // Collect birthday people for separate section
      const birthdayPeople: ClassMember[] = [];

      data.People.forEach((d: PersonApiData) => {
        const memberData: ClassMember = {
          displayName: `${d.FirstName} ${d.LastName}`,
          firstName: d.FirstName,
          classRole: d.RoleName,
          personId: d.Id,
          status: d.status,
          gender: d.Gender,
          hasPhoto: d.hasPhoto,
          age: d.age,
          birthdayThisMonth: d.birthdayThisMonth,
          birthdayUpcoming: d.birthdayUpcoming,
          birthdayRecent: d.birthdayRecent,
          birthdayToday: d.birthdayToday,
          birthDay: d.birthDay,
          birthMonth: d.birthMonth,
          familyId: d.familyId,
          checkedInBy: d.checkedInBy ?? null,
          isGuest: d.isGuest ?? false,
        };

        renderClassMember(memberData);

        // Add to birthday list if upcoming or recent
        if (d.birthdayUpcoming || d.birthdayRecent) {
          birthdayPeople.push(memberData);
        }
      });

      // Render birthday section
      renderBirthdaySection(birthdayPeople);

      // Update counts after rendering
      updateMemberCounts();
    })
    .fail((xhr: JQuery.jqXHR) => {
      // API error - show debug info
      let errorMessage = i18next.t("Unable to load group members");
      if (xhr.responseJSON?.message) {
        errorMessage = xhr.responseJSON.message;
      } else if (xhr.status === 500) {
        errorMessage = i18next.t("Server error - the event may not be linked to a group");
      }
      $("#classMemberContainer").html(renderErrorMessage(errorMessage, xhr.status));
    });
}

/**
 * Render no members message with troubleshooting
 */
function renderNoMembersMessage(): string {
  return (
    '<div class="kiosk-status-container">' +
    '<div class="card kiosk-status-card card-warning">' +
    '<div class="card-header">' +
    '<h3 class="card-title"><i class="fa-solid fa-users-slash me-2"></i>' +
    i18next.t("No Group Members Found") +
    "</h3>" +
    "</div>" +
    '<div class="card-body">' +
    '<div class="kiosk-status-icon text-warning">' +
    '<i class="fa-solid fa-users-slash"></i>' +
    "</div>" +
    '<p class="text-muted">' +
    i18next.t("This kiosk is assigned to an event, but no members are available for check-in.") +
    "</p>" +
    '<div class="kiosk-instructions">' +
    '<h5><i class="fa-solid fa-circle-info me-2"></i>' +
    i18next.t("Possible Causes") +
    "</h5>" +
    "<ol>" +
    `<li>${tHtml("{{label}} Edit the event and associate it with a group", { label: strong(`${i18next.t("Event not linked to a Group")}:`) })}</li>` +
    `<li>${tHtml("{{label}} Add people to the group that is linked to this event", { label: strong(`${i18next.t("Group has no members")}:`) })}</li>` +
    `<li>${tHtml("{{label}} The event may not be currently active (check start/end times)", { label: strong(`${i18next.t("Event timing")}:`) })}</li>` +
    "</ol>" +
    "</div>" +
    '<div class="kiosk-instructions mt-3">' +
    '<h5><i class="fa-solid fa-wrench me-2"></i>' +
    i18next.t("How to Fix") +
    "</h5>" +
    '<ol class="small">' +
    `<li>${tHtml("Go to {{place}} in ChurchCRM", { place: strong(i18next.t("Events")) })}</li>` +
    `<li>${tHtml("Edit this event and set the {{field}} field", { field: strong(i18next.t("Group")) })}</li>` +
    `<li>${i18next.t("Ensure the group has members assigned")}</li>` +
    `<li>${i18next.t("Refresh this kiosk page")}</li>` +
    "</ol>" +
    "</div>" +
    "</div>" +
    "</div>" +
    "</div>"
  );
}

/**
 * Render error message with troubleshooting
 */
function renderErrorMessage(message: string, statusCode?: number): string {
  return (
    '<div class="kiosk-status-container">' +
    '<div class="card kiosk-status-card card-danger">' +
    '<div class="card-header">' +
    '<h3 class="card-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>' +
    i18next.t("Error Loading Members") +
    "</h3>" +
    "</div>" +
    '<div class="card-body">' +
    '<div class="kiosk-status-icon text-danger">' +
    '<i class="fa-solid fa-triangle-exclamation"></i>' +
    "</div>" +
    '<p class="text-muted">' +
    escapeHtml(message) +
    "</p>" +
    (statusCode
      ? `<p class="small text-muted">${i18next.t("HTTP Status: {{status}}", { status: String(statusCode), interpolation: { escapeValue: true } })}</p>`
      : "") +
    '<div class="kiosk-instructions">' +
    '<h5><i class="fa-solid fa-lightbulb me-2"></i>' +
    i18next.t("Troubleshooting") +
    "</h5>" +
    "<ul>" +
    `<li>${tHtml("Verify the event is linked to a {{field}}", { field: strong(i18next.t("Group")) })}</li>` +
    `<li>${i18next.t("Check that the event start/end times are correct")}</li>` +
    `<li>${i18next.t("Ensure the group has members")}</li>` +
    `<li>${i18next.t("Check the server logs for more details")}</li>` +
    "</ul>" +
    "</div>" +
    "</div>" +
    "</div>" +
    "</div>"
  );
}

/**
 * Helper function to safely parse JSON assignment data
 */
function parseAssignment(assignmentJson: string | null | undefined): KioskAssignment | null {
  if (!assignmentJson) {
    return null;
  }
  try {
    return JSON.parse(assignmentJson) as KioskAssignment;
  } catch (e) {
    // Log the error but do not break the heartbeat loop
    // eslint-disable-next-line no-console
    console.error("Failed to parse kiosk Assignment JSON:", e, assignmentJson);
    return null;
  }
}

/**
 * Heartbeat function to check kiosk status
 */
function heartbeat(): void {
  APIRequest({
    path: "heartbeat",
  }).done((data: HeartbeatResponse) => {
    // Reload if the kiosk was renamed in the admin panel
    if (kioskState.kioskName !== undefined && kioskState.kioskName !== data.Name) {
      location.reload();
      return;
    }
    kioskState.kioskName = data.Name;

    const thisAssignment = parseAssignment(data.Assignment);
    if (kioskState.kioskAssignmentId === undefined) {
      kioskState.kioskAssignmentId = thisAssignment || undefined;
    } else if (
      thisAssignment &&
      kioskState.kioskAssignmentId &&
      (thisAssignment.EventId !== kioskState.kioskAssignmentId.EventId ||
        thisAssignment.Event.GroupId !== kioskState.kioskAssignmentId.Event.GroupId)
    ) {
      location.reload();
    }

    if (data.Commands === "Reload") {
      location.reload();
    }

    if (data.Commands === "Identify") {
      stopEventLoop();
      $("#event").hide();
      $("#noEvent").css("display", "flex");
      $("#noEvent").html(
        renderStatusCard("info", "fa-tablet-screen-button", i18next.t("Kiosk Identification"), data.Name, null),
      );
      setTimeout(() => {
        location.reload();
      }, 2000);
      return;
    }

    if (data.Accepted) {
      const Assignment = parseAssignment(data.Assignment);
      if (Assignment && Assignment.AssignmentType === 1) {
        // Event.Start / End / CheckInOpensAt are ISO 8601 strings with the
        // church's sTimeZone offset (set by KioskDevice::heartbeat). moment
        // parses them as instants-in-time, so all comparisons below are
        // tz-correct regardless of the kiosk device's browser timezone.
        // CheckInOpensAt = event start − 1 hour (server-computed) so check-in
        // opens before the event actually starts. Falls back to event start
        // − 1 hour for older API responses without the field.
        const eventStart = moment(Assignment.Event.Start);
        const eventEnd = moment(Assignment.Event.End);
        // Server emits CheckInOpensAt as ISO+sTimeZone offset (event start − 1h).
        // Fallback to client-side compute for older API responses.
        const checkInOpensAt = Assignment.Event.CheckInOpensAt
          ? moment(Assignment.Event.CheckInOpensAt)
          : eventStart.clone().subtract(1, "hour");
        const now = moment();

        $("#eventTitle").text(Assignment.Event.Title);
        $("#kioskName").text(data.Name);
        $("#startTime").text(eventStart.format("LLL"));
        $("#endTime").text(eventEnd.format("LLL"));

        if (now.isBefore(checkInOpensAt)) {
          $("#noEvent").hide();
          $("#event").show();
          $("#timeRemaining").addClass("d-none");
          $("#registerGuestBtn").addClass("d-none");
          $("#classMemberContainer").html(renderCountdown(checkInOpensAt, Assignment.Event.Title, data.Name));
          startCountdown(checkInOpensAt);
        } else if (now.isAfter(eventEnd)) {
          // Event has ended
          $("#noEvent").hide();
          $("#event").show();
          $("#timeRemaining").addClass("d-none");
          $("#registerGuestBtn").addClass("d-none");
          $("#classMemberContainer").html(renderEventEnded(Assignment.Event.Title));
        } else {
          // Event is active - show class members + time-remaining badge so
          // volunteers know when the kiosk will close. Heartbeat runs every
          // 15s, so minute-level granularity is plenty here.
          updateActiveClassMembers();
          $("#noEvent").hide();
          $("#event").show();
          const minutesLeft = Math.max(0, Math.ceil((eventEnd.unix() - now.unix()) / 60));
          let label: string;
          if (minutesLeft < 1) {
            label = i18next.t("Closing");
          } else if (minutesLeft < 60) {
            label = i18next.t("{{minutes}} min left", { minutes: minutesLeft });
          } else {
            const hours = Math.floor(minutesLeft / 60);
            const mins = minutesLeft % 60;
            label = i18next.t("{{hours}}h {{mins}}m left", { hours: hours, mins: mins });
          }
          $("#timeRemaining").removeClass("d-none").text(label);
        }
      } else {
        $("#noEvent").css("display", "flex");
        $("#noEvent").html(
          renderStatusCard(
            "success",
            "fa-circle-check",
            i18next.t("Kiosk Ready"),
            data.Name,
            `<p class='text-muted mb-0'>${i18next.t("This kiosk is accepted but has no active event assignment.")}</p>` +
              "<div class='kiosk-instructions'>" +
              `<h5><i class='fa-solid fa-circle-info'></i> ${i18next.t("Next Steps")}</h5>` +
              "<ol>" +
              `<li>${tHtml("Go to {{path}}", { path: strong(`${i18next.t("Groups")} → ${i18next.t("Admin")} → ${i18next.t("Kiosk Manager")}`) })}</li>` +
              `<li>${i18next.t("Find this kiosk in the device list")}</li>` +
              `<li>${tHtml("Use the {{control}} dropdown to select an event", { control: strong(i18next.t("Assign")) })}</li>` +
              "</ol></div>",
          ),
        );
        $("#event").hide();
      }
    } else {
      $("#noEvent").css("display", "flex");
      $("#noEvent").html(
        renderStatusCard(
          "pending",
          "fa-hourglass-half",
          i18next.t("Awaiting Acceptance"),
          data.Name,
          `<p class='text-muted'>${i18next.t("This kiosk has registered but needs to be accepted by an administrator before it can be used.")}</p>` +
            "<div class='kiosk-instructions'>" +
            `<h5><i class='fa-solid fa-circle-info'></i> ${i18next.t("How to Accept This Kiosk")}</h5>` +
            "<ol>" +
            `<li>${i18next.t("Log in to ChurchCRM as an administrator")}</li>` +
            `<li>${tHtml("Navigate to {{path}}", { path: strong(`${i18next.t("Groups")} → ${i18next.t("Admin")} → ${i18next.t("Kiosk Manager")}`) })}</li>` +
            `<li>${tHtml("Find the kiosk named {{name}} in the list", { name: strong(data.Name) })}</li>` +
            `<li>${tHtml("Click the {{button}} button", { button: `<strong><i class='fa-solid fa-check'></i> ${escapeHtml(i18next.t("Accept"))}</strong>` })}</li>` +
            `<li>${i18next.t("Assign an event to the kiosk using the dropdown")}</li>` +
            "</ol>" +
            `<p class='mb-0'><small>${i18next.t("This page will automatically update once the kiosk is accepted.")}</small></p>` +
            "</div>",
        ),
      );
      $("#event").hide();
    }
  });
}

/**
 * Render countdown timer before event starts
 */
function renderCountdown(eventStart: moment.Moment, eventTitle: string, kioskName: string): string {
  return (
    '<div class="kiosk-status-container">' +
    '<div class="card kiosk-status-card card-primary">' +
    '<div class="card-header">' +
    '<h3 class="card-title"><i class="fa-solid fa-clock me-2"></i>' +
    i18next.t("Check-in Opens Soon") +
    "</h3>" +
    "</div>" +
    '<div class="card-body text-center">' +
    '<div class="kiosk-status-icon text-primary">' +
    '<i class="fa-solid fa-clock"></i>' +
    "</div>" +
    '<div class="kiosk-name mb-2"><i class="fa-solid fa-tablet-screen-button me-2"></i>' +
    escapeHtml(kioskName) +
    "</div>" +
    '<p class="text-muted mb-3">' +
    tHtml("Check-in for {{title}} will begin at", { title: strong(eventTitle) }) +
    ":</p>" +
    '<h4 class="text-primary mb-4">' +
    eventStart.format("LT") +
    "</h4>" +
    '<div class="row justify-content-center mb-4">' +
    '<div class="col-auto text-center px-3">' +
    '<div id="countdown-days" class="kiosk-countdown text-dark">00</div>' +
    '<small class="text-muted">' +
    i18next.t("Days") +
    "</small>" +
    "</div>" +
    '<div class="col-auto text-center px-3">' +
    '<div id="countdown-hours" class="kiosk-countdown text-dark">00</div>' +
    '<small class="text-muted">' +
    i18next.t("Hours") +
    "</small>" +
    "</div>" +
    '<div class="col-auto text-center px-3">' +
    '<div id="countdown-minutes" class="kiosk-countdown text-dark">00</div>' +
    '<small class="text-muted">' +
    i18next.t("Minutes") +
    "</small>" +
    "</div>" +
    '<div class="col-auto text-center px-3">' +
    '<div id="countdown-seconds" class="kiosk-countdown text-primary">00</div>' +
    '<small class="text-muted">' +
    i18next.t("Seconds") +
    "</small>" +
    "</div>" +
    "</div>" +
    '<p class="mb-0 text-muted"><small>' +
    i18next.t("This page will automatically refresh when check-in opens.") +
    "</small></p>" +
    "</div>" +
    "</div>" +
    "</div>"
  );
}

/**
 * Render event ended message
 */
function renderEventEnded(eventTitle: string): string {
  return (
    '<div class="kiosk-status-container">' +
    '<div class="card kiosk-status-card card-success">' +
    '<div class="card-header">' +
    '<h3 class="card-title"><i class="fa-solid fa-calendar-check me-2"></i>' +
    i18next.t("Event Has Ended") +
    "</h3>" +
    "</div>" +
    '<div class="card-body text-center">' +
    '<div class="kiosk-status-icon text-success">' +
    '<i class="fa-solid fa-calendar-check"></i>' +
    "</div>" +
    '<p class="text-muted mb-0">' +
    tHtml("Check-in for {{title}} has closed.", { title: strong(eventTitle) }) +
    "</p>" +
    '<p class="text-muted">' +
    i18next.t("Thank you for attending!") +
    "</p>" +
    "</div>" +
    "</div>" +
    "</div>"
  );
}

/**
 * Start the countdown timer
 */
function startCountdown(eventStart: moment.Moment): void {
  // Clear any existing countdown interval
  if (kioskState.countdownInterval) {
    clearInterval(kioskState.countdownInterval);
  }

  kioskState.countdownInterval = setInterval(() => {
    const now = moment();
    const duration = moment.duration(eventStart.diff(now));

    if (duration.asSeconds() <= 0) {
      // Event has started - reload page to show members
      clearInterval(kioskState.countdownInterval);
      location.reload();
      return;
    }

    const days = Math.floor(duration.asDays());
    const hours = duration.hours();
    const minutes = duration.minutes();
    const seconds = duration.seconds();

    $("#countdown-days").text(String(days).padStart(2, "0"));
    $("#countdown-hours").text(String(hours).padStart(2, "0"));
    $("#countdown-minutes").text(String(minutes).padStart(2, "0"));
    $("#countdown-seconds").text(String(seconds).padStart(2, "0"));
  }, 1000);
}

/**
 * Render a status card
 */
function renderStatusCard(
  statusType: string,
  iconClass: string,
  title: string,
  kioskName: string,
  bodyContent: string | null,
): string {
  let statusColor = "bg-primary";
  let iconColorClass = "text-primary";

  if (statusType === "pending") {
    statusColor = "bg-warning";
    iconColorClass = "text-warning";
  } else if (statusType === "success") {
    statusColor = "bg-success";
    iconColorClass = "text-success";
  } else if (statusType === "info") {
    statusColor = "bg-info";
    iconColorClass = "text-info";
  }

  // Validate iconClass to prevent XSS via class injection (only allow known FA icons)
  const safeIconClass = /^fa-[a-z0-9-]+$/.test(iconClass) ? iconClass : "fa-circle-question";

  return (
    '<div class="card kiosk-status-card">' +
    '<div class="card-status-top ' +
    statusColor +
    '"></div>' +
    '<div class="card-header">' +
    '<h3 class="card-title"><i class="fa-solid ' +
    safeIconClass +
    ' me-2"></i>' +
    escapeHtml(title) +
    "</h3>" +
    "</div>" +
    '<div class="card-body text-center">' +
    '<div class="kiosk-status-icon ' +
    iconColorClass +
    '">' +
    '<i class="fa-solid ' +
    safeIconClass +
    '"></i>' +
    "</div>" +
    '<div class="kiosk-name"><i class="fa-solid fa-tablet-screen-button me-2"></i>' +
    escapeHtml(kioskName) +
    "</div>" +
    (bodyContent ? bodyContent : "") +
    "</div></div>"
  );
}

/**
 * Check in a person
 */
function checkInPerson(personId: number): void {
  if (kioskState.checkinByEnabled) {
    const $personDiv = $(`#personId-${personId}`);
    const personName = $personDiv.find(".kiosk-member-name").text().trim() || i18next.t("this person");
    showCheckinByModal(personId, "checkin", personName);
  } else {
    performCheckin(personId, null);
  }
}

/**
 * Perform the actual check-in API call
 */
function performCheckin(personId: number, checker: FamilyMember | null): void {
  const payload: { PersonId: number; CheckedInById?: number } = { PersonId: personId };
  if (checker !== null) {
    payload.CheckedInById = checker.Id;
  }
  APIRequest({
    path: "checkin",
    method: "POST",
    data: JSON.stringify(payload),
  }).done(() => {
    setCheckedIn(personId);
    // Optimistically update the checked-in-by line with the selected guardian.
    updateCheckinByLine(personId, checker);
  });
}

/**
 * Check out a person
 */
function checkOutPerson(personId: number): void {
  if (kioskState.checkinByEnabled) {
    const $personDiv = $(`#personId-${personId}`);
    const personName = $personDiv.find(".kiosk-member-name").text().trim() || i18next.t("this person");
    showCheckinByModal(personId, "checkout", personName);
  } else {
    performCheckout(personId, null);
  }
}

/**
 * Perform the actual check-out API call
 */
function performCheckout(personId: number, checker: FamilyMember | null): void {
  const payload: { PersonId: number; CheckedOutById?: number } = { PersonId: personId };
  if (checker !== null) {
    payload.CheckedOutById = checker.Id;
  }
  APIRequest({
    path: "checkout",
    method: "POST",
    data: JSON.stringify(payload),
  }).done(() => {
    setCheckedOut(personId);
  });
}

/**
 * Enable or disable the "Check-in By" feature
 */
function setCheckinByEnabled(enabled: boolean): void {
  kioskState.checkinByEnabled = enabled;
}

/**
 * Render family member selection buttons for the "Check-in By" modal.
 * Photos are fetched via the guardian photo endpoint, authorized by
 * family relationship to the roster child ({childPersonId}).
 * Returns a jQuery element; avoids HTML-string injection to prevent
 * inline event-handler issues (project CSP forbids 'unsafe-inline').
 */
function renderFamilyMemberOptions(members: FamilyMember[], childPersonId: number): JQuery {
  const $container = $("<div>", { class: "checkin-by-members" });

  for (const member of members) {
    const $btn = $("<button>", {
      type: "button",
      class: "checkin-by-member-btn checkinByMemberBtn",
      "data-memberid": member.Id,
    });

    $btn.append(buildGuardianAvatar(member, "checkin-by-photo", 144, getFamilyPhotoUrl(childPersonId, member.Id)));

    $btn.append($("<span>", { class: "checkin-by-name", text: `${member.FirstName} ${member.LastName}` }));
    $container.append($btn);
  }

  return $container;
}

/**
 * Show the "Check-in By" modal for selecting who is checking a person in/out
 */
function showCheckinByModal(personId: number, action: "checkin" | "checkout", personName: string): void {
  const isCheckin = action === "checkin";
  const title = isCheckin
    ? i18next.t("Who is bringing in {{name}}?", { name: personName })
    : i18next.t("Who is picking up {{name}}?", { name: personName });

  // Set modal title
  $("#checkinByModalTitle").text(title);

  // Show loading state
  $("#checkinByModalBody").html(
    `<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">${i18next.t("Loading family members...")}</p></div>`,
  );

  // Track which child's family we are showing (needed for the guardian photo URL).
  // Increment token *before* resetting members so any in-flight XHR from a prior
  // call sees a stale token and discards its result (rapid double-tap guard).
  currentFamilyMembers = [];
  const myToken = ++currentFamilyRequestToken;

  // Store the callback to invoke after selection
  pendingCheckinByCallback = (member) => {
    if (action === "checkin") {
      performCheckin(personId, member);
    } else {
      performCheckout(personId, member);
    }
  };

  // Show modal
  const checkinByEl = document.getElementById("checkinByModal");
  if (checkinByEl) window.bootstrap.Modal.getOrCreateInstance(checkinByEl).show();

  // Fetch family members
  APIRequest({
    path: `activeClassMember/${personId}/family`,
  })
    .done((data: FamilyMembersResponse) => {
      // Discard stale responses from a previous modal open (rapid double-tap race).
      if (myToken !== currentFamilyRequestToken) return;

      // Guard against unexpected API responses (defensive check for empty/malformed members array)
      if (!data.members || data.members.length === 0) {
        // No family members found — close modal and proceed without a checker
        const el = document.getElementById("checkinByModal");
        if (el) window.bootstrap.Modal.getOrCreateInstance(el).hide();
        const cb = pendingCheckinByCallback;
        pendingCheckinByCallback = null;
        if (cb) {
          cb(null);
        }
        return;
      }
      currentFamilyMembers = data.members;
      $("#checkinByModalBody").empty().append(renderFamilyMemberOptions(data.members, personId));
    })
    .fail(() => {
      // Discard stale failure responses too.
      if (myToken !== currentFamilyRequestToken) return;

      // On API failure close modal and proceed without a checker
      const failEl = document.getElementById("checkinByModal");
      if (failEl) window.bootstrap.Modal.getOrCreateInstance(failEl).hide();
      const cb = pendingCheckinByCallback;
      pendingCheckinByCallback = null;
      if (cb) {
        cb(null);
      }
    });
}

/**
 * Resolve the "Check-in By" modal with the selected person (or null for skip).
 * Looks up the full FamilyMember object from the currently-loaded members list
 * so the callback receives name + hasPhoto for immediate card update.
 */
function resolveCheckinByModal(checkedByPersonId: number | null): void {
  if (pendingCheckinByCallback) {
    const cb = pendingCheckinByCallback;
    pendingCheckinByCallback = null;
    const member =
      checkedByPersonId !== null && checkedByPersonId > 0
        ? (currentFamilyMembers.find((m) => m.Id === checkedByPersonId) ?? null)
        : null;
    cb(member);
  }
}

/**
 * Cancel the "Check-in By" modal without performing a check-in/out
 */
function cancelCheckinByModal(): void {
  pendingCheckinByCallback = null;
}

/**
 * Send alert to all checked-in students with families
 */
function alertAll(): void {
  // Get all checked-in members with families
  const membersToAlert: Array<{ personId: number; name: string }> = [];
  $("#checkedInList .kiosk-member").each(function () {
    const idAttr = $(this).attr("id");
    const familyId = ($(this).data() as { familyId?: number })?.familyId;
    if (!idAttr || !familyId) {
      return;
    }
    const numericPart = idAttr.replace("personId-", "");
    const numericId = parseInt(numericPart, 10);
    if (Number.isNaN(numericId)) {
      return;
    }
    const name = $(this).find(".kiosk-member-name").text().trim() || i18next.t("Student");
    membersToAlert.push({ personId: numericId, name });
  });

  if (membersToAlert.length === 0) {
    showKioskNotification(i18next.t("No checked-in students with families to alert"), "warning");
    return;
  }

  // Disable button and show sending state
  const $btn = $("#alertAllBtn");
  const originalHtml = $btn.html();
  $btn.prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

  // Track success/failure per alert
  let completed = 0;
  let successCount = 0;
  const total = membersToAlert.length;

  const handleCompletion = (success: boolean): void => {
    completed++;
    if (success) successCount++;

    if (completed === total) {
      $btn.html(originalHtml).prop("disabled", false);
      const failureCount = total - successCount;
      if (failureCount === 0) {
        showKioskNotification(i18next.t("Alerts sent: {{total}}", { total }), "success");
      } else if (successCount === 0) {
        showKioskNotification(i18next.t("Failed to send all alerts: {{total}}", { total }), "error");
      } else {
        showKioskNotification(
          i18next.t("Alerts sent: {{sent}}, failed: {{failed}}", { sent: successCount, failed: failureCount }),
          "warning",
        );
      }
    }
  };

  // Send alerts sequentially with 500ms delay to avoid overwhelming the server
  membersToAlert.forEach((member, index) => {
    setTimeout(() => {
      APIRequest({
        path: "triggerNotification",
        method: "POST",
        data: JSON.stringify({ PersonId: member.personId }),
      })
        .done(() => handleCompletion(true))
        .fail(() => handleCompletion(false));
    }, index * 500);
  });
}
function checkOutAll(): void {
  // Disable button during processing
  const $btn = $("#checkoutAllBtn");
  const originalHtml = $btn.html();
  $btn.prop("disabled", true).html(`<i class="fa-solid fa-spinner fa-spin me-1"></i>${i18next.t("Processing...")}`);

  APIRequest({
    path: "checkoutAll",
    method: "POST",
  })
    .done(() => {
      // Move all checked-in people to not checked in
      $("#checkedInList .kiosk-member").each(function () {
        const idAttr = $(this).attr("id");
        if (!idAttr) {
          return;
        }
        const numericPart = idAttr.replace("personId-", "");
        const numericId = parseInt(numericPart, 10);
        if (!Number.isNaN(numericId)) {
          setCheckedOut(numericId);
        }
      });
      $btn.html(originalHtml).prop("disabled", false);
    })
    .fail(() => {
      $btn.html(originalHtml).prop("disabled", false);
    });
}

/**
 * Set a person as checked out in the UI
 */
function setCheckedOut(personId: number): void {
  const $personDiv = $(`#personId-${personId}`);
  const $personDivButton = $personDiv.find(".checkoutButton");

  // Update button - icon only
  $personDivButton.removeClass("checkoutButton kiosk-btn-checkout");
  $personDivButton.addClass("checkinButton kiosk-btn-checkin");
  $personDivButton.html('<i class="fa-solid fa-right-to-bracket"></i>');
  $personDivButton.attr("title", "Check In");

  // Remove checked-in styling
  $personDiv.removeClass("checked-in");

  // Clear the checked-in-by line (person is no longer checked in)
  updateCheckinByLine(personId, null);

  // Remove alert button when checked out (alert buttons only shown for checked-in students)
  $personDiv.find(".parentAlertButton").remove();

  // Move to Not Checked In section if not already there
  if ($personDiv.closest("#notCheckedInList").length === 0) {
    $personDiv.detach().appendTo("#notCheckedInList");
    updateMemberCounts();
  }
}

/**
 * Set a person as checked in in the UI
 */
function setCheckedIn(personId: number): void {
  const $personDiv = $(`#personId-${personId}`);
  const $personDivButton = $personDiv.find(".checkinButton");

  // Update button - icon only
  $personDivButton.removeClass("checkinButton kiosk-btn-checkin");
  $personDivButton.addClass("checkoutButton kiosk-btn-checkout");
  $personDivButton.html('<i class="fa-solid fa-right-from-bracket"></i>');
  $personDivButton.attr("title", "Check Out");

  // Add checked-in styling
  $personDiv.addClass("checked-in");

  // Get familyId from rendered data (stored in member row)
  const familyId = ($personDiv.data() as { familyId?: number })?.familyId;

  // Manage alert button: add if conditions are met, remove if not
  const hasAlertBtn = $personDiv.find(".parentAlertButton").length > 0;
  const shouldHaveAlertBtn = kioskState.notificationsEnabled && familyId;

  if (shouldHaveAlertBtn && !hasAlertBtn) {
    // Add alert button
    const $actionsDiv = $personDiv.find(".kiosk-member-actions");
    const alertBtn = $("<button>", {
      class: "kiosk-btn kiosk-btn-alert parentAlertButton",
      "data-personid": personId,
      title: i18next.t("Parent Alert"),
    }).append($("<i>", { class: "fa-solid fa-bell" }));
    $actionsDiv.append(alertBtn);
  } else if (!shouldHaveAlertBtn && hasAlertBtn) {
    // Remove alert button if conditions no longer met
    $personDiv.find(".parentAlertButton").remove();
  }

  // Move to Checked In section if not already there
  if ($personDiv.closest("#checkedInList").length === 0) {
    $personDiv.detach().appendTo("#checkedInList");
    updateMemberCounts();
  }
}

/**
 * Trigger a parent notification
 */
function triggerNotification(personId: number): void {
  // Get the student's name for feedback
  const $personDiv = $(`#personId-${personId}`);
  const studentName = $personDiv.find(".kiosk-member-name").text().trim() || i18next.t("Student");

  // GUARD: Verify person has a family before sending alert (should not be reachable if UI guard works)
  const familyId = ($personDiv.data() as { familyId?: number })?.familyId;
  if (!familyId) {
    console.error(`Cannot send alert: ${studentName} has no family record (familyId=${familyId})`);
    showKioskNotification(i18next.t("{{name}} has no family on record", { name: studentName }), "error");
    return;
  }

  // Visual feedback - disable button and show sending state
  const $alertBtn = $personDiv.find(".parentAlertButton");
  $alertBtn.prop("disabled", true).addClass("sending");
  $alertBtn.find("i").removeClass("fa-bell").addClass("fa-spinner fa-spin");

  APIRequest({
    path: "triggerNotification",
    method: "POST",
    data: JSON.stringify({ PersonId: personId }),
  })
    .done(() => {
      // Show success notification
      const crm = getCRM();
      if (crm.notify) {
        crm.notify(i18next.t("Parent alert sent for {{name}}", { name: studentName }), {
          type: "success",
          delay: 4000,
        });
      } else {
        // Fallback for kiosk view without full CRM.notify
        showKioskNotification(i18next.t("Parent alert sent for {{name}}", { name: studentName }), "success");
      }
      // Reset button after short delay
      setTimeout(() => {
        $alertBtn.prop("disabled", false).removeClass("sending");
        $alertBtn.find("i").removeClass("fa-spinner fa-spin").addClass("fa-bell");
      }, 2000);
    })
    .fail(() => {
      // Show error notification
      const crmFail = getCRM();
      if (crmFail.notify) {
        crmFail.notify(i18next.t("Failed to send parent alert"), { type: "error", delay: 4000 });
      } else {
        showKioskNotification(i18next.t("Failed to send parent alert"), "error");
      }
      // Reset button
      $alertBtn.prop("disabled", false).removeClass("sending");
      $alertBtn.find("i").removeClass("fa-spinner fa-spin").addClass("fa-bell");
    });
}

/**
 * Simple notification for kiosk mode (no Notyf dependency)
 */
function showKioskNotification(message: string, type: string): void {
  const $notification = $("<div>", {
    class: `kiosk-notification kiosk-notification-${type}`,
    text: message,
  });
  $("body").append($notification);

  // Animate in
  setTimeout(() => {
    $notification.addClass("show");
  }, 10);

  // Remove after delay
  setTimeout(() => {
    $notification.removeClass("show");
    setTimeout(() => {
      $notification.remove();
    }, 300);
  }, 4000);
}

/**
 * Enter full screen mode
 */
function enterFullScreen(): void {
  const docEl = document.documentElement as HTMLElement & {
    mozRequestFullScreen?: () => Promise<void>;
    webkitRequestFullscreen?: () => Promise<void>;
    msRequestFullscreen?: () => Promise<void>;
  };

  if (docEl.requestFullscreen) {
    docEl.requestFullscreen();
  } else if (docEl.mozRequestFullScreen) {
    docEl.mozRequestFullScreen();
  } else if (docEl.webkitRequestFullscreen) {
    docEl.webkitRequestFullscreen();
  } else if (docEl.msRequestFullscreen) {
    docEl.msRequestFullscreen();
  }
}

/**
 * Exit full screen mode
 */
function exitFullScreen(): void {
  const doc = document as Document & {
    mozCancelFullScreen?: () => Promise<void>;
    webkitExitFullscreen?: () => Promise<void>;
  };

  if (doc.exitFullscreen) {
    doc.exitFullscreen();
  } else if (doc.mozCancelFullScreen) {
    doc.mozCancelFullScreen();
  } else if (doc.webkitExitFullscreen) {
    doc.webkitExitFullscreen();
  }
}

/**
 * Display person info (placeholder)
 */
function displayPersonInfo(_personId: number): void {
  // Not implemented
}

/** True while someone is using the kiosk: a modal is open, the page is hidden, or there was a recent tap or key. */
function isUserBusy(): boolean {
  return (
    document.hidden ||
    document.querySelector(".modal.show") !== null ||
    Date.now() - kioskState.lastInteractionAt < IDLE_BEFORE_POLL_MS
  );
}

/** Poll again after the delay; if the kiosk is in use at that point, retry shortly instead. */
function scheduleNextRefresh(delayMs: number = POLL_INTERVAL_MS): void {
  if (kioskState.refreshTimer) {
    clearTimeout(kioskState.refreshTimer);
  }
  kioskState.refreshTimer = setTimeout(() => {
    if (isUserBusy()) {
      scheduleNextRefresh(BUSY_RETRY_MS);
      return;
    }
    heartbeat();
    scheduleNextRefresh();
  }, delayMs);
}

/**
 * Show the guest registration modal, resetting any previous form state.
 */
function showGuestRegistrationModal(): void {
  const modalEl = document.getElementById("guestRegistrationModal");
  if (!modalEl) return;

  // Reset all form fields in one call
  const form = document.getElementById("guestRegistrationForm") as HTMLFormElement | null;
  form?.reset();

  // Clear validation state
  modalEl.querySelectorAll(".is-invalid").forEach((el) => {
    el.classList.remove("is-invalid");
  });
  const errorDiv = document.getElementById("guestFormError");
  if (errorDiv) {
    errorDiv.textContent = "";
    errorDiv.style.display = "none";
  }

  // Re-enable submit button
  const submitBtn = document.getElementById("guestRegisterSubmitBtn") as HTMLButtonElement | null;
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = `<i class="fa-solid fa-user-plus me-1"></i>${i18next.t("Register & Check In")}`;
  }

  window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

/**
 * Submit the guest registration form
 */
function submitGuestRegistration(): void {
  const firstNameEl = document.getElementById("guestFirstName") as HTMLInputElement;
  const lastNameEl = document.getElementById("guestLastName") as HTMLInputElement;
  const phoneEl = document.getElementById("guestPhone") as HTMLInputElement;
  const emailEl = document.getElementById("guestEmail") as HTMLInputElement;
  const birthYearEl = document.getElementById("guestBirthYear") as HTMLInputElement;
  const birthMonthEl = document.getElementById("guestBirthMonth") as HTMLSelectElement;
  const birthDayEl = document.getElementById("guestBirthDay") as HTMLInputElement;
  const errorDiv = document.getElementById("guestFormError");
  const submitBtn = document.getElementById("guestRegisterSubmitBtn") as HTMLButtonElement | null;

  // The Enter key bypasses the disabled button, so guard here too
  if (submitBtn?.disabled) return;

  // Clear previous validation
  [firstNameEl, lastNameEl, phoneEl, emailEl].forEach((el) => {
    el.classList.remove("is-invalid");
  });
  if (errorDiv) {
    errorDiv.textContent = "";
    errorDiv.style.display = "none";
  }

  // Validate required fields
  let valid = true;
  if (!firstNameEl.value.trim()) {
    firstNameEl.classList.add("is-invalid");
    valid = false;
  }
  if (!lastNameEl.value.trim()) {
    lastNameEl.classList.add("is-invalid");
    valid = false;
  }
  if (!phoneEl.value.trim() && !emailEl.value.trim()) {
    phoneEl.classList.add("is-invalid");
    emailEl.classList.add("is-invalid");
    if (errorDiv) {
      errorDiv.textContent = i18next.t("A phone number or email address is required");
      errorDiv.style.display = "block";
    }
    valid = false;
  }
  if (!valid) return;

  // Disable submit button to prevent double-submit
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>${i18next.t("Registering...")}`;
  }

  const payload: Record<string, string | number> = {
    FirstName: firstNameEl.value.trim(),
    LastName: lastNameEl.value.trim(),
  };
  if (phoneEl.value.trim()) payload.Phone = phoneEl.value.trim();
  if (emailEl.value.trim()) payload.Email = emailEl.value.trim();
  if (birthYearEl.value) payload.BirthYear = parseInt(birthYearEl.value, 10);
  if (birthMonthEl.value) payload.BirthMonth = parseInt(birthMonthEl.value, 10);
  if (birthDayEl.value) payload.BirthDay = parseInt(birthDayEl.value, 10);

  APIRequest({
    path: "registerGuest",
    method: "POST",
    data: JSON.stringify(payload),
  })
    .done((data: RegisterGuestResponse) => {
      // Close modal
      const modalEl = document.getElementById("guestRegistrationModal");
      if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();

      // An empty group (or a countdown/ended screen) replaced the roster markup, so there is no
      // list to add to: reload and let the server's roster, which includes guests, render it.
      if ($("#checkedInList").length === 0) {
        window.location.reload();
        return;
      }

      // Render the guest directly in the Checked In list
      const guestMember: ClassMember = {
        displayName: `${data.FirstName} ${data.LastName}`,
        firstName: data.FirstName,
        classRole: "",
        personId: data.Id,
        status: data.status, // 1 = checked in (server confirmed check-in)
        gender: data.Gender,
        hasPhoto: data.hasPhoto,
        age: data.age,
        birthdayThisMonth: false,
        birthdayUpcoming: false,
        birthdayRecent: false,
        birthdayToday: false,
        birthDay: null,
        birthMonth: null,
        familyId: data.familyId,
        isGuest: true,
      };
      renderClassMember(guestMember);
      updateMemberCounts();

      showKioskNotification(
        i18next.t("{{name}} registered and checked in!", {
          name: `${data.FirstName} ${data.LastName}`,
        }),
        "success",
      );
    })
    .fail((xhr: JQuery.jqXHR) => {
      // Re-enable submit button on failure
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="fa-solid fa-user-plus me-1"></i>${i18next.t("Register & Check In")}`;
      }
      const message =
        (xhr.responseJSON as { message?: string } | undefined)?.message ||
        i18next.t("Failed to register guest. Please try again.");
      if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = "block";
      }
    });
}

/**
 * Start the event loop
 */
function startEventLoop(): void {
  heartbeat();
  for (const type of ["pointerdown", "keydown"]) {
    document.addEventListener(
      type,
      () => {
        kioskState.lastInteractionAt = Date.now();
      },
      { passive: true },
    );
  }
  scheduleNextRefresh();
}

/**
 * Stop the event loop
 */
function stopEventLoop(): void {
  if (kioskState.refreshTimer) {
    clearTimeout(kioskState.refreshTimer);
    kioskState.refreshTimer = undefined;
  }
}

// Export the kiosk object for global access
// Use Object.defineProperty for the notificationsEnabled getter to reflect actual state
export const kiosk: KioskJSOM = {
  get notificationsEnabled() {
    return kioskState.notificationsEnabled;
  },
  get checkinByEnabled() {
    return kioskState.checkinByEnabled;
  },
  escapeHtml,
  APIRequest,
  getPhotoUrl,
  getFamilyPhotoUrl,
  updateCheckinByLine,
  renderClassMember,
  updateMemberCounts,
  renderBirthdaySection,
  renderBirthdayCard,
  updateActiveClassMembers,
  renderNoMembersMessage,
  renderErrorMessage,
  heartbeat,
  renderCountdown,
  renderEventEnded,
  startCountdown,
  renderStatusCard,
  checkInPerson,
  checkOutPerson,
  checkOutAll,
  alertAll,
  setCheckedOut,
  setCheckedIn,
  triggerNotification,
  showKioskNotification,
  enterFullScreen,
  exitFullScreen,
  displayPersonInfo,
  startEventLoop,
  stopEventLoop,
  setCheckinByEnabled,
  resolveCheckinByModal,
  cancelCheckinByModal,
  scheduleNextRefresh,
  showGuestRegistrationModal,
  submitGuestRegistration,
};

// Attach to window.CRM.kiosk for global access (for external use)
window.CRM = window.CRM ?? {};
window.CRM.kiosk = kiosk;
