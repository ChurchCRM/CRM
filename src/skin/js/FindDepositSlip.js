var dataT = 0;

$(document).ready(function () {
  $("#depositDate").datepicker({format: 'yyyy-mm-dd', language: window.CRM.lang}).datepicker("setDate", new Date());
  $("#addNewDeposit").click(function (e) {
    var newDeposit = {
      'depositType': $("#depositType option:selected").val(),
      'depositComment': $("#depositComment").val(),
      'depositDate': $("#depositDate").val()
    };

    if(!$("#depositComment").val().trim()){
        bootbox.confirm({
             title: i18next.t('Add New Deposit'),
             message: i18next.t('You are about to add a new deposit without a comment'),
             buttons: {
                cancel: {
                    label: i18next.t('Cancel')
                },
                confirm: {
                    label: i18next.t('Confirm')
                }
            },
             callback: function (result) {
                 if(result == true){
                        addNewDepositRequest(newDeposit);
                 }
             }
        });

    }else{
            addNewDepositRequest(newDeposit);
    }

  });

  function addNewDepositRequest(newDeposit){
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/deposits",
      data: JSON.stringify(newDeposit),
      contentType: "application/json; charset=utf-8",
      dataType: "json"
    }).done(function (data) {
      data.totalAmount = '';
      dataT.row.add(data);
      dataT.rows().invalidate().draw(true);
    });
  };

 var dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/deposits",
      dataSrc: "Deposits"
    },
    "deferRender": true,
    columns: [
      {
        title:i18next.t( 'Deposit ID'),
        data: 'Id',
        render: function (data, type, full, meta) {
          if (type === 'display') {
            return '<a href=\'DepositSlipEditor.php?DepositSlipID=' + full.Id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a>' + full.Id;
          }
          else {
            return parseInt(full.Id);
          }
        },
        type: 'num'
      },
      {
        title:i18next.t( 'Deposit Date'),
        data: 'Date',
        render: function (data, type, full, meta) {
          if (type === 'display') {
            return moment(data).format("MM-DD-YY");
          }
          else {
            return data
          }
        },
        searchable: true
      },
      {
        title:i18next.t( 'Deposit Total'),
        data: 'totalAmount',
        searchable: false,
      },
      {
        title:i18next.t( 'Deposit Comment'),
        data: 'Comment',
        searchable: true
      },
      {
        title:i18next.t( 'Closed'),
        data: 'Closed',
        searchable: true,
        render: function (data, type, full, meta) {
          return data == 1 ? 'Yes' : 'No';
        }
      },
      {
        title:i18next.t( 'Deposit Type'),
        data: 'Type',
        searchable: true
      }
    ],
    order: [0, 'desc']
  }
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  dataT = $("#depositsTable").DataTable(dataTableConfig);

  $("#depositsTable tbody").on('click', 'tr', function () {
    $(this).toggleClass('selected');
    var selectedRows = dataT.rows('.selected').data().length;
    $("#deleteSelectedRows").prop('disabled', !(selectedRows));
    $("#deleteSelectedRows").text("Delete (" + selectedRows + ") Selected Rows");
    $("#exportSelectedRows").prop('disabled', !(selectedRows));
    $("#exportSelectedRows").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (OFX)");
    $("#exportSelectedRowsCSV").prop('disabled', !(selectedRows));
    $("#exportSelectedRowsCSV").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (CSV)");
    $("#generateDepositSlip").prop('disabled', !(selectedRows));
    $("#generateDepositSlip").html("<i class=\"fa fa-download\"></i> Generate Deposit Split for Selected (" + selectedRows + ") Rows (PDF)");
  });

  $('.exportButton').click(function (sender) {
    var selectedRows = dataT.rows('.selected').data()
    var type = this.getAttribute("data-exportType");
    $.each(selectedRows, function (index, value) {
      window.CRM.VerifyThenLoadAPIContent(window.CRM.root + '/api/deposits/' + value.Id + '/' + type);
    });
  });

});
