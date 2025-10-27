$(document).ready(function () {
    $("#AddAllToCart").click(function () {
        window.CRM.cartManager.addPerson(listPeople, {
            showNotification: true
        });
    });

    $("#RemoveAllFromCart").click(function () {
        window.CRM.cartManager.removePerson(listPeople, {
            confirm: true,
            showNotification: true
        });
    });
});
