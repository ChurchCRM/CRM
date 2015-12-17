
function formatCurrency(total) {
    var neg = false;
    if(total < 0) {
        neg = true;
        total = Math.abs(total);
    }
    return parseFloat(total, 10).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString();
}

$('#FundSplit').on('change',function() {
if(this.value == 0){
$('#FundSelection').show();
$('#SingleComment').hide();
}
else
{
$('#FundSelection').hide();
$('#SingleComment').show();
}
});

$('#PaymentByMethod').on('change',function() {
if(this.value == "CASH"){
$('#CashEnter').show();
$('#CheckEnter').hide();
$('#grandTotal').prop("disabled",true)
}
else if(this.value == "CHECK")
{
$('#CashEnter').hide();
$('#CheckEnter').show();
$('#grandTotal').prop("disabled",false)
}
else{
$('#CashEnter').hide();
$('#CheckEnter').hide();
}
});

$(".denominationInputBox").on('change',function(){
	var grandtotal=0;
	$(".denominationInputBox").each(function(i,el){
	var currencyvalue=$(el).attr("data-cur-value");
	var currencycount=$(el).val();
	console.log("("+currencycount+"): "+currencyvalue);
	grandtotal+= currencyvalue*currencycount;
	});
	$('#grandTotal').val(formatCurrency(grandtotal));
});

$(document).ready(function() {

$("#FamilyName").autocomplete({
		source: "AjaxFunctions.php?searchtype=famlist_s",
		minLength: 3,
		select: function(event,ui) {
			$('[name=FamilyName]').val(ui.item.value);
			$('[name=FamilyID]:eq(1)').val(ui.item.id);
		}
	});


$('#MatchEnvelope').click(function() {
	console.log("matchenvelopecliked");
   $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : 'ajax/SearchEnvelope.php?searchtype=envelope&envelopeID='+$('input[name=Envelope]').val(), // the url where we want to POST
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
			$('[name=FamilyName]').val(data[0].Name);
			$('[name=FamilyID]:eq(1)').val(data[0].fam_ID);
		});
		
});

$('#MatchFamily').click(function() {
   $.ajax({
            type        : 'GET', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/members/list/byCheckNumber/'+$('textarea[name=ScanInput]').val(), // the url where we want to POST
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
			$('[name=FamilyName]').val(data.fam_Name);
			$('[name=CheckNo]').val(data.CheckNumber);
		});
});

$('#SetDefaultCheck').click(function() {
  alert( "Handler for find SetDefaultCheck clicked" );
});

function getFundSubmitData(){


}

function getDenominationSubmitData(){
	var denominations=new Array();
	$(".denominationInputBox").each(function(i,el){
	var currencyObject = {currencyID: $(el).attr("Name"), Count: $(el).val()}
	console.log(currencyObject);
	denominations.push(currencyObject);
	});
	return denominations;	
}


function getSubmitFormData(){
	var fd = {
				'FamilyID'			: $('[name=FamilyID]:eq(1)').val(),
				'Date'				:$('input[name=Date]').val(),
				'FundSplit'			:$('select[name=FundSplit]').val(),
				'FYID'				 : $('select[name=FYID]').val(),
				'Envelope'             : $('input[name=Envelope]').val(),
				'paymentby'    : $('select[name=Method]').val(),
				
				'comment'			:$('input[name=OneComment]').val(),
				
				
				'total'				:$('input[name=Total]').val(),
				'cashDenominations'	:getDenominationSubmitData()
				
			};
			
	return fd;
}


$('#PledgeForm').submit(function(event) {
		console.log("submit pressed");
        // get the form data
        // there are many ways to get this data using jQuery (you can use the class or id also)
        var formData = getSubmitFormData();
		
		console.log(formData);

       //process the form
       $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : 'api/payments', // the url where we want to POST
            data        : formData, // our data object
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
			console.log(data.downloads);
			var obj = jQuery.parseJSON(data.downloads);
			console.log(obj);
			jQuery.each(obj, function() {
					console.log(this);
					console.log(this.name);
					console.log(this.url);
				});
			
		  });
		 
		

        event.preventDefault();
    });

});
