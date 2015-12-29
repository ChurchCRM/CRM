$('.delete-button').click(function() {
	console.log("delete-button" + this.id);
	var groupKey =  ($(this).attr('id').split(':'))[1];
	console.log("Setting Data GroupKey for #deleteConfirmed to: " + groupKey);
	$("#deleteConfirmed").data("GroupKey",groupKey);
		
});


$('#deleteConfirmed').click(function() {
	var groupKey =  $('#deleteConfirmed').data("GroupKey");
	console.log("Deleting Payment with Group Key: " + groupKey);
   $.ajax({
            type        : 'DELETE', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/payments/'+groupKey, // the url where we want to POST
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
			
		});
		
});

