$("document").ready(function(){

	$("#SearchText").autocomplete({
		source: "AjaxFunctions.php?searchtype=person",
		minLength: 2,
		select: function(event, ui) {
			var location = 'PersonView.php?PersonID='+ui.item.id;
			window.location.replace(location);
			$('#add_per_ID').val(ui.item.id);
		}
	});
	
});

