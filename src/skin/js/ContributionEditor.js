function initPaymentTable()
{
  
 var dataTableConfig = {
    ajax:{
      url :window.CRM.root+"/api/split/" + iContributionID + "/splits",
      dataSrc:'ContribSplits'
    },
    "deferRender": true,
    columns: [
      {
        width: 'auto',
        title:i18next.t('ConID'),
        data:'Id',
      },
      {
        width: 'auto',
        title:i18next.t('Split ID'),
        data:'ConId'
      },
      {
        width: 'auto',
        title:i18next.t('Fund'),
        data:'fun_Name',
        // type: 'input-select'
      },
      {
        width: 'auto',
        title:i18next.t('Amount'),
        data:'Amount'
      },
      {
        width: 'auto',
        title:i18next.t('Comment'),
        data:'Comment'
      },
      {
        width: 'auto',
        title:i18next.t('Non-Deductible'),
        data:'Nondeductible',
        className: 'dt-body-center',
        // type: "checkbox",
        orderable: false,
  
        render: function ( data, type, row ) {
        if ( type === 'display' ) {
          if (data == '1') {
            return '<input type="checkbox" disabled checked>';
          } else {
            return '<input type="checkbox" disabled>';
          }
        }
        return data;
        }
      },
      ],
    //   footerCallback: function ( row, data, start, end, display ) {
    //     var api = this.api(), data;

    //     // Remove the formatting to get integer data for summation
    //     var intVal = function ( i ) {
    //         return typeof i === 'string' ?
    //             i.replace(/[\$,]/g, '')*1 :
    //             typeof i === 'number' ?
    //                 i : 0;
    //     };

    //     // Total over all pages
    //     var total = api
    //         .column( 3 )
    //         .data()
    //         .reduce( function (a, b) {
    //             return intVal(a) + intVal(b);
    //         }, 0 );

    //     // Total over this page
    //     var pageTotal = api
    //         .column( 3, { page: 'current'} )
    //         .data()
    //         .reduce( function (a, b) {
    //             return intVal(a) + intVal(b);
    //         }, 0 );

    //     // Update footer
    //     $( api.column( 3 ).footer() ).html(
    //         '$'+pageTotal +' ( $'+ total +' total)'
    //     );
    // }
      // order: [0, 'asc']
    }
 



    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#splitTable").DataTable(dataTableConfig);
    dataT.on( 'xhr', function () {
    //    var json = dataT.ajax.json();
    //    console.log( json );
    } );
}

  // get contribution data; table contrib_con
  function initContribution() {
    $.ajax({
        type: "GET",
        url: window.CRM.root + '/api/contrib/'+iContributionID+'/contribution',
        data: "",
        
        // success: function (data) {


        // }
      }).done( function(data) {
        var contrib = JSON.parse(data)
          
        // console.log(contrib);
        $("#contribDate").datepicker({format: window.CRM.datePickerformat, language: window.CRM.lang}).datepicker("setDate", new Date(contrib.Date));
        $("[name=ContributorID]").val(contrib.ConId);
        $("[name=TotalAmount]").val(contrib.totalAmount);
        $('[name=contribComment').val(contrib.Comment);
      }
      );
  }
  // get contributor data from the contribution
  function initContributor() {
    $.ajax({
        type: "GET",
        url: window.CRM.root + '/api/person/'+iContributorID+'/search2',
        data: "Person",

        // success: function (data) {

        // }
      }).done( function(data) {
        var person = JSON.parse(data).Person
         
        // console.log(person);
          $("[name=TypeOfMbr]").val(person[0].TypeOfMbr);
          $('[name=ContributorName]').html("<option selected>" + person[0].displayName + "</option>");
          
          if (person[0].envelope !='0' && person[0].envelope != '') {
            $('[name=Envelope]').val(person[0].envelope);
          } else {
            $('[name=Envelope]').val('');
          }
      }
      );
  }

  function initFundList() {
    $.ajax({
      type: "GET",
      url: window.CRM.root + '/api/activefunds',
      data: "DonationFunds",

      // success: function (data) {

      // }
    }).done(function(data){
      var FundList = JSON.parse(data).DonationFunds
      
      var o = new Option("", 0);
          $(o).html("");
          $("#AddFund").append(o);
      $.each(FundList, function(key, object) {
        
          // console.log(object.Id);
          var o = new Option(object.Name, object.Id);
          $(o).html(object.Name);
          $("#AddFund").append(o);
        });
    });
  }
 