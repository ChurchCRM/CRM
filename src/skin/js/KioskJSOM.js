window.CRM.kiosk = {
    APIRequest: function (options) {
        if (!options.method) {
            options.method = "GET";
        }
        options.url = window.CRM.root + "/kiosk/" + options.path;
        options.dataType = "json";
        options.contentType = "application/json";
        return $.ajax(options);
    },

    renderClassMember: function (classMember) {
        existingDiv = $("#personId-" + classMember.personId);
        if (existingDiv.length > 0) {
        } else {
            var outerDiv = $("<div>", {
                id: "personId-" + classMember.personId,
            }).addClass("col-sm-3");
            var innerDiv = $("<div>").addClass(
                "card card-widget widget-user-2",
            );
            var userHeaderDiv = $("<div>", {
                class: "widget-user-header bg-yellow",
            }).attr("data-personid", classMember.personId);
            var imageDiv = $("<div>", { class: "widget-user-image" }).append(
                $("<img>", {
                    class: "initials-image profile-user-img img-responsive img-circle no-border",
                })
                    .data("name", classMember.displayName)
                    .data(
                        "src",
                        window.CRM.root +
                            "/kiosk/activeClassMember/" +
                            classMember.personId +
                            "/photo",
                    ),
            );
            userHeaderDiv.append(imageDiv);
            userHeaderDiv
                .append(
                    $("<h3>", {
                        class: "widget-user-username",
                        text: classMember.displayName,
                    }),
                )
                .append(
                    $("<h3>", {
                        class: "widget-user-desc",
                        style: "clear:both",
                        text: classMember.classRole,
                    }),
                );
            innerDiv.append(userHeaderDiv);
            innerDiv.append(
                $("<div>", { class: "box-footer no-padding" }).append(
                    $("<ul>", { class: "nav navbar-nav", style: "width:100%" })
                        .append(
                            $("<li>", { style: "width:50%" }).append(
                                $("<button>", {
                                    class: "btn btn-danger parentAlertButton",
                                    style: "width:100%",
                                    text: "Trigger Parent Alert",
                                    "data-personid": classMember.personId,
                                }).prepend(
                                    $("<i>", {
                                        class: "fa fa-exclamation-triangle",
                                        "aria-hidden": "true",
                                    }),
                                ),
                            ),
                        )
                        .append(
                            $("<li>", {
                                class: "btn btn-primary checkinButton",
                                style: "width:50%",
                                text: "Checkin",
                                "data-personid": classMember.personId,
                            }),
                        ),
                ),
            );
            outerDiv.append(innerDiv);
            $("#classMemberContainer").append(outerDiv);
        }

        if (classMember.status == 1) {
            window.CRM.kiosk.setCheckedIn(classMember.personId);
        } else {
            window.CRM.kiosk.setCheckedOut(classMember.personId);
        }
    },

    updateActiveClassMembers: function () {
        window.CRM.kiosk
            .APIRequest({
                path: "activeClassMembers",
            })
            .done(function (data) {
                $(data.People).each(function (i, d) {
                    window.CRM.kiosk.renderClassMember({
                        displayName: d.FirstName + " " + d.LastName,
                        classRole: d.RoleName,
                        personId: d.Id,
                        status: d.status,
                    });
                });
            });
    },

    heartbeat: function () {
        window.CRM.kiosk
            .APIRequest({
                path: "heartbeat",
            })
            .done(function (data) {
                thisAssignment = JSON.parse(data.Assignment);
                if (window.CRM.kioskAssignmentId === undefined) {
                    window.CRM.kioskAssignmentId = thisAssignment;
                } else if (
                    thisAssignment &&
                    (thisAssignment.EventId !==
                        window.CRM.kioskAssignmentId.EventId ||
                        thisAssignment.Event.GroupId !==
                            window.CRM.kioskAssignmentId.Event.GroupId)
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
                    $("#noEvent").html("Kiosk Name: " + data.Name);
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                    return;
                }

                if (data.Accepted) {
                    Assignment = JSON.parse(data.Assignment);
                    if (Assignment && Assignment.AssignmentType == 1) {
                        window.CRM.kiosk.updateActiveClassMembers();
                        $("#noEvent").hide();
                        $("#event").show();
                        $("#eventTitle").text(Assignment.Event.Title);
                        $("#startTime").text(
                            moment(Assignment.Event.Start).format(
                                "MMMM Do YYYY, h:mm:ss a",
                            ),
                        );
                        $("#endTime").text(
                            moment(Assignment.Event.End).format(
                                "MMMM Do YYYY, h:mm:ss a",
                            ),
                        );
                    } else {
                        $("#noEvent").show();
                        $("#noEvent").text(
                            "No active assignments for this kiosk",
                        );
                        $("#event").hide();
                    }
                } else {
                    $("#noEvent").show();
                    $("#noEvent").html(
                        "This kiosk has not been accepted.<br/>Kiosk Name: " +
                            data.Name,
                    );
                    $("#event").hide();
                }
            });
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

    setCheckedOut: function (personId) {
        $personDiv = $("#personId-" + personId);
        $personDivButton = $("#personId-" + personId + " .checkoutButton");
        $personDivButton.addClass("checkinButton");
        $personDivButton.removeClass("checkoutButton");
        $personDivButton.text("Checkin");
        $personDiv.find(".widget-user-header").addClass("bg-yellow");
        $personDiv.find(".widget-user-header").removeClass("bg-green");
    },

    setCheckedIn: function (personId) {
        $personDiv = $("#personId-" + personId);

        $personDivButton = $("#personId-" + personId + " .checkinButton");
        $personDivButton.removeClass("checkinButton");
        $personDivButton.addClass("checkoutButton");
        $personDivButton.text("Checkout");

        $personDiv.find(".widget-user-header").removeClass("bg-yellow");
        $personDiv.find(".widget-user-header").addClass("bg-green");
    },

    triggerNotification: function (personId) {
        //window.CRM.kiosk.stopEventLoop();
        window.CRM.kiosk
            .APIRequest({
                path: "triggerNotification",
                method: "POST",
                data: JSON.stringify({ PersonId: personId }),
            })
            .done(function (data) {
                //window.CRM.kiosk.startEventLoop();
                //TODO:  Signal to the kiosk user that the notification was sent
            });
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

    displayPersonInfo: function (personId) {
        //TODO: Display information (allergies, etc) about the person selected.
    },

    startEventLoop: function () {
        window.CRM.kiosk.kioskEventLoop = setInterval(
            window.CRM.kiosk.heartbeat,
            2000,
        );
    },

    stopEventLoop: function () {
        clearInterval(window.CRM.kiosk.kioskEventLoop);
    },
};
