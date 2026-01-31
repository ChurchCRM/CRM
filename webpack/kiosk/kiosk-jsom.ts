/**
 * Kiosk JSOM (JavaScript Object Model)
 * 
 * Main kiosk logic for Sunday School check-in functionality.
 * Requires: moment.js (loaded globally), jQuery
 */

import type { ClassMember, PersonApiData, ActiveClassMembersResponse, HeartbeatResponse, KioskAssignment, AjaxOptions, KioskJSOM } from './types';

// Declare moment as global (loaded via CDN in header)
declare const moment: typeof import('moment');

// Helper to access window.CRM safely
function getCRM(): any {
    return (window as any).CRM || {};
}

// Module-level state (avoids global window.CRM pollution)
const kioskState = {
    notificationsEnabled: false,
    kioskAssignmentId: undefined as KioskAssignment | undefined,
    kioskEventLoop: undefined as ReturnType<typeof setInterval> | undefined,
    countdownInterval: undefined as ReturnType<typeof setInterval> | undefined,
};

/**
 * HTML escape helper to prevent XSS
 */
function escapeHtml(text: string | null | undefined): string {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

/**
 * Make API request to kiosk device endpoints
 */
function APIRequest(options: AjaxOptions): JQuery.jqXHR {
    const ajaxOptions: JQuery.AjaxSettings = {
        method: options.method || 'GET',
        url: getCRM().root + '/kiosk/device/' + options.path,
        dataType: 'json',
        contentType: 'application/json',
        data: options.data,
    };
    return $.ajax(ajaxOptions);
}

/**
 * Get photo URL for a person
 */
function getPhotoUrl(personId: number): string {
    return getCRM().root + '/kiosk/device/activeClassMember/' + personId + '/photo';
}

/**
 * Render a class member card in the appropriate section
 */
function renderClassMember(classMember: ClassMember): void {
    const existingDiv = $('#personId-' + classMember.personId);
    if (existingDiv.length > 0) {
        // Card already exists, just update status
    } else {
        // Create member row
        const memberRow = $('<div>', {
            id: 'personId-' + classMember.personId,
            class: 'kiosk-member',
        });

        // Avatar
        const avatarDiv = $('<div>', { class: 'kiosk-member-avatar' });
        if (classMember.hasPhoto) {
            avatarDiv.append(
                $('<img>', {
                    src: getPhotoUrl(classMember.personId),
                    alt: classMember.displayName,
                }),
            );
        } else {
            // Gender icon - 1 = Male, 2 = Female
            let iconClass = 'fas fa-user';
            let iconColor = '#6c757d';
            if (classMember.gender === 1) {
                iconClass = 'fas fa-male';
                iconColor = '#007bff';
            } else if (classMember.gender === 2) {
                iconClass = 'fas fa-female';
                iconColor = '#e83e8c';
            }
            avatarDiv.append(
                $('<i>', {
                    class: iconClass,
                    style: 'font-size: 24px; color: ' + iconColor + ';',
                }),
            );
        }

        // Info section
        const infoDiv = $('<div>', { class: 'kiosk-member-info' });

        // Name with optional birthday cake for upcoming/recent birthdays
        const nameDiv = $('<div>', { class: 'kiosk-member-name' });
        nameDiv.text(classMember.displayName);
        if (classMember.birthdayUpcoming || classMember.birthdayRecent) {
            nameDiv.append(
                $('<i>', {
                    class: 'fas fa-birthday-cake ml-2',
                    style: 'color: #e83e8c;',
                    title: classMember.birthdayUpcoming ? 'Birthday coming up!' : 'Recent birthday!',
                }),
            );
        }
        infoDiv.append(nameDiv);

        // Show age if available
        if (classMember.age !== null && classMember.age >= 0) {
            infoDiv.append(
                $('<div>', {
                    class: 'kiosk-member-age',
                    text: classMember.age + ' yrs',
                }),
            );
        }

        // Actions - icon-only buttons
        const actionsDiv = $('<div>', { class: 'kiosk-member-actions' });

        const checkinBtn = $('<button>', {
            class: 'kiosk-btn kiosk-btn-checkin checkinButton',
            'data-personid': classMember.personId,
            title: 'Check In',
        }).append($('<i>', { class: 'fas fa-sign-in-alt' }));

        actionsDiv.append(checkinBtn);

        // Only show alert button for checked-in students when notifications are enabled
        if (classMember.status == 1 && kioskState.notificationsEnabled) {
            const alertBtn = $('<button>', {
                class: 'kiosk-btn kiosk-btn-alert parentAlertButton',
                'data-personid': classMember.personId,
                title: 'Parent Alert',
            }).append($('<i>', { class: 'fas fa-bell' }));
            actionsDiv.append(alertBtn);
        }

        memberRow.append(avatarDiv).append(infoDiv).append(actionsDiv);

        // Add to appropriate section based on status
        if (classMember.status == 1) {
            $('#checkedInList').append(memberRow);
        } else {
            $('#notCheckedInList').append(memberRow);
        }
    }

    // Update visual state
    if (classMember.status == 1) {
        setCheckedIn(classMember.personId);
    } else {
        setCheckedOut(classMember.personId);
    }
}

/**
 * Update the member counts in the UI
 */
function updateMemberCounts(): void {
    const checkedIn = $('#checkedInList .kiosk-member').length;
    const notCheckedIn = $('#notCheckedInList .kiosk-member').length;

    $('#checkedInCount').text(checkedIn);
    $('#notCheckedInCount').text(notCheckedIn);
    $('#checkedInSectionCount').text(checkedIn);
    $('#notCheckedInSectionCount').text(notCheckedIn);

    // Show/hide Checkout All button based on checked-in count
    if (checkedIn > 0) {
        $('#checkoutAllBtn').show();
    } else {
        $('#checkoutAllBtn').hide();
    }

    // Show/hide empty state messages
    if (checkedIn === 0) {
        if ($('#checkedInList .kiosk-empty').length === 0) {
            $('#checkedInList').html(
                '<div class="kiosk-empty">' +
                    '<i class="fas fa-user-clock"></i>' +
                    '<p>No one checked in yet</p>' +
                    '</div>',
            );
        }
    } else {
        $('#checkedInList .kiosk-empty').remove();
    }

    if (notCheckedIn === 0) {
        if ($('#notCheckedInList .kiosk-empty').length === 0) {
            $('#notCheckedInList').html(
                '<div class="kiosk-empty">' +
                    '<i class="fas fa-check-double text-success"></i>' +
                    '<p>Everyone is here!</p>' +
                    '</div>',
            );
        }
    } else {
        $('#notCheckedInList .kiosk-empty').remove();
    }
}

/**
 * Render the birthday section banner
 */
function renderBirthdaySection(birthdayPeople: ClassMember[]): void {
    const birthdayList = $('#birthdayList');
    const birthdayBanner = $('#birthdayBanner');
    birthdayList.empty();

    if (!birthdayPeople || birthdayPeople.length === 0) {
        // Hide banner when no birthdays
        birthdayBanner.hide();
        $('.kiosk-section').removeClass('has-birthday-banner');
        $('#birthdayCount').text('0');
        return;
    }

    // Show banner and adjust section heights
    birthdayBanner.show();
    $('.kiosk-section').addClass('has-birthday-banner');

    // Sort by birthday (upcoming first, then recent)
    const monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // Separate upcoming and recent
    const upcoming = birthdayPeople.filter((p) => p.birthdayUpcoming);
    const recent = birthdayPeople.filter((p) => p.birthdayRecent && !p.birthdayUpcoming);

    // Render all birthday cards in a horizontal layout
    upcoming.forEach((person) => {
        birthdayList.append(renderBirthdayCard(person, monthNames, 'upcoming'));
    });
    recent.forEach((person) => {
        birthdayList.append(renderBirthdayCard(person, monthNames, 'recent'));
    });

    $('#birthdayCount').text(birthdayPeople.length);
}

/**
 * Render a birthday card
 */
function renderBirthdayCard(person: ClassMember, monthNames: string[], cardType: string): JQuery {
    let cardClass = 'birthday-card';
    const today = new Date();
    const isToday =
        person.birthdayToday || (person.birthMonth === today.getMonth() + 1 && person.birthDay === today.getDate());

    if (isToday) {
        cardClass += ' today';
    } else if (cardType === 'upcoming') {
        cardClass += ' upcoming';
    } else if (cardType === 'recent') {
        cardClass += ' recent';
    }

    const card = $('<div>', { class: cardClass });

    // Avatar
    const avatarDiv = $('<div>', { class: 'birthday-avatar' });
    if (person.hasPhoto) {
        avatarDiv.append(
            $('<img>', {
                src: getPhotoUrl(person.personId),
                alt: person.displayName,
            }),
        );
    } else {
        avatarDiv.append($('<i>', { class: 'fas fa-birthday-cake' }));
    }
    card.append(avatarDiv);

    // Info
    const infoDiv = $('<div>', { class: 'birthday-info' });
    infoDiv.append($('<div>', { class: 'birthday-name', text: person.firstName }));

    let dateText = monthNames[person.birthMonth || 0] + ' ' + person.birthDay;
    let dateClass = 'birthday-date';
    if (isToday) {
        dateText = '🎉 Today!';
        dateClass += ' today';
    }
    infoDiv.append($('<div>', { class: dateClass, text: dateText }));
    card.append(infoDiv);

    // Age badge (if age is available and it's upcoming/today)
    if (person.age !== null && (cardType === 'upcoming' || isToday)) {
        const turningAge = isToday ? person.age : person.age + 1;
        card.append($('<span>', { class: 'birthday-age-badge', text: 'Turning ' + turningAge }));
    }

    return card;
}

/**
 * Update active class members from API
 */
function updateActiveClassMembers(): void {
    APIRequest({
        path: 'activeClassMembers',
    })
        .done((data: ActiveClassMembersResponse) => {
            if (!data || !data.People || data.People.length === 0) {
                // No members found - show helpful debug info
                $('#classMemberContainer').html(renderNoMembersMessage());
                return;
            }

            // Store notifications enabled flag for use in rendering
            kioskState.notificationsEnabled = data.notificationsEnabled || false;

            // Clear loading state on first load
            if ($('#notCheckedInList .fa-spinner').length > 0) {
                $('#checkedInList').empty();
                $('#notCheckedInList').empty();
                $('#birthdayList').empty();
            }

            // Update group name in headers if provided
            if (data.GroupName) {
                $('.kiosk-group-name').text(data.GroupName);
            }

            // Collect birthday people for separate section
            const birthdayPeople: ClassMember[] = [];

            data.People.forEach((d: PersonApiData) => {
                const memberData: ClassMember = {
                    displayName: d.FirstName + ' ' + d.LastName,
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
            let errorMessage = 'Unable to load class members';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.status === 500) {
                errorMessage = 'Server error - the event may not be linked to a group';
            }
            $('#classMemberContainer').html(renderErrorMessage(errorMessage, xhr.status));
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
        '<h3 class="card-title"><i class="fas fa-users-slash mr-2"></i>No Class Members Found</h3>' +
        '</div>' +
        '<div class="card-body">' +
        '<div class="kiosk-status-icon text-warning">' +
        '<i class="fas fa-users-slash"></i>' +
        '</div>' +
        '<p class="text-muted">This kiosk is assigned to an event, but no members are available for check-in.</p>' +
        '<div class="kiosk-instructions">' +
        '<h5><i class="fas fa-info-circle mr-2"></i>Possible Causes</h5>' +
        '<ol>' +
        '<li><strong>Event not linked to a Group:</strong> Edit the event and associate it with a Sunday School or other group</li>' +
        '<li><strong>Group has no members:</strong> Add people to the group that is linked to this event</li>' +
        '<li><strong>Event timing:</strong> The event may not be currently active (check start/end times)</li>' +
        '</ol>' +
        '</div>' +
        '<div class="kiosk-instructions mt-3">' +
        '<h5><i class="fas fa-wrench mr-2"></i>How to Fix</h5>' +
        '<ol class="small">' +
        '<li>Go to <strong>Events</strong> in ChurchCRM</li>' +
        '<li>Edit this event and set the <strong>Group</strong> field</li>' +
        '<li>Ensure the group has members assigned</li>' +
        '<li>Refresh this kiosk page</li>' +
        '</ol>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>'
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
        '<h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i>Error Loading Members</h3>' +
        '</div>' +
        '<div class="card-body">' +
        '<div class="kiosk-status-icon text-danger">' +
        '<i class="fas fa-exclamation-triangle"></i>' +
        '</div>' +
        '<p class="text-muted">' +
        escapeHtml(message) +
        '</p>' +
        (statusCode ? '<p class="small text-muted">HTTP Status: ' + statusCode + '</p>' : '') +
        '<div class="kiosk-instructions">' +
        '<h5><i class="fas fa-lightbulb mr-2"></i>Troubleshooting</h5>' +
        '<ul>' +
        '<li>Verify the event is linked to a <strong>Group</strong></li>' +
        '<li>Check that the event start/end times are correct</li>' +
        '<li>Ensure the group has members</li>' +
        '<li>Check the server logs for more details</li>' +
        '</ul>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>'
    );
}

/**
 * Heartbeat function to check kiosk status
 */
function heartbeat(): void {
    APIRequest({
        path: 'heartbeat',
    }).done((data: HeartbeatResponse) => {
        const thisAssignment: KioskAssignment | null = data.Assignment ? JSON.parse(data.Assignment) : null;
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

        if (data.Commands === 'Reload') {
            location.reload();
        }

        if (data.Commands === 'Identify') {
            clearInterval(kioskState.kioskEventLoop);
            $('#event').hide();
            $('#noEvent').show();
            $('#noEvent').html(
                renderStatusCard('info', 'fa-tablet-alt', 'Kiosk Identification', data.Name, null),
            );
            setTimeout(() => {
                location.reload();
            }, 2000);
            return;
        }

        if (data.Accepted) {
            const Assignment: KioskAssignment | null = data.Assignment ? JSON.parse(data.Assignment) : null;
            if (Assignment && Assignment.AssignmentType == 1) {
                const eventStart = moment(Assignment.Event.Start);
                const eventEnd = moment(Assignment.Event.End);
                const now = moment();

                $('#eventTitle').text(Assignment.Event.Title);
                $('#startTime').text(eventStart.format('MMMM Do YYYY, h:mm:ss a'));
                $('#endTime').text(eventEnd.format('MMMM Do YYYY, h:mm:ss a'));

                if (now.isBefore(eventStart)) {
                    // Event hasn't started yet - show countdown
                    $('#noEvent').hide();
                    $('#event').show();
                    $('#classMemberContainer').html(
                        renderCountdown(eventStart, Assignment.Event.Title),
                    );
                    startCountdown(eventStart);
                } else if (now.isAfter(eventEnd)) {
                    // Event has ended
                    $('#noEvent').hide();
                    $('#event').show();
                    $('#classMemberContainer').html(renderEventEnded(Assignment.Event.Title));
                } else {
                    // Event is active - show class members
                    updateActiveClassMembers();
                    $('#noEvent').hide();
                    $('#event').show();
                }
            } else {
                $('#noEvent').show();
                $('#noEvent').html(
                    renderStatusCard(
                        'success',
                        'fa-check-circle',
                        'Kiosk Ready',
                        data.Name,
                        "<p class='text-muted mb-0'>This kiosk is accepted but has no active event assignment.</p>" +
                            "<div class='kiosk-instructions'>" +
                            "<h5><i class='fas fa-info-circle'></i> Next Steps</h5>" +
                            '<ol>' +
                            '<li>Go to <strong>Kiosk Manager</strong> in the admin menu</li>' +
                            '<li>Find this kiosk in the device list</li>' +
                            '<li>Use the <strong>Assign</strong> dropdown to select an event</li>' +
                            '</ol></div>',
                    ),
                );
                $('#event').hide();
            }
        } else {
            $('#noEvent').show();
            $('#noEvent').html(
                renderStatusCard(
                    'pending',
                    'fa-hourglass-half',
                    'Awaiting Acceptance',
                    data.Name,
                    "<p class='text-muted'>This kiosk has registered but needs to be accepted by an administrator before it can be used.</p>" +
                        "<div class='kiosk-instructions'>" +
                        "<h5><i class='fas fa-info-circle'></i> How to Accept This Kiosk</h5>" +
                        '<ol>' +
                        '<li>Log in to ChurchCRM as an administrator</li>' +
                        '<li>Navigate to <strong>Admin → Kiosk Manager</strong></li>' +
                        '<li>Find the kiosk named <strong>' +
                        escapeHtml(data.Name) +
                        '</strong> in the list</li>' +
                        "<li>Click the <strong><i class='fas fa-check'></i> Accept</strong> button</li>" +
                        '<li>Assign an event to the kiosk using the dropdown</li>' +
                        '</ol>' +
                        "<p class='mb-0'><small>This page will automatically update once the kiosk is accepted.</small></p>" +
                        '</div>',
                ),
            );
            $('#event').hide();
        }
    });
}

/**
 * Render countdown timer before event starts
 */
function renderCountdown(eventStart: moment.Moment, eventTitle: string): string {
    return (
        '<div class="kiosk-status-container">' +
        '<div class="card kiosk-status-card card-primary">' +
        '<div class="card-header">' +
        '<h3 class="card-title"><i class="fas fa-clock mr-2"></i>Check-in Opens Soon</h3>' +
        '</div>' +
        '<div class="card-body text-center">' +
        '<div class="kiosk-status-icon text-primary">' +
        '<i class="fas fa-clock"></i>' +
        '</div>' +
        '<p class="text-muted mb-3">Check-in for <strong>' +
        escapeHtml(eventTitle) +
        '</strong> will begin at:</p>' +
        '<h4 class="text-primary mb-4">' +
        eventStart.format('h:mm A') +
        '</h4>' +
        '<div class="row justify-content-center mb-4">' +
        '<div class="col-auto text-center px-3">' +
        '<div id="countdown-days" class="kiosk-countdown text-dark">00</div>' +
        '<small class="text-muted">Days</small>' +
        '</div>' +
        '<div class="col-auto text-center px-3">' +
        '<div id="countdown-hours" class="kiosk-countdown text-dark">00</div>' +
        '<small class="text-muted">Hours</small>' +
        '</div>' +
        '<div class="col-auto text-center px-3">' +
        '<div id="countdown-minutes" class="kiosk-countdown text-dark">00</div>' +
        '<small class="text-muted">Minutes</small>' +
        '</div>' +
        '<div class="col-auto text-center px-3">' +
        '<div id="countdown-seconds" class="kiosk-countdown text-primary">00</div>' +
        '<small class="text-muted">Seconds</small>' +
        '</div>' +
        '</div>' +
        '<p class="mb-0 text-muted"><small>This page will automatically refresh when check-in opens.</small></p>' +
        '</div>' +
        '</div>' +
        '</div>'
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
        '<h3 class="card-title"><i class="fas fa-calendar-check mr-2"></i>Event Has Ended</h3>' +
        '</div>' +
        '<div class="card-body text-center">' +
        '<div class="kiosk-status-icon text-success">' +
        '<i class="fas fa-calendar-check"></i>' +
        '</div>' +
        '<p class="text-muted mb-0">Check-in for <strong>' +
        escapeHtml(eventTitle) +
        '</strong> has closed.</p>' +
        '<p class="text-muted">Thank you for attending!</p>' +
        '</div>' +
        '</div>' +
        '</div>'
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

        $('#countdown-days').text(String(days).padStart(2, '0'));
        $('#countdown-hours').text(String(hours).padStart(2, '0'));
        $('#countdown-minutes').text(String(minutes).padStart(2, '0'));
        $('#countdown-seconds').text(String(seconds).padStart(2, '0'));
    }, 1000);
}

/**
 * Render a status card
 */
function renderStatusCard(statusType: string, iconClass: string, title: string, kioskName: string, bodyContent: string | null): string {
    // Use Bootstrap 4.6.2 card-primary pattern consistent with ChurchCRM
    let cardClass = 'card-primary';
    let iconColorClass = 'text-primary';

    if (statusType === 'pending') {
        cardClass = 'card-warning';
        iconColorClass = 'text-warning';
    } else if (statusType === 'success') {
        cardClass = 'card-success';
        iconColorClass = 'text-success';
    } else if (statusType === 'info') {
        cardClass = 'card-info';
        iconColorClass = 'text-info';
    }

    // Validate iconClass to prevent XSS via class injection (only allow known FA icons)
    const safeIconClass = /^fa-[a-z0-9-]+$/.test(iconClass) ? iconClass : 'fa-question-circle';

    return (
        '<div class="card kiosk-status-card ' +
        cardClass +
        '">' +
        '<div class="card-header">' +
        '<h3 class="card-title"><i class="fas ' +
        safeIconClass +
        ' mr-2"></i>' +
        escapeHtml(title) +
        '</h3>' +
        '</div>' +
        '<div class="card-body text-center">' +
        '<div class="kiosk-status-icon ' +
        iconColorClass +
        '">' +
        '<i class="fas ' +
        safeIconClass +
        '"></i>' +
        '</div>' +
        '<div class="kiosk-name"><i class="fas fa-tablet-alt mr-2"></i>' +
        escapeHtml(kioskName) +
        '</div>' +
        (bodyContent ? bodyContent : '') +
        '</div></div>'
    );
}

/**
 * Check in a person
 */
function checkInPerson(personId: number): void {
    APIRequest({
        path: 'checkin',
        method: 'POST',
        data: JSON.stringify({ PersonId: personId }),
    }).done(() => {
        setCheckedIn(personId);
    });
}

/**
 * Check out a person
 */
function checkOutPerson(personId: number): void {
    APIRequest({
        path: 'checkout',
        method: 'POST',
        data: JSON.stringify({ PersonId: personId }),
    }).done(() => {
        setCheckedOut(personId);
    });
}

/**
 * Check out all people
 */
function checkOutAll(): void {
    // Disable button during processing
    const $btn = $('#checkoutAllBtn');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Processing...');

    APIRequest({
        path: 'checkoutAll',
        method: 'POST',
    })
        .done(() => {
            // Move all checked-in people to not checked in
            $('#checkedInList .kiosk-member').each(function () {
                const personId = $(this).attr('id')?.replace('personId-', '');
                if (personId) {
                    setCheckedOut(parseInt(personId, 10));
                }
            });
            $btn.html(originalHtml).prop('disabled', false);
        })
        .fail(() => {
            $btn.html(originalHtml).prop('disabled', false);
        });
}

/**
 * Set a person as checked out in the UI
 */
function setCheckedOut(personId: number): void {
    const $personDiv = $('#personId-' + personId);
    const $personDivButton = $personDiv.find('.checkoutButton');

    // Update button - icon only
    $personDivButton.removeClass('checkoutButton kiosk-btn-checkout');
    $personDivButton.addClass('checkinButton kiosk-btn-checkin');
    $personDivButton.html('<i class="fas fa-sign-in-alt"></i>');
    $personDivButton.attr('title', 'Check In');

    // Remove checked-in styling
    $personDiv.removeClass('checked-in');

    // Move to Not Checked In section if not already there
    if ($personDiv.closest('#notCheckedInList').length === 0) {
        $personDiv.detach().appendTo('#notCheckedInList');
        updateMemberCounts();
    }
}

/**
 * Set a person as checked in in the UI
 */
function setCheckedIn(personId: number): void {
    const $personDiv = $('#personId-' + personId);
    const $personDivButton = $personDiv.find('.checkinButton');

    // Update button - icon only
    $personDivButton.removeClass('checkinButton kiosk-btn-checkin');
    $personDivButton.addClass('checkoutButton kiosk-btn-checkout');
    $personDivButton.html('<i class="fas fa-sign-out-alt"></i>');
    $personDivButton.attr('title', 'Check Out');

    // Add checked-in styling
    $personDiv.addClass('checked-in');

    // Move to Checked In section if not already there
    if ($personDiv.closest('#checkedInList').length === 0) {
        $personDiv.detach().appendTo('#checkedInList');
        updateMemberCounts();
    }
}

/**
 * Trigger a parent notification
 */
function triggerNotification(personId: number): void {
    // Get the student's name for feedback
    const $personDiv = $('#personId-' + personId);
    const studentName = $personDiv.find('.kiosk-member-name').text().trim() || 'Student';

    // Visual feedback - disable button and show sending state
    const $alertBtn = $personDiv.find('.parentAlertButton');
    $alertBtn.prop('disabled', true).addClass('sending');
    $alertBtn.find('i').removeClass('fa-bell').addClass('fa-spinner fa-spin');

    APIRequest({
        path: 'triggerNotification',
        method: 'POST',
        data: JSON.stringify({ PersonId: personId }),
    })
        .done(() => {
            // Show success notification
            if (typeof getCRM().notify === 'function') {
                getCRM().notify('Parent alert sent for ' + studentName, { type: 'success', delay: 4000 });
            } else {
                // Fallback for kiosk view without full CRM.notify
                showKioskNotification('Parent alert sent for ' + studentName, 'success');
            }
            // Reset button after short delay
            setTimeout(() => {
                $alertBtn.prop('disabled', false).removeClass('sending');
                $alertBtn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-bell');
            }, 2000);
        })
        .fail(() => {
            // Show error notification
            if (typeof getCRM().notify === 'function') {
                getCRM().notify('Failed to send parent alert', { type: 'error', delay: 4000 });
            } else {
                showKioskNotification('Failed to send parent alert', 'error');
            }
            // Reset button
            $alertBtn.prop('disabled', false).removeClass('sending');
            $alertBtn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-bell');
        });
}

/**
 * Simple notification for kiosk mode (no Notyf dependency)
 */
function showKioskNotification(message: string, type: string): void {
    const $notification = $('<div>', {
        class: 'kiosk-notification kiosk-notification-' + type,
        text: message,
    });
    $('body').append($notification);

    // Animate in
    setTimeout(() => {
        $notification.addClass('show');
    }, 10);

    // Remove after delay
    setTimeout(() => {
        $notification.removeClass('show');
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

/**
 * Start the event loop
 */
function startEventLoop(): void {
    kioskState.kioskEventLoop = setInterval(heartbeat, 2000);
}

/**
 * Stop the event loop
 */
function stopEventLoop(): void {
    if (kioskState.kioskEventLoop) {
        clearInterval(kioskState.kioskEventLoop);
    }
}

// Export the kiosk object for global access
export const kiosk: KioskJSOM = {
    notificationsEnabled: false,
    escapeHtml,
    APIRequest,
    getPhotoUrl,
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
    setCheckedOut,
    setCheckedIn,
    triggerNotification,
    showKioskNotification,
    enterFullScreen,
    exitFullScreen,
    displayPersonInfo,
    startEventLoop,
    stopEventLoop,
};

// Attach to window.CRM.kiosk for global access (for external use)
if (!(window as any).CRM) {
    (window as any).CRM = {};
}
(window as any).CRM.kiosk = kiosk;
