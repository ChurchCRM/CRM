
$('.delete-button').click(function() {
	console.log("delete-button" + this.id);
	var groupKey =  ($(this).attr('id').split(':'))[1];
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