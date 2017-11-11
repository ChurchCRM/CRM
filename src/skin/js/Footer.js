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

    window.CRM.system.runTimerJobs();
       
    $(".date-picker").datepicker({format:window.CRM.datePickerformat, language: window.CRM.lang});

  
    $(".initials-image").initial();
    $(".maxUploadSize").text(window.CRM.maxUploadSize);
  
    $(document).on("click", ".emptyCart", function (e) {
      window.CRM.cart.empty();
    });
    
    $(document).on("click", "#emptyCartToGroup", function (e) {
      window.CRM.cart.emptyToGroup();
    });
    
    $(document).on("click",".RemoveFromPeopleCart", function(){
      clickedButton = $(this);
      window.CRM.cart.removePerson([clickedButton.data("personid")],function()
      {
        $(clickedButton).addClass("AddToPeopleCart");
        $(clickedButton).removeClass("RemoveFromPeopleCart");
        $('span i:nth-child(2)',clickedButton).removeClass("fa-remove");
        $('span i:nth-child(2)',clickedButton).addClass("fa-cart-plus");
      });
    });
    
    $(document).on("click",".AddToPeopleCart", function(){
      clickedButton = $(this);
      window.CRM.cart.addPerson([clickedButton.data("personid")],function()
      {
        $(clickedButton).addClass("RemoveFromPeopleCart");
        $(clickedButton).removeClass("AddToPeopleCart");
        $('span i:nth-child(2)',clickedButton).addClass("fa-remove ");
        $('span i:nth-child(2)',clickedButton).removeClass("fa-cart-plus ");
      });
    });
    
    window.CRM.cart.refresh();
});

function showGlobalMessage(message, callOutClass) {
    $("#globalMessageText").text(message);
    $("#globalMessageCallOut").addClass("callout-"+callOutClass);
    $("#globalMessage").show("slow");
}
