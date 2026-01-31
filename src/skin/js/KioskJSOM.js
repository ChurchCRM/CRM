/**
 * Kiosk JSOM (JavaScript Object Model)
 * Requires: moment.js (loaded globally), jQuery
 */

window.CRM.kiosk = {
    APIRequest: function (options) {
        if (!options.method) {
            options.method = "GET";
        }
        options.url = window.CRM.root + "/kiosk/device/" + options.path;
        options.dataType = "json";
        options.contentType = "application/json";
        return $.ajax(options);
    },

    getPhotoUrl: function (personId) {
        return window.CRM.root + "/kiosk/device/activeClassMember/" + personId + "/photo";
    },

    renderClassMember: function (classMember) {
        let existingDiv = $("#personId-" + classMember.personId);
        if (existingDiv.length > 0) {
            // Card already exists, just update status
        } else {
            // Create member row
            var memberRow = $("<div>", {
                id: "personId-" + classMember.personId,
                class: "kiosk-member",
            });

            // Avatar
            var avatarDiv = $("<div>", { class: "kiosk-member-avatar" });
            if (classMember.hasPhoto) {
                avatarDiv.append(
                    $("<img>", {
                        src: window.CRM.kiosk.getPhotoUrl(classMember.personId),
                        alt: classMember.displayName,
                    }),
                );
            } else {
                // Gender icon - 1 = Male, 2 = Female
                var iconClass = "fas fa-user";
                var iconColor = "#6c757d";
                if (classMember.gender === 1) {
                    iconClass = "fas fa-male";
                    iconColor = "#007bff";
                } else if (classMember.gender === 2) {
                    iconClass = "fas fa-female";
                    iconColor = "#e83e8c";
                }
                avatarDiv.append(
                    $("<i>", {
                        class: iconClass,
                        style: "font-size: 24px; color: " + iconColor + ";",
                    }),
                );
            }

            // Info section
            var infoDiv = $("<div>", { class: "kiosk-member-info" });

            // Name with optional birthday cake for upcoming/recent birthdays
            var nameDiv = $("<div>", { class: "kiosk-member-name" });
            nameDiv.text(classMember.displayName);
            if (classMember.birthdayUpcoming || classMember.birthdayRecent) {
                nameDiv.append(
                    $("<i>", {
                        class: "fas fa-birthday-cake ml-2",
                        style: "color: #e83e8c;",
                        title: classMember.birthdayUpcoming ? "Birthday coming up!" : "Recent birthday!",
                    }),
                );
            }
            infoDiv.append(nameDiv);

            // Show age if available
            if (classMember.age !== null && classMember.age >= 0) {
                infoDiv.append(
                    $("<div>", {
                        class: "kiosk-member-age",
                        text: classMember.age + " yrs",
                    }),
                );
            }

            // Actions - icon-only buttons
            var actionsDiv = $("<div>", { class: "kiosk-member-actions" });

            var checkinBtn = $("<button>", {
                class: "kiosk-btn kiosk-btn-checkin checkinButton",
                "data-personid": classMember.personId,
                title: "Check In",
            }).append($("<i>", { class: "fas fa-sign-in-alt" }));

            var alertBtn = $("<button>", {
                class: "kiosk-btn kiosk-btn-alert parentAlertButton",
                "data-personid": classMember.personId,
                title: "Parent Alert",
            }).append($("<i>", { class: "fas fa-bell" }));

            actionsDiv.append(checkinBtn).append(alertBtn);

            memberRow.append(avatarDiv).append(infoDiv).append(actionsDiv);

            // Add to appropriate section based on status
            if (classMember.status == 1) {
                $("#checkedInList").append(memberRow);
            } else {
                $("#notCheckedInList").append(memberRow);
            }
        }

        // Update visual state
        if (classMember.status == 1) {
            window.CRM.kiosk.setCheckedIn(classMember.personId);
        } else {
            window.CRM.kiosk.setCheckedOut(classMember.personId);
        }
    },

    updateMemberCounts: function () {
        var checkedIn = $("#checkedInList .kiosk-member").length;
        var notCheckedIn = $("#notCheckedInList .kiosk-member").length;

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

        // Show/hide empty state messages
        if (checkedIn === 0) {
            if ($("#checkedInList .kiosk-empty").length === 0) {
                $("#checkedInList").html(
                    '<div class="kiosk-empty">' +
                        '<i class="fas fa-user-clock"></i>' +
                        "<p>No one checked in yet</p>" +
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
                        '<i class="fas fa-check-double text-success"></i>' +
                        "<p>Everyone is here!</p>" +
                        "</div>",
                );
            }
        } else {
            $("#notCheckedInList .kiosk-empty").remove();
        }
    },

    renderBirthdaySection: function (birthdayPeople) {
        var birthdayList = $("#birthdayList");
        var birthdayBanner = $("#birthdayBanner");
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
        var monthNames = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

        // Separate upcoming and recent
        var upcoming = birthdayPeople.filter(function (p) {
            return p.birthdayUpcoming;
        });
        var recent = birthdayPeople.filter(function (p) {
            return p.birthdayRecent && !p.birthdayUpcoming;
        });

        // Render all birthday cards in a horizontal layout
        upcoming.forEach(function (person) {
            birthdayList.append(window.CRM.kiosk.renderBirthdayCard(person, monthNames, "upcoming"));
        });
        recent.forEach(function (person) {
            birthdayList.append(window.CRM.kiosk.renderBirthdayCard(person, monthNames, "recent"));
        });

        $("#birthdayCount").text(birthdayPeople.length);
    },

    renderBirthdayCard: function (person, monthNames, cardType) {
        var cardClass = "birthday-card";
        var today = new Date();
        var isToday =
            person.birthdayToday || (person.birthMonth === today.getMonth() + 1 && person.birthDay === today.getDate());

        if (isToday) {
            cardClass += " today";
        } else if (cardType === "upcoming") {
            cardClass += " upcoming";
        } else if (cardType === "recent") {
            cardClass += " recent";
        }

        var card = $("<div>", { class: cardClass });

        // Avatar
        var avatarDiv = $("<div>", { class: "birthday-avatar" });
        if (person.hasPhoto) {
            avatarDiv.append(
                $("<img>", {
                    src: window.CRM.kiosk.getPhotoUrl(person.personId),
                    alt: person.displayName,
                }),
            );
        } else {
            avatarDiv.append($("<i>", { class: "fas fa-birthday-cake" }));
        }
        card.append(avatarDiv);

        // Info
        var infoDiv = $("<div>", { class: "birthday-info" });
        infoDiv.append($("<div>", { class: "birthday-name", text: person.firstName }));

        var dateText = monthNames[person.birthMonth] + " " + person.birthDay;
        var dateClass = "birthday-date";
        if (isToday) {
            dateText = "🎉 Today!";
            dateClass += " today";
        }
        infoDiv.append($("<div>", { class: dateClass, text: dateText }));
        card.append(infoDiv);

        // Age badge (if age is available and it's upcoming/today)
        if (person.age !== null && (cardType === "upcoming" || isToday)) {
            var turningAge = isToday ? person.age : person.age + 1;
            card.append($("<span>", { class: "birthday-age-badge", text: "Turning " + turningAge }));
        }

        return card;
    },

    updateActiveClassMembers: function () {
        window.CRM.kiosk
            .APIRequest({
                path: "activeClassMembers",
            })
            .done(function (data) {
                if (!data || !data.People || data.People.length === 0) {
                    // No members found - show helpful debug info
                    $("#classMemberContainer").html(window.CRM.kiosk.renderNoMembersMessage());
                    return;
                }
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
                var birthdayPeople = [];

                $(data.People).each(function (i, d) {
                    var memberData = {
                        displayName: d.FirstName + " " + d.LastName,
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

                    window.CRM.kiosk.renderClassMember(memberData);

                    // Add to birthday list if upcoming or recent
                    if (d.birthdayUpcoming || d.birthdayRecent) {
                        birthdayPeople.push(memberData);
                    }
                });

                // Render birthday section
                window.CRM.kiosk.renderBirthdaySection(birthdayPeople);

                // Update counts after rendering
                window.CRM.kiosk.updateMemberCounts();
            })
            .fail(function (xhr, status, error) {
                // API error - show debug info
                var errorMessage = "Unable to load class members";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.status === 500) {
                    errorMessage = "Server error - the event may not be linked to a group";
                }
                $("#classMemberContainer").html(window.CRM.kiosk.renderErrorMessage(errorMessage, xhr.status));
            });
    },

    renderNoMembersMessage: function () {
        return (
            '<div class="kiosk-status-container">' +
            '<div class="card kiosk-status-card card-warning">' +
            '<div class="card-header">' +
            '<h3 class="card-title"><i class="fas fa-users-slash mr-2"></i>No Class Members Found</h3>' +
            "</div>" +
            '<div class="card-body">' +
            '<div class="kiosk-status-icon text-warning">' +
            '<i class="fas fa-users-slash"></i>' +
            "</div>" +
            '<p class="text-muted">This kiosk is assigned to an event, but no members are available for check-in.</p>' +
            '<div class="kiosk-instructions">' +
            '<h5><i class="fas fa-info-circle mr-2"></i>Possible Causes</h5>' +
            "<ol>" +
            "<li><strong>Event not linked to a Group:</strong> Edit the event and associate it with a Sunday School or other group</li>" +
            "<li><strong>Group has no members:</strong> Add people to the group that is linked to this event</li>" +
            "<li><strong>Event timing:</strong> The event may not be currently active (check start/end times)</li>" +
            "</ol>" +
            "</div>" +
            '<div class="kiosk-instructions mt-3">' +
            '<h5><i class="fas fa-wrench mr-2"></i>How to Fix</h5>' +
            '<ol class="small">' +
            "<li>Go to <strong>Events</strong> in ChurchCRM</li>" +
            "<li>Edit this event and set the <strong>Group</strong> field</li>" +
            "<li>Ensure the group has members assigned</li>" +
            "<li>Refresh this kiosk page</li>" +
            "</ol>" +
            "</div>" +
            "</div>" +
            "</div>" +
            "</div>"
        );
    },

    renderErrorMessage: function (message, statusCode) {
        return (
            '<div class="kiosk-status-container">' +
            '<div class="card kiosk-status-card card-danger">' +
            '<div class="card-header">' +
            '<h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i>Error Loading Members</h3>' +
            "</div>" +
            '<div class="card-body">' +
            '<div class="kiosk-status-icon text-danger">' +
            '<i class="fas fa-exclamation-triangle"></i>' +
            "</div>" +
            '<p class="text-muted">' +
            message +
            "</p>" +
            (statusCode ? '<p class="small text-muted">HTTP Status: ' + statusCode + "</p>" : "") +
            '<div class="kiosk-instructions">' +
            '<h5><i class="fas fa-lightbulb mr-2"></i>Troubleshooting</h5>' +
            "<ul>" +
            "<li>Verify the event is linked to a <strong>Group</strong></li>" +
            "<li>Check that the event start/end times are correct</li>" +
            "<li>Ensure the group has members</li>" +
            "<li>Check the server logs for more details</li>" +
            "</ul>" +
            "</div>" +
            "</div>" +
            "</div>" +
            "</div>"
        );
    },

    heartbeat: function () {
        window.CRM.kiosk
            .APIRequest({
                path: "heartbeat",
            })
            .done(function (data) {
                let thisAssignment = JSON.parse(data.Assignment);
                if (window.CRM.kioskAssignmentId === undefined) {
                    window.CRM.kioskAssignmentId = thisAssignment;
                } else if (
                    thisAssignment &&
                    (thisAssignment.EventId !== window.CRM.kioskAssignmentId.EventId ||
                        thisAssignment.Event.GroupId !== window.CRM.kioskAssignmentId.Event.GroupId)
                ) {
                    location.reload();
                }

                if (data.Commands === "Reload") {
                    location.reload();
                }

                if (data.Commands === "Identify") {
                    clearInterval(window.CRM.kioskEventLoop);
                    $("#event").hide();
                    $("#noEvent").show();
                    $("#noEvent").html(
                        window.CRM.kiosk.renderStatusCard(
                            "info",
                            "fa-tablet-alt",
                            "Kiosk Identification",
                            data.Name,
                            null,
                        ),
                    );
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                    return;
                }

                if (data.Accepted) {
                    let Assignment = JSON.parse(data.Assignment);
                    if (Assignment && Assignment.AssignmentType == 1) {
                        var eventStart = moment(Assignment.Event.Start);
                        var eventEnd = moment(Assignment.Event.End);
                        var now = moment();

                        $("#eventTitle").text(Assignment.Event.Title);
                        $("#startTime").text(eventStart.format("MMMM Do YYYY, h:mm:ss a"));
                        $("#endTime").text(eventEnd.format("MMMM Do YYYY, h:mm:ss a"));

                        if (now.isBefore(eventStart)) {
                            // Event hasn't started yet - show countdown
                            $("#noEvent").hide();
                            $("#event").show();
                            $("#classMemberContainer").html(
                                window.CRM.kiosk.renderCountdown(eventStart, Assignment.Event.Title),
                            );
                            window.CRM.kiosk.startCountdown(eventStart);
                        } else if (now.isAfter(eventEnd)) {
                            // Event has ended
                            $("#noEvent").hide();
                            $("#event").show();
                            $("#classMemberContainer").html(window.CRM.kiosk.renderEventEnded(Assignment.Event.Title));
                        } else {
                            // Event is active - show class members
                            window.CRM.kiosk.updateActiveClassMembers();
                            $("#noEvent").hide();
                            $("#event").show();
                        }
                    } else {
                        $("#noEvent").show();
                        $("#noEvent").html(
                            window.CRM.kiosk.renderStatusCard(
                                "success",
                                "fa-check-circle",
                                "Kiosk Ready",
                                data.Name,
                                "<p class='text-muted mb-0'>This kiosk is accepted but has no active event assignment.</p>" +
                                    "<div class='kiosk-instructions'>" +
                                    "<h5><i class='fas fa-info-circle'></i> Next Steps</h5>" +
                                    "<ol>" +
                                    "<li>Go to <strong>Kiosk Manager</strong> in the admin menu</li>" +
                                    "<li>Find this kiosk in the device list</li>" +
                                    "<li>Use the <strong>Assign</strong> dropdown to select an event</li>" +
                                    "</ol></div>",
                            ),
                        );
                        $("#event").hide();
                    }
                } else {
                    $("#noEvent").show();
                    $("#noEvent").html(
                        window.CRM.kiosk.renderStatusCard(
                            "pending",
                            "fa-hourglass-half",
                            "Awaiting Acceptance",
                            data.Name,
                            "<p class='text-muted'>This kiosk has registered but needs to be accepted by an administrator before it can be used.</p>" +
                                "<div class='kiosk-instructions'>" +
                                "<h5><i class='fas fa-info-circle'></i> How to Accept This Kiosk</h5>" +
                                "<ol>" +
                                "<li>Log in to ChurchCRM as an administrator</li>" +
                                "<li>Navigate to <strong>Admin → Kiosk Manager</strong></li>" +
                                "<li>Find the kiosk named <strong>" +
                                data.Name +
                                "</strong> in the list</li>" +
                                "<li>Click the <strong><i class='fas fa-check'></i> Accept</strong> button</li>" +
                                "<li>Assign an event to the kiosk using the dropdown</li>" +
                                "</ol>" +
                                "<p class='mb-0'><small>This page will automatically update once the kiosk is accepted.</small></p>" +
                                "</div>",
                        ),
                    );
                    $("#event").hide();
                }
            });
    },

    renderCountdown: function (eventStart, eventTitle) {
        return (
            '<div class="kiosk-status-container">' +
            '<div class="card kiosk-status-card card-primary">' +
            '<div class="card-header">' +
            '<h3 class="card-title"><i class="fas fa-clock mr-2"></i>Check-in Opens Soon</h3>' +
            "</div>" +
            '<div class="card-body text-center">' +
            '<div class="kiosk-status-icon text-primary">' +
            '<i class="fas fa-clock"></i>' +
            "</div>" +
            '<p class="text-muted mb-3">Check-in for <strong>' +
            eventTitle +
            "</strong> will begin at:</p>" +
            '<h4 class="text-primary mb-4">' +
            eventStart.format("h:mm A") +
            "</h4>" +
            '<div class="row justify-content-center mb-4">' +
            '<div class="col-auto text-center px-3">' +
            '<div id="countdown-days" class="kiosk-countdown text-dark">00</div>' +
            '<small class="text-muted">Days</small>' +
            "</div>" +
            '<div class="col-auto text-center px-3">' +
            '<div id="countdown-hours" class="kiosk-countdown text-dark">00</div>' +
            '<small class="text-muted">Hours</small>' +
            "</div>" +
            '<div class="col-auto text-center px-3">' +
            '<div id="countdown-minutes" class="kiosk-countdown text-dark">00</div>' +
            '<small class="text-muted">Minutes</small>' +
            "</div>" +
            '<div class="col-auto text-center px-3">' +
            '<div id="countdown-seconds" class="kiosk-countdown text-primary">00</div>' +
            '<small class="text-muted">Seconds</small>' +
            "</div>" +
            "</div>" +
            '<p class="mb-0 text-muted"><small>This page will automatically refresh when check-in opens.</small></p>' +
            "</div>" +
            "</div>" +
            "</div>"
        );
    },

    renderEventEnded: function (eventTitle) {
        return (
            '<div class="kiosk-status-container">' +
            '<div class="card kiosk-status-card card-success">' +
            '<div class="card-header">' +
            '<h3 class="card-title"><i class="fas fa-calendar-check mr-2"></i>Event Has Ended</h3>' +
            "</div>" +
            '<div class="card-body text-center">' +
            '<div class="kiosk-status-icon text-success">' +
            '<i class="fas fa-calendar-check"></i>' +
            "</div>" +
            '<p class="text-muted mb-0">Check-in for <strong>' +
            eventTitle +
            "</strong> has closed.</p>" +
            '<p class="text-muted">Thank you for attending!</p>' +
            "</div>" +
            "</div>" +
            "</div>"
        );
    },

    startCountdown: function (eventStart) {
        // Clear any existing countdown interval
        if (window.CRM.countdownInterval) {
            clearInterval(window.CRM.countdownInterval);
        }

        window.CRM.countdownInterval = setInterval(function () {
            var now = moment();
            var duration = moment.duration(eventStart.diff(now));

            if (duration.asSeconds() <= 0) {
                // Event has started - reload page to show members
                clearInterval(window.CRM.countdownInterval);
                location.reload();
                return;
            }

            var days = Math.floor(duration.asDays());
            var hours = duration.hours();
            var minutes = duration.minutes();
            var seconds = duration.seconds();

            $("#countdown-days").text(String(days).padStart(2, "0"));
            $("#countdown-hours").text(String(hours).padStart(2, "0"));
            $("#countdown-minutes").text(String(minutes).padStart(2, "0"));
            $("#countdown-seconds").text(String(seconds).padStart(2, "0"));
        }, 1000);
    },

    renderStatusCard: function (statusType, iconClass, title, kioskName, bodyContent) {
        // Use Bootstrap 4.6.2 card-primary pattern consistent with ChurchCRM
        var cardClass = "card-primary";
        var iconColorClass = "text-primary";

        if (statusType === "pending") {
            cardClass = "card-warning";
            iconColorClass = "text-warning";
        } else if (statusType === "success") {
            cardClass = "card-success";
            iconColorClass = "text-success";
        } else if (statusType === "info") {
            cardClass = "card-info";
            iconColorClass = "text-info";
        }

        return (
            '<div class="card kiosk-status-card ' +
            cardClass +
            '">' +
            '<div class="card-header">' +
            '<h3 class="card-title"><i class="fas ' +
            iconClass +
            ' mr-2"></i>' +
            title +
            "</h3>" +
            "</div>" +
            '<div class="card-body text-center">' +
            '<div class="kiosk-status-icon ' +
            iconColorClass +
            '">' +
            '<i class="fas ' +
            iconClass +
            '"></i>' +
            "</div>" +
            '<div class="kiosk-name"><i class="fas fa-tablet-alt mr-2"></i>' +
            kioskName +
            "</div>" +
            (bodyContent ? bodyContent : "") +
            "</div></div>"
        );
    },

    checkInPerson: function (personId) {
        window.CRM.kiosk
            .APIRequest({
                path: "checkin",
                method: "POST",
                data: JSON.stringify({ PersonId: personId }),
            })
            .done(function (data) {
                window.CRM.kiosk.setCheckedIn(personId);
            });
    },

    checkOutPerson: function (personId) {
        window.CRM.kiosk
            .APIRequest({
                path: "checkout",
                method: "POST",
                data: JSON.stringify({ PersonId: personId }),
            })
            .done(function (data) {
                window.CRM.kiosk.setCheckedOut(personId);
            });
    },

    checkOutAll: function () {
        // Disable button during processing
        var $btn = $("#checkoutAllBtn");
        var originalHtml = $btn.html();
        $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Processing...');

        window.CRM.kiosk
            .APIRequest({
                path: "checkoutAll",
                method: "POST",
            })
            .done(function (data) {
                // Move all checked-in people to not checked in
                $("#checkedInList .kiosk-member").each(function () {
                    var personId = $(this).attr("id").replace("personId-", "");
                    window.CRM.kiosk.setCheckedOut(personId);
                });
                $btn.html(originalHtml).prop("disabled", false);
            })
            .fail(function () {
                $btn.html(originalHtml).prop("disabled", false);
            });
    },

    setCheckedOut: function (personId) {
        let $personDiv = $("#personId-" + personId);
        let $personDivButton = $personDiv.find(".checkoutButton");

        // Update button - icon only
        $personDivButton.removeClass("checkoutButton kiosk-btn-checkout");
        $personDivButton.addClass("checkinButton kiosk-btn-checkin");
        $personDivButton.html('<i class="fas fa-sign-in-alt"></i>');
        $personDivButton.attr("title", "Check In");

        // Remove checked-in styling
        $personDiv.removeClass("checked-in");

        // Move to Not Checked In section if not already there
        if ($personDiv.closest("#notCheckedInList").length === 0) {
            $personDiv.detach().appendTo("#notCheckedInList");
            window.CRM.kiosk.updateMemberCounts();
        }
    },

    setCheckedIn: function (personId) {
        let $personDiv = $("#personId-" + personId);
        let $personDivButton = $personDiv.find(".checkinButton");

        // Update button - icon only
        $personDivButton.removeClass("checkinButton kiosk-btn-checkin");
        $personDivButton.addClass("checkoutButton kiosk-btn-checkout");
        $personDivButton.html('<i class="fas fa-sign-out-alt"></i>');
        $personDivButton.attr("title", "Check Out");

        // Add checked-in styling
        $personDiv.addClass("checked-in");

        // Move to Checked In section if not already there
        if ($personDiv.closest("#checkedInList").length === 0) {
            $personDiv.detach().appendTo("#checkedInList");
            window.CRM.kiosk.updateMemberCounts();
        }
    },

    triggerNotification: function (personId) {
        //window.CRM.kiosk.stopEventLoop();
        window.CRM.kiosk
            .APIRequest({
                path: "triggerNotification",
                method: "POST",
                data: JSON.stringify({ PersonId: personId }),
            })
            .done(function (data) {});
    },

    enterFullScreen: function () {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
            document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
            document.documentElement.webkitRequestFullscreen();
        } else if (document.documentElement.msRequestFullscreen) {
            document.documentElement.msRequestFullscreen();
        }
    },

    exitFullScreen: function () {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    },

    displayPersonInfo: function (personId) {},

    startEventLoop: function () {
        window.CRM.kiosk.kioskEventLoop = setInterval(window.CRM.kiosk.heartbeat, 2000);
    },

    stopEventLoop: function () {
        clearInterval(window.CRM.kiosk.kioskEventLoop);
    },
};
