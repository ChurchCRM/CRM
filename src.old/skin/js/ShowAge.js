(function($) {
  $(document).on('ready', function() {
    $('[data-birth-date]').each(function(idx, element) {
      var $element = $(element);
      var birthDate = moment($element.data('birthDate'));
      var now = moment();
      var ageDisplay = now.diff(birthDate, 'years');
      if(ageDisplay < 1) {
        ageDisplay = now.diff(birthDate, 'months');
      }
      if(ageDisplay) {
        $element.text(ageDisplay);
      }
    });
  });
})($);
