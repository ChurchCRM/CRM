$(document).ready(function () {
    $("#AddAllToCart").click(function () {
        window.CRM.cart.addPerson(listPeople);
    });

    $("#RemoveAllFromCart").click(function () {
        window.CRM.cart.removePerson(listPeople);
    });
});
