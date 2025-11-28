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
        // Use issue template selection to mark this as a bug (new GitHub issue type).
        // Use multiple params to encourage GitHub to select the correct template.
        // include `template=` and `issue_template=` variants and prefill the title.
        var title = encodeURIComponent('Bug: ');
        var gitHubURL =
            "https://github.com/ChurchCRM/CRM/issues/new?assignees=&template=bug_report.md&issue_template=bug_report.md&title=" +
            title +
            "&body=" +
            systemInfo;
        window.open(gitHubURL, `github`);
        $("#IssueReportModal").modal("toggle");
    });
});
