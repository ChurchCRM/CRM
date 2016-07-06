if (!$.isArray(depositData.deposits))
{
    depositData.deposits=[depositData.deposits];
}
var dataT = 0;

function verifyContent(url) {
  $.ajax({
    type: 'HEAD',
    url: url,
    async: false,
    statusCode: {
      200: function() {
        window.open(url);
      },
      404: function() {
        displayErrorMessage(url, "There was a problem retreiving the export for this object");
      }
    }
  });
}

$(document).ready(function() {
    $("#depositDate").datepicker({format:'yyyy-mm-dd'}).datepicker("setDate", new Date());
    $("#addNewDeposit").click(function (e){
        var newDeposit = {
            'depositType':$("#depositType option:selected").val(),
            'depositComment':$("#depositComment").val(),
            'depositDate':$("#depositDate").val()
        };
        $.ajax({
            method: "POST",
            url:   window.CRM.root+"/api/deposits",
            data:  JSON.stringify(newDeposit)
        }).done(function(data){
            dataT.row.add(data[0]);
            dataT.rows().invalidate().draw(true);
        });
    });
   
    dataT = $("#depositsTable").DataTable({
    data:depositData.deposits,
    columns: [
    {
        width: 'auto',
        title:'Deposit ID',
        data:'dep_ID',
        render: function  (data, type, full, meta ) {
            if (type === 'display')
            {
                return '<a href=\'DepositSlipEditor.php?DepositSlipID='+full.dep_ID+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a>'+full.dep_ID; 
            }
            else
            {
                return parseInt(full.dep_ID);
            }
        },
        type:'num'
    },
    {
        width: 'auto',
        title:'Deposit Date',
        data:'dep_Date',
        searchable: true
    },
    {
        width: 'auto',
        title:'Deposit Total',
        data:'dep_Total',
        searchable: false,
    },
    {
        width: 'auto',
        title:'Deposit Comment',
        data:'dep_Comment',
        searchable: true
    },
    {
        width: 'auto',
        title:'Closed',
        data:'dep_Closed',
        searchable: true,
        render: function (data,type,full,meta) {
            return data == 1 ? 'Yes' : 'No';
        }
    },
    {
        width: 'auto',
        title:'Deposit Type',
        data:'dep_Type',
        searchable: true
    }
    ],
    order:[0,'desc']
    });
    
    $("#depositsTable tbody").on('click', 'tr', function() {
         $(this).toggleClass('selected');
         var selectedRows = dataT.rows('.selected').data().length;
          $("#deleteSelectedRows").prop('disabled', !(selectedRows));
          $("#deleteSelectedRows").text("Delete ("+selectedRows+") Selected Rows");
          $("#exportSelectedRows").prop('disabled', !(selectedRows));
          $("#exportSelectedRows").html("<i class=\"fa fa-download\"></i> Export ("+selectedRows+") Selected Rows (OFX)");
          $("#exportSelectedRowsCSV").prop('disabled', !(selectedRows));
          $("#exportSelectedRowsCSV").html("<i class=\"fa fa-download\"></i> Export ("+selectedRows+") Selected Rows (CSV)");
          $("#generateDepositSlip").prop('disabled', !(selectedRows));
          $("#generateDepositSlip").html("<i class=\"fa fa-download\"></i> Generate Deposit Split for Selected ("+selectedRows+") Rows (PDF)");
    });
     
    $('#deleteSelectedRows').click(function() {
        var deletedRows = dataT.rows('.selected').data()
        $("#deleteNumber").text(deletedRows.length);
        $("#confirmDelete").modal('show');
    });
    
    

    $('.exportButton').click(function(sender) {
        var selectedRows = dataT.rows('.selected').data()
        var type = this .getAttribute("data-exportType");
        $.each(selectedRows, function(index, value){
          verifyContent(window.CRM.root+'/api/deposits/'+value.dep_ID+'/'+type);
           
        });
    });

    $("#deleteConfirmed").click(function() {
        var deletedRows = dataT.rows('.selected').data()
        $.each(deletedRows, function(index, value){
            $.ajax({
                type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
                url         : window.CRM.root+'/api/deposits/'+value.dep_ID, // the url where we want to POST
                dataType    : 'json', // what type of data do we expect back from the server
                encode      : true,
                data        : {"_METHOD":"DELETE"}
            })
            .done(function(data) {
                $('#confirmDelete').modal('hide');
                dataT.rows('.selected').remove().draw(false);
            });
        });
    });
});