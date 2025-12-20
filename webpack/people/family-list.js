import $ from 'jquery';

$(document).ready(function() {
  // Initialize DataTable for families list
  if ($.fn.DataTable) {
    $('#families').DataTable(window.CRM.plugin.dataTable);
  }

  // Handle photo viewing
  $(document).on('click', '.view-family-photo', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var familyId = $(this).data('family-id');
    if (window.CRM && window.CRM.showPhotoLightbox) {
      window.CRM.showPhotoLightbox('family', familyId);
    }
  });
});

export default {};
