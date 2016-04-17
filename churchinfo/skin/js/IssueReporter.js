$("#submitIssue").click(function()
{
  var postData = {
    "issueTitle": $("input:text[name=issueTitle]").val(),
    "issueDescription": $("textarea[name=issueDescription]").val()
  };
  $.ajax({
    method: "POST",
    url: window.CRM.root + "/api/issues",
    data: JSON.stringify(postData)
  }).done(function(data)
  {
    console.log(data);
    $("#IssueReportModal .modal-body").empty();
    $("<h2/>").text("Successfully submitted Issue #" + data.number).appendTo("#IssueReportModal .modal-body");
    $("<a/>", {
      href: data.url,
      target: "_blank",
      text: "View Issue #" + data.number + " on GitHub"
    }).appendTo("#IssueReportModal .modal-body");
    $("#submitIssue").remove();
    $("<button/>").text("Close").attr("data-dismiss", "modal").addClass("btn btn-primary").appendTo("#IssueReportModal .modal-footer");
  });

});