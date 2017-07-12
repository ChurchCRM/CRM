<?php
	//
	// © Philippe Logel 21 juillet 2016
	// Include de la config d’accès au sgbd + dbb
	//

	require "../../Include/Config.php";
	require "../../Include/Functions.php";
	//require "../../ChurchCRM/dto/SystemURLs.php";
	
	use ChurchCRM\dto\SystemConfig;
	use ChurchCRM\dto\SystemURLs;
	
	//echo "alert('".$localeInfo->getLocale()."');";
	
	setlocale(LC_ALL, $localeInfo->getLocale());
?>
$("document").ready(function(){
  
    i18next
     .use(i18nextXHRBackend)
     .init(
     {
        lng:window.CRM.locale,
        nsSeparator: false,
        keySeparator: false,
        pluralSeparator:false,
        contextSeparator:false,
        fallbackLng: false,
        backend: {
          loadPath: window.CRM.root + '/locale/'+window.CRM.locale+'/LC_MESSAGES/messages.js'
        }
     });

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
              var idKey = 1;
              var results = new Array();
              $.each(data.results, function(key, resultGroupJSONText)
              {
                var rawResultGroup = JSON.parse(resultGroupJSONText);
                var groupName = Object.keys(rawResultGroup)[0];
                var ckeys = rawResultGroup[groupName];
                var resultGroup = {
                  id: key,
                  text: groupName,
                  children: []
                };
                idKey++;
                $.each(ckeys, function(ckey, cvalue)
                {
                  var childObject = {
                    id: idKey,
                    text: cvalue.displayName,
                    uri: cvalue.uri
                  };
                  idKey++;
                  resultGroup.children.push(childObject);
                });
                if(resultGroup.children.length > 0)
                  results.push(resultGroup);
              });
              return {results: results};
            },
            cache: true
        }
    });
    $(".multiSearch").on("select2:select",function (e) { window.location.href= e.params.data.uri;});

    $.ajax({
      url: window.CRM.root + "/api/timerjobs/run",
      type: "POST"
    });
    $(".date-picker").datepicker({format:'<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>', language: window.CRM.lang});
    

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
