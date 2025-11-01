i18nextOpt = {
    lng: window.CRM.shortLocale,
    nsSeparator: false,
    keySeparator: false,
    pluralSeparator: false,
    contextSeparator: false,
    fallbackLng: false,
    resources: {},
};

i18nextOpt.resources[window.CRM.shortLocale] = {
    translation: window.CRM.i18keys,
};
i18next.init(i18nextOpt);

$("document").ready(function () {
    $(".multiSearch").select2({
        language: window.CRM.shortLocale,
        minimumInputLength: 2,
        ajax: {
            url: function (params) {
                return window.CRM.root + "/api/search/" + params.term;
            },
            dataType: "json",
            delay: 250,
            data: "",
            processResults: function (data, params) {
                return { results: data };
            },
            cache: true,
            beforeSend: function (jqXHR, settings) {
                jqXHR.url = settings.url;
            },
            error: window.CRM.system.handlejQAJAXError,
        },
    });
    $(".multiSearch").on("select2:select", function (e) {
        window.location.href = e.params.data.uri;
    });

    window.onkeyup = function (e) {
        // listen for "?" keypress for quick access to the select2 search box.
        if (e.shiftKey && e.key === "?") {
            $(".multiSearch").select2("open");
        }
    };

    window.CRM.system.runTimerJobs();

    $(".date-picker").datepicker({
        format: window.CRM.datePickerformat,
        language: window.CRM.lang,
    });

    $(".maxUploadSize").text(window.CRM.maxUploadSize);

    // Note: Cart event handlers are now in cart.js module
    // The CartManager class handles all cart button clicks and notifications

    window.CRM.dashboard.refresh();
    DashboardRefreshTimer = setInterval(
        window.CRM.dashboard.refresh,
        window.CRM.iDashboardServiceIntervalTime * 1000,
    );

    window.CRM.APIRequest({
        path: "system/notification",
    }).done(function (data) {
        data.notifications.forEach(function (item) {
            $.notify(
                {
                    icon: "fa fa-" + item.icon,
                    message: item.title,
                    url: item.url,
                },
                {
                    delay: item.delay,
                    type: item.type,
                    placement: {
                        from: item.placement,
                        align: item.align,
                    },
                },
            );
        });
    });

    // Initialize FAB buttons with localized labels
    initializeFAB();
});

function showGlobalMessage(message, callOutClass) {
    var icon = "exclamation-triangle";
    if (callOutClass === "success") {
        icon = "check";
    }
    $.notify(
        {
            icon: "fa fa-" + icon,
            message: message,
        },
        {
            delay: 5000,
            type: callOutClass,
            placement: {
                from: "top",
                align: "right",
            },
            offset: {
                x: 15,
                y: 60,
            },
        },
    );
}

/**
 * Initialize Floating Action Buttons (FAB)
 * - Sets localized labels
 * - Handles scroll behavior to hide buttons on scroll
 * - Auto-hides after 5 seconds
 */
function initializeFAB() {
    const fabContainer = $("#fab-container");
    const fabPersonLabel = $("#fab-person-label");
    const fabFamilyLabel = $("#fab-family-label");

    // Set localized labels
    fabPersonLabel.text(i18next.t("Add New Person"));
    fabFamilyLabel.text(i18next.t("Add New Family"));

    // Auto-hide FAB after 5 seconds
    setTimeout(function () {
        fabContainer.addClass("hidden");
    }, 5000);

    // Hide FAB on any scroll to prevent blocking content
    $(window).on("scroll", function () {
        const currentScroll = $(this).scrollTop();

        // Hide FAB once user scrolls past 50px
        if (currentScroll > 50) {
            fabContainer.addClass("hidden");
        }
    });
}
