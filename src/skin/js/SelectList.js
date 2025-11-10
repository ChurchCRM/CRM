$(document).ready(function () {
    // Wait for locales to load before setting up cart handlers
    // CartManager uses i18next for notifications
    window.CRM.onLocalesReady(function () {
        $("#AddAllToCart").click(function () {
            window.CRM.cartManager.addPerson(listPeople, {
                showNotification: true,
            });
        });

        $("#RemoveAllFromCart").click(function () {
            window.CRM.cartManager.removePerson(listPeople, {
                confirm: true,
                showNotification: true,
            });
        });
    });
});
