// Common photo viewer handler for cart pages
// Used by CartToFamily.php and event/cart-to-event

$(document).on("click", ".view-person-photo", function (e) {
  var personId = $(e.currentTarget).data("person-id");
  window.CRM.showPhotoLightbox("person", personId);
  e.preventDefault();
  e.stopPropagation();
});
