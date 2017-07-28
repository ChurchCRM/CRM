$(document).on("click",".AddToPeopleCart", function(){
  clickedButton = $(this);
  window.CRM.cart.addPerson([clickedButton.data("persionid")],function()
  {
    $('span i:nth-child(2)',clickedButton).addClass("fa-remove RemoveFromPeopleCart");
    $('span i:nth-child(2)',clickedButton).removeClass("fa-cart-plus AddToPeopleCart");
  });
})

$(document).on("click",".RemoveFromPeopleCart", function(){
  clickedButton = $(this);
  window.CRM.cart.removePerson([clickedButton.data("persionid")],function()
  {
    $('span i:nth-child(2)',clickedButton).removeClass("fa-remove RemoveFromPeopleCart");
    $('span i:nth-child(2)',clickedButton).addClass("fa-cart-plus AddToPeopleCart");
  });
})

