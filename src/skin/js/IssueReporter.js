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
        // Include body parameter to show system info in GitHub issue
        // Template parameter still works alongside body parameter
        var gitHubTemplateURL =
            "https://github.com/ChurchCRM/CRM/issues/new?assignees=&template=bug_report&body=" + systemInfo;
        window.open(gitHubTemplateURL, `github`);
        $("#IssueReportModal").modal("toggle");
    });
});
