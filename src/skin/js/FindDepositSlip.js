var dataT = 0;

$(document).ready(function () {
  $("#depositDate").datepicker({format: 'yyyy-mm-dd', language: window.CRM.lang}).datepicker("setDate", new Date());
  $("#addNewDeposit").click(function (e) {
    var newDeposit = {
      'depositType': $("#depositType option:selected").val(),
      'depositComment': $("#depositComment").val(),
      'depositDate': $("#depositDate").val()
    };
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
  });

  dataT = $("#depositsTable").DataTable({
    "language": {
      "url": window.CRM.root + "/skin/locale/dataTables/" + window.CRM.locale + ".json"
    },
    ajax: {
      url: window.CRM.root + "/api/deposits",
      dataSrc: "Deposits"
    },
    responsive: true,
    "deferRender": true,
    columns: [
      {
        title: 'Deposit ID',
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
        title: 'Deposit Date',
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
        title: 'Deposit Total',
        data: 'totalAmount',
        searchable: false,
      },
      {
        title: 'Deposit Comment',
        data: 'Comment',
        searchable: true
      },
      {
        title: 'Closed',
        data: 'Closed',
        searchable: true,
        render: function (data, type, full, meta) {
          return data == 1 ? 'Yes' : 'No';
        }
      },
      {
        title: 'Deposit Type',
        data: 'Type',
        searchable: true
      }
    ],
    order: [0, 'desc']
  });

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

  $('#deleteSelectedRows').click(function () {
    var deletedRows = dataT.rows('.selected').data()
    $("#deleteNumber").text(deletedRows.length);
    $("#confirmDelete").modal('show');
  });


  $('.exportButton').click(function (sender) {
    var selectedRows = dataT.rows('.selected').data()
    var type = this.getAttribute("data-exportType");
    $.each(selectedRows, function (index, value) {
      window.CRM.VerifyThenLoadAPIContent(window.CRM.root + '/api/deposits/' + value.Id + '/' + type);
    });
  });

  $("#deleteConfirmed").click(function () {
    var deletedRows = dataT.rows('.selected').data()
    $.each(deletedRows, function (index, value) {
      $.ajax({
        type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
        url: window.CRM.root + '/api/deposits/' + value.Id, // the url where we want to POST
        dataType: 'json', // what type of data do we expect back from the server
        encode: true,
        data: {"_METHOD": "DELETE"}
      })
        .done(function (data) {
          $('#confirmDelete').modal('hide');
          dataT.rows('.selected').remove().draw(false);
        });
    });
  });
});
