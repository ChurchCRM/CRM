
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
	grandtotal+= currencyvalue*currencycount;
	});
	$('#grandTotal').val(formatCurrency(grandtotal));
});

$(".fundSplitInputBox").on('change',function(){
	var grandtotal=0;
	$(".fundSplitInputBox").each(function(i,el){
		var fundval=$(el).val();
		grandtotal+= fundval*1;
	});
	if (formatCurrency(grandtotal) == formatCurrency($('#grandTotal').val())){
		console.log("split value is OK");
		$('#FundSelection .box-header h4').removeClass('fa fa-exclamation');
		$('#FundSelection .box-header h4').addClass('fa fa-check');
		
		
	}
	else
	{
		$('#FundSelection .box-header h4').removeClass('fa fa-check');
		$('#FundSelection .box-header h4').addClass('fa fa-exclamation');
	}
});





$(document).ready(function() {



$('#MatchEnvelope').click(function() {
	console.log("matchenvelopecliked");
   $.ajax({
            type        : 'GET', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/families/byEnvelopeNumber/'+$('input[name=Envelope]').val(), // the url where we want to POST
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
			$('[name=FamilyName]').val(data.Name);
			$('[name=FamilyID]:eq(1)').val(data.fam_ID);
		});
		
});

$('#MatchFamily').click(function() {
   $.ajax({
            type        : 'GET', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/families/byCheckNumber/'+$('textarea[name=ScanInput]').val(), // the url where we want to POST
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
	
	var funds=new Array();
	if ($('select[name=FundSplit]').val() == "0")
	{
		$(".fundrow").each(function(i,el){
			console.log($(this).attr('id'));
			var fundID = ($(this).attr('id').split('_'))[1];
			console.log(fundID);
			var amount = $('input[name='+fundID+'_Amount]').val();
			var nondedamount=  $('input[name='+fundID+'_NonDeductible]').val();
			var comment=  $('input[name='+fundID+'_Comment]').val();
			var fundobjet ={FundID: fundID, Amount: amount, NonDeductible:nondedamount, Comment: comment};
			funds.push(fundobjet);
		});
	}
	else
	{
		var fundobjet ={FundID: $('select[name=FundSplit]').val(), Comment: $('input[name=OneComment]').val(), Amount: $('input[name=TotalAmount]').val()};
		funds.push(fundobjet);
	}
	return JSON.stringify(funds);	

}

function getDenominationSubmitData(){
	var denominations=new Array();
	$(".denominationInputBox").each(function(i,el){
	var currencyObject = {currencyID: $(el).attr("Name"), Count: $(el).val()}
	denominations.push(currencyObject);
	});
	return JSON.stringify(denominations);	
}


function getSubmitFormData(){
	var fd = {
				'FamilyID'			: $('[name=FamilyID]:eq(1)').val(),
				'Date'				:$('input[name=Date]').val(),
				
				'FYID'				 : $('select[name=FYID]').val(),
				'Envelope'             : $('input[name=Envelope]').val(),
				'iMethod'    : $('select[name=Method]').val(),
				'comment'			:$('input[name=OneComment]').val(),
				'total'				:$('input[name=TotalAmount]').val()};
				if ($('select[name=Method]').val() == "CASH")
				{
					fd['cashDenominations']=getDenominationSubmitData();
				}
				if ($('select[name=Method]').val() == "CHECK")
				{
					fd['iCheckNo']=$('input[name=CheckNo]').val();
				}
				fd['FundSplit']=getFundSubmitData();
				
				
			
			
	return fd;
}


$('#PledgeForm').submit(function(event) {
		event.preventDefault();
		console.log("submit pressed");
        // get the form data
        // there are many ways to get this data using jQuery (you can use the class or id also)
        var formData = getSubmitFormData();
		
		console.log(formData);

       //process the form
       $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : 'api/payments', // the url where we want to POST
            data        :  JSON.stringify(formData), // our data object
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
		  });
		 
		

        
    });

});
