$.widget( "custom.catcomplete", $.ui.autocomplete, {
    _create: function() {
        this._super();
        this.widget().menu( "option", "items", "> :not(.ui-autocomplete-category)" );
    },
    _renderMenu: function( ul, items ) {
        var that = this;
        $.each(items, function( index, item ) {
            var li;
            ul.append( "<li class='ui-autocomplete-category'>" + Object.keys(item)[0]+ "</li>" );
            $.each(item[Object.keys(item)[0]], function (subindex,subitem) { 
                li = that._renderItemData( ul, {label: subitem.displayName,value: subitem.uri} );
            });
        });
    }
});


$("document").ready(function(){
    $(".multiSearch").catcomplete({
        source: function (request, response) {
            $.ajax({
                url: 'api/search/'+request.term,
                dataType: 'json',
                type: 'GET',
                success: function (data) {
                    response(data);
                }
            })
        },
        minLength: 2,
        select: function (event, ui) {
            console.log("selected");
            var location = ui.item.value;
            window.location.replace(location);
            return false;
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