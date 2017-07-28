$(document).on("click",".AddToPeopleCart", function(){
  clickedButton = $(this);
  window.CRM.cart.addPerson([clickedButton.data("persionid")],function()
  {
    $('span i:nth-child(2)',clickedButton).addClass("fa-remove RemoveFromPeopleCart");
    $('span i:nth-child(2)',clickedButton).removeClass("fa-cart-plus AddToPeopleCart");
  });
})
