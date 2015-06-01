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

    var selectTpl = Handlebars.compile($("#jira-select-board-sprint").html());
    var issueRowTpl = Handlebars.compile($("#jira-issue-row-template").html());

    // 293
    // 933

    var loadSprintDetails = function(board, sprint) {

        $("#sprintDetails").hide();

        $("#closed tbody").html("");
        $("#open tbody").html("");
        $("#resolved tbody").html("");

        $.get("/boards/" + board + "/sprints/" + sprint, function(data) {

            var allIssueKeys = [];

            console.log("completed");
            $.each(data.contents.completedIssues, function(index, issue) {
                allIssueKeys.push(issue.key);
                console.log(issue);

                // write header
                $("#closed tbody").append(issueRowTpl({
                    statusType: "success",
                    icon: "glyphicon-ok",
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
                var icon = "glyphicon-remove";

                // 1: // open
                // 3: // in progress
                // 4: // reopened
                // 5: // resolved
                // 10000: // acknowledged
                // 10039: // in review

                switch (parseInt(issue.status.id)) {
                    case 5:
                        type = "resolved";
                        statusType = "success";
                        icon = "glyphicon-ok";
                        break;
                    case 3:
                        statusType = "warning";
                        icon = "glyphicon-wrench";
                        break;
                    case 10039:
                        statusType = "warning";
                        icon = "glyphicon-eye-open";
                        break;
                }

                // write header
                $("#" + type + " tbody").append(issueRowTpl({
                    statusType: statusType,
                    icon: icon,
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

                    $("#sprintDetails").show();

                });
            });
        });
    };

    /**
     * Load Sprints
     */
    var loadBoardSprints = function(boardId) {
        $.get("/boards/" + boardId + "/sprints", function(data) {

            $("#selectSprint").append(selectTpl({
                title: "Select Sprint",
                boards: data.sprints
            }));

            $("#selectSprint .dropdown-menu a").bind('click.jirastic', function(e) {
                e.preventDefault();

                loadSprintDetails(boardId, $(this).data("id"));
            });
        });
    };

    /**
     * Load boards
     */
    $.get("/boards", function(data) {
        $("#selectBoard").append(selectTpl({
            title: "Select Board",
            boards: data.views
        }));

        $("#selectBoard .dropdown-menu a").bind('click.jirastic', function(e) {
            e.preventDefault();

            $("#selectSprint").html("");
            loadBoardSprints($(this).data("id"));
        });
    });

    // owner - customfield_16025

    // $('#closed')

}(jQuery, window));