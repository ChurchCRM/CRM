function displayErrorMessage(endpoint, message) {
  $(".modal").modal('hide');
  $("#APIError").modal('show');
  $("#APIEndpoint").text(endpoint);
  $("#APIErrorText").text(message);
}

$(document).ajaxError(function(evt, xhr, settings) {
  var CRMResponse = JSON.parse(xhr.responseText).error;
  displayErrorMessage("[" + settings.type + "] " + settings.url, " " + CRMResponse.text);
});