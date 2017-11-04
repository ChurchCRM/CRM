i18nextOpt = {
  lng:window.CRM.shortLocale,
  nsSeparator: false,
  keySeparator: false,
  pluralSeparator:false,
  contextSeparator:false,
  fallbackLng: false,
  resources: { }
};

i18nextOpt.resources[window.CRM.shortLocale] = {
  translation: window.CRM.i18keys
};
i18next.init(i18nextOpt);

$("document").ready(function(){
    $(".multiSearch").select2({
        language: window.CRM.shortLocale,
        minimumInputLength: 2,
        ajax: {
            url: function (params){
              return window.CRM.root + "/api/search/" + params.term;
            },
            dataType: 'json',
            delay: 250,
            data: "",
            processResults: function (data, params) {
              return {results: data};
            },
            cache: true
        }
    });
    $(".multiSearch").on("select2:select",function (e) { window.location.href= e.params.data.uri;});

    $.ajax({
      url: window.CRM.root + "/api/timerjobs/run",
      type: "POST"
    });
    $(".date-picker").datepicker({format:window.CRM.datePickerformat, language: window.CRM.lang});
    

    $(".initials-image").initial();
    $(".maxUploadSize").text(window.CRM.maxUploadSize);


    $("#emptyCart").click(function (e) {
            $.ajax({
                method: "DELETE",
                url: window.CRM.root + "/api/cart/",
                contentType: "application/json; charset=utf-8",
                dataType: "json"
            }).done(function (data) {
                $('#iconCount').text('0');
            });

    });

});

function showGlobalMessage(message, callOutClass) {
    $("#globalMessageText").text(message);
    $("#globalMessageCallOut").addClass("callout-"+callOutClass);
    $("#globalMessage").show("slow");
}
