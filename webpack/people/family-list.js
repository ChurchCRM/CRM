import $ from "jquery";

$(document).ready(() => {
  // Handle photo viewing
  $(document).on("click", ".view-family-photo", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var familyId = $(this).data("family-id");
    if (window.CRM && window.CRM.showPhotoLightbox) {
      window.CRM.showPhotoLightbox("family", familyId);
    }
  });
});

export default {};
