var dataT = 0;

  $('#cancel').click(function() {
    document.location = window.CRM.root + "/" + slinkBack;
  });

  $("#addNewContrib").click(function() {
    document.location= window.CRM.root + "/ContributionEditor.php?linkBack=findContributions.php";
  });

  function initTable(url = "/api/contrib") {
    var dataTableConfig = {
      ajax: {
        url: window.CRM.root + url,
        dataSrc: "Contribs"
      },
      initComplete: function() {
        filterFill()
      },
      deferRender: true,
      columns: [
        {
          title:i18next.t( 'Contribution ID'),
          data: 'Id',
          render: function (data, type, full, meta) {
            if (type === 'display') {
              return '<a href=\'ContributionEditor.php?ContributionID=' + full.Id + '&ContributorID=' + full.per_ID + '&linkBack=FindContributions.php\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a>' + full.Id;
            }
            else {
              return parseInt(full.Id);
            }
          },
          type: 'num'
        },
        {
          title:i18next.t('Deposit ID'),
          data: 'DepId',
          render: function (data, type, full, meta) {
            if (type === 'display') {
              return '<a href=\'DepositSlipEditor.php?DepositSlipID=' + full.DepId + '&linkBack=FindContributions.php\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a>' + parseInt(full.DepId);
            }
            else {
              return parseInt(full.DepId);
            }
            
          }
        },
        {
          title:i18next.t( 'First Name'),
          data: 'FirstName',
          searchable: true
          // use the following to combine columns
          // render: function ( data, type, full ) {
          //     if (full['Envelope']!='0') {
          //       return full['LastName'] + ', ' + data + ' (' + full['Envelope'] + ')' ;
          //     } else {
          //       return full['LastName'] + ', ' + data;
          //     }
          // }
        },
        {
          title:i18next.t( 'Last Name'),
          data: 'LastName',
          searchable: true,
          // visible: true
        },
        {
          title:i18next.t( 'Envelope'),
          data: 'Envelope',
          searchable: true,
          render: function(data) {
            if (data !='0' && data != '') {
              return data;
            } else {
              return '';
            }
          }
        },
        {
          title:i18next.t( 'Contribution Date'),
          data: 'Date',
          render: function (data, type, full, meta) {
            if (type === 'display') {
              return moment(data).format("YYYY-MM-DD");
            }
            else {
              return data
            }
          },
          searchable: true
        },
        {
          title:i18next.t( 'Total'),
          data: 'totalAmount',
          searchable: false,
        },
        {
          title:i18next.t( 'Comment'),
          data: 'Comment',
          searchable: true,
  
        },
        {
          title:i18next.t( 'Method'),
          data: 'Method',
          searchable: true,
        },
        {
          title:i18next.t( 'CheckNo'),
          data: 'Checkno',
          searchable: true,
        },
        {
          title:i18next.t( 'PerId'),
          data: 'ConId',
          searchable: true,
          visible: false,
        }
      ],
      order: [0, 'desc']
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#contribTable").DataTable(dataTableConfig);
  }
  
  function filterFill() {
    console.log('filterFill');
    dataT.columns(5).data().eq(0).unique().sort().each( function ( d, j ) {
        $('.filter-Date').append('<option>'+moment(d).format('YYYY-MM-DD')+'</option>');
    });

    dataT.columns(7).data().eq(0).unique().sort().each( function ( d, j ) {
        $('.filter-Comment').append('<option>'+d+'</option>');
    });
  }

  function initButtons() {
  
    $("#contribButton").show();

    $("#contribTable tbody").on('click', 'tr', function () {
      $(this).toggleClass('selected');
      var selectedRows = dataT.rows('.selected').data().length;
      $("#deleteSelectedRows").prop('disabled', !(selectedRows));
      $("#deleteSelectedRows").text("Delete (" + selectedRows + ") Selected Rows");
      // $("#exportSelectedRows").prop('disabled', !(selectedRows));
      // $("#exportSelectedRows").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (OFX)");
      // $("#exportSelectedRowsCSV").prop('disabled', !(selectedRows));
      // $("#exportSelectedRowsCSV").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (CSV)");
      // $("#generateDepositSlip").prop('disabled', !(selectedRows));
      // $("#generateDepositSlip").html("<i class=\"fa fa-download\"></i> Generate Deposit Split for Selected (" + selectedRows + ") Rows (PDF)");
    });
  
    $('.exportButton').click(function (sender) {
      var selectedRows = dataT.rows('.selected').data()
      var type = this.getAttribute("data-exportType");
      $.each(selectedRows, function (index, value) {
        window.CRM.VerifyThenLoadAPIContent(window.CRM.root + '/api/contrib/' + value.Id + '/' + type);
      });
    });
  }

  function initAddToDeposit() { // search filtered records
    $("#depositButton").show();

    $("#contribTable tbody").on('click', 'tr', function () {
      $(this).toggleClass('selected');
      var selectedRows = dataT.rows('.selected').data().length;
      $("#AddSelectedToDeposit").prop('disabled', !(selectedRows));
      $("#AddSelectedToDeposit").text("Add (" + selectedRows + ") Selected Rows");
    });

    $("#AddToDeposit").click(function() {
      // add all visible records in table to deposit
      var addRows = dataT.rows( {order:'index', search:'applied'} ).column(0).data();
      $.each(addRows, function (index, value) {
        AddToDeposit(parseInt(value));
      });
    });

  $("#AddSelectedToDeposit").click(function() {
    // add all visible records in table to deposit
    var addRows = dataT.rows('.selected').data();
    $.each(addRows, function (index, value) {
      AddToDeposit(parseInt(value.Id));
    });
  });
}

  function AddToDeposit(iConId) {
    // return false;
    var postData = {
      DepId: iDepositSlipID,
    };
      
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/contrib/" + iConId + "/deposit",
      data: JSON.stringify(postData),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      // success: function (data) {
      // }
    }).done(function(data) {
        // redirect to deposit page
        document.location = window.CRM.root + "/" + slinkBack;
 
    });
  }
