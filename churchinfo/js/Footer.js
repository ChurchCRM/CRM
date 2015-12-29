$.widget( "custom.catcomplete", $.ui.autocomplete, {
    _create: function() {
      this._super();
      this.widget().menu( "option", "items", "> :not(.ui-autocomplete-category)" );
    },
    _renderMenu: function( ul, items ) {
		var that = this;
		$.each(items, function( index, item ) {
			console.log(JSON.stringify(item));
			var li;
			ul.append( "<li class='ui-autocomplete-category'>" + Object.keys(item)[0]+ "</li>" );
			
			$.each(item[Object.keys(item)[0]], function (subindex,subitem) { 
				console.log(subitem);
				li = that._renderItemData( ul, {label: subitem.displayName,value: subitem.displayName} );
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
					console.log(data);
					response(data);
				}
			})
		},
		minLength: 2
	});
});