/**
 * JIRASTIC
 */
(function($, window) {

    Handlebars.registerHelper('equal', function(lvalue, rvalue, options) {
        if (arguments.length < 3)
            throw new Error("Handlebars Helper equal needs 2 parameters");
        if( lvalue!=rvalue ) {
            return options.inverse(this);
        } else {
            return options.fn(this);
        }
    });

    $.get("/boards/223/sprints/876", function(data) {

        var allIssueKeys = [];

        console.log("completed");
        $.each(data.contents.completedIssues, function(index, issue) {
            allIssueKeys.push(issue.key);
            console.log(issue);

            var issueRow = Handlebars.compile($("#jira-issue-row-template").html());

            // write header
            $("#closed tbody").append(issueRow({
                statusType: "success",
                issueKey: issue.key,
                statusName: issue.status.name,
                summary: issue.summary,
                jiraUrl: data.urls.jira
            }));
        });

        console.log("incompleted");
        $.each(data.contents.incompletedIssues, function(index, issue) {
            allIssueKeys.push(issue.key);
            console.log(issue);

            var type = "open";
            var statusType = "danger";

            // 1: // open
            // 4: // reopened
            // 5: // resolved
            // 10000: // acknowledged
            // 10039: // in review
            if (5 === parseInt(issue.status.id)) {
                type = "resolved";
                statusType = "success";
            } else if (10039 === parseInt(issue.status.id)) {
                statusType = "warning";
            }

            var issueRow = Handlebars.compile($("#jira-issue-row-template").html());

            // write header
            $("#" + type + " tbody").append(issueRow({
                statusType: statusType,
                issueKey: issue.key,
                statusName: issue.status.name,
                summary: issue.summary,
                jiraUrl: data.urls.jira
            }));
        });

        console.log("punted");
        $.each(data.contents.puntedIssues, function(index, issue) {
            allIssueKeys.push(issue.key);
            console.log(issue);
        });

        console.log("load detail information");

        $.get('/issues?keys=' + allIssueKeys.join(','), function (data) {

            console.log(data);

            $.each(data.issues, function(index, issue) {
                var item = $("[data-key='" + issue.key + "']");

                var owner = issue.fields.creator.displayName;

                // owner field
                if (issue.fields.customfield_16025) {
                    owner = issue.fields.customfield_16025.displayName;
                }

                item.find(".owner").html(owner);

                var testInstructions = "";

                if (issue.fields.customfield_10478) {
                    testInstructions = issue.fields.customfield_10478;
                    testInstructions = "<p>" + testInstructions.split("\n").join("</p><p>") + "</p>";
                }

                item.find(".testInstructions").html(testInstructions).linkify();

            });
        });
    });

    // owner - customfield_16025

    // $('#closed')

}(jQuery, window));