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
                //console.log(data);
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
                    //console.log("Processing Group : "+groupName) ;
                    //console.log("Key: "+key+" Value: "+value);
                    //console.log(ckeys);
                    var children = new Array();
                    $.each(ckeys, function (ckey,cvalue) {
                        var childObject = {
                            id: idKey,
                            text: cvalue.displayName      
                        };
                        idKey++;
                        resultGroup.children.push(childObject);
                    });
                   
                    results.push(resultGroup);
                });
                console.log(results);
                return {results: results}; 
            },
            cache: true
        }
    });
     
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