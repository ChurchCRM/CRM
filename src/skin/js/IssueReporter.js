$("#submitIssue").click(function () {
    var postData = {
        pageName: $("input[name=pageName]").val(),
        screenSize: {
            height: screen.height,
            width: screen.width,
        },
        windowSize: {
            height: $(window).height(),
            width: $(window).width(),
        },
        pageSize: {
            height: $(document).height(),
            width: $(document).width(),
        },
    };
    $.ajax({
        method: "POST",
        url: window.CRM.root + "/api/issues",
        data: JSON.stringify(postData),
        contentType: "application/json; charset=utf-8",
        dataType: "json",
    }).done(function (data) {
        var bugMsg = "**Describe the issue** \n\n\n\n";
        var systemInfo = encodeURIComponent(bugMsg + data["issueBody"]);
        var gitHubURL =
            "https://github.com/ChurchCRM/CRM/issues/new?assignees=&labels=Web%20Report&body=" +
            systemInfo;
        window.open(gitHubURL, `github`);
        $("#IssueReportModal").modal("toggle");
    });
});
