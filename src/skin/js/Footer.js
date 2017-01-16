$("document").ready(function(){

    $(".multiSearch").select2({
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
    $(".date-picker").datepicker({format:'yyyy-mm-dd', language: window.CRM.lang});
    
    $(".maxUploadSize").text(window.CRM.maxUploadSize);
});

function showGlobalMessage(message, callOutClass) {
    $("#globalMessageText").text(message);
    $("#globalMessageCallOut").addClass("callout-"+callOutClass);
    $("#globalMessage").show("slow");
}
