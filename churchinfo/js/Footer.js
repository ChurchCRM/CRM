$("document").ready(function(){

    $(".multiSearch").select2({
        ajax: {
            url: function (params){
                    return "api/search/"+params.term;   
            },
            dataType: 'json',
            delay: 250,
            data: "",
            processResults: function (data, params) {
                var idKey = 1;
                var results = new Array();   
                $.each(data, function (key,value) {
                    var groupName = Object.keys(value)[0];
                    var ckeys = value[groupName];
                    var resultGroup = {
                        id: key,
                        text: groupName,
                        children:[]
                    };
                    idKey++;
                    var children = new Array();
                    $.each(ckeys, function (ckey,cvalue) {
                        var childObject = {
                            id: idKey,
                            text: cvalue.displayName,     
                            uri: cvalue.uri
                        };
                        idKey++;
                        resultGroup.children.push(childObject);
                    });
                    results.push(resultGroup);
                });
                return {results: results}; 
            },
            cache: true
        }
    });
    $(".multiSearch").on("select2:select",function (e) { window.location.href= e.params.data.uri;});
     
    $(".searchPerson").autocomplete({
        source: function (request, response) {
            $.ajax({
                url: 'api/persons/search/'+request.term,
                dataType: 'json',
                type: 'GET',
                success: function (data) {
                    response($.map(data.persons, function (item) {
                        return {
                            label: item.displayName,
                            value: item.uri
                        }
                    }));
                }
            })
        },
        select: function (event, ui) {
            var location = ui.item.value;
            window.location.replace(location);
            return false;
        },
        minLength: 2
    });
    
    
    $(".searchFamily").autocomplete({
        source: function (request, response) {
                $.ajax({
                url: 'api/families/search/'+request.term,
                dataType: 'json',
                type: 'GET',
                success: function (data) {
                    response($.map(data.families, function (item) {
                        return {
                            label: item.displayName,
                            value: item.uri
                        }
                    }));
                }
            })
        },
        select: function (event, ui) {
            var location = ui.item.value;
            window.location.replace(location);
            return false;
        },
        minLength: 2
    });
     
     
     
     
     
});