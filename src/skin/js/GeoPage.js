$(document).ready(function () {
    // Wait for locales to load before setting up cart handlers
    // CartManager uses i18next for notifications
    window.CRM.onLocalesReady(function () {
        $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
            var target = $(e.target).attr("href"); // activated tab
            $(target + " .choiceSelectBox").select2({ width: "resolve" });
        });

        $(".choiceSelectBox").select2({ width: "resolve" });

        $("#AddAllToCart").click(function () {
            // Use CartManager with notifications
            window.CRM.cartManager.addPerson(listPeople, {
                showNotification: true,
            });
        });

        $("#RemoveAllFromCart").click(function () {
            // Use CartManager with confirmation and notifications
            window.CRM.cartManager.removePerson(listPeople, {
                confirm: true,
                showNotification: true,
            });
        });
    });
});
