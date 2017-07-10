$("#submitIssue").click(function () {
  var postData = {
    "issueTitle": $("input:text[name=issueTitle]").val(),
    "issueDescription": $("textarea[name=issueDescription]").val(),
    "pageName" : $("input[name=pageName]").val(),
    "screenSize": {
        "height":screen.height,
        "width":screen.width
    },
    "windowSize":{
        "height":$(window).height(),
        "width":$(window).width()
    },
    "pageSize" : {
        "height" : $(document).height(),
        "width":$(document).width()
    }
  };
  $.ajax({
    method: "POST",
    url: window.CRM.root + "/api/issues",
    data: JSON.stringify(postData),
    contentType: "application/json; charset=utf-8",
    dataType: "json"
  }).done(function (data) {
    console.log(data);
    $("#IssueReportModal .modal-body").empty();
    $("<h2/>").text( i18next.t("Successfully submitted Issue")+" #" + data.number).appendTo("#IssueReportModal .modal-body");
    $("<a/>", {
      href: data.url,
      target: "_blank",
      text:  i18next.t("View Issue on GitHub") + ": #" + data.number
    }).appendTo("#IssueReportModal .modal-body");
    $("#submitIssue").remove();
    $("<button/>").text("Close").attr("data-dismiss", "modal").addClass("btn btn-primary").appendTo("#IssueReportModal .modal-footer");
  });

});
