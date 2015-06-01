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

        $(".loaderWrapper").show();
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

                    $(".loaderWrapper").hide();
                    $("#sprintDetails").show();

                });
            });
        });
    };

    /**
     * Load Sprints
     */
    var loadBoardSprints = function(boardId) {

        $("#sprintDetails").hide();
        $("#selectSprint").html("");
        $(".loaderWrapper").show();

        $.get("/boards/" + boardId + "/sprints", function(data) {

            $(".loaderWrapper").hide();

            $("#selectSprint").append(selectTpl({
                title: "Select Sprint",
                boards: data.sprints
            }));

            $("#selectSprint .dropdown-menu a").bind('click.jirastic', function(e) {
                e.preventDefault();

                var el = $(this);

                el.parents(".btn-group").find(".title").text(el.text());

                loadSprintDetails(boardId, el.data("id"));
            });
        });
    };

    /**
     * Load boards
     */
    $.get("/boards", function(data) {

        $(".loaderWrapper").hide();

        $("#selectBoard").append(selectTpl({
            title: "Select Board",
            boards: data.views
        }));

        $("#selectBoard .dropdown-menu a").bind('click.jirastic', function(e) {
            e.preventDefault();

            var el = $(this);

            el.parents(".btn-group").find(".title").text(el.text());

            loadBoardSprints(el.data("id"));
        });
    });

    // owner - customfield_16025

    // $('#closed')
    //
    //
    //
    //
    //
    //
    //
    //


    /**
     * THE EPIC LOADER :)
     */
    var container = document.getElementById('loaderContainer');
    var loader = document.getElementById('loader');
    var circleL = document.getElementById('circleL');
    var circleR = document.getElementById('circleR');
    var jump = document.getElementById('jump');
    var jumpRef = jump.cloneNode();

    loader.appendChild(jumpRef);

    TweenMax.set([container, loader], {
        position: 'absolute',
        top:'50%',
        left: '50%',
        xPercent: -50,
        yPercent: -50
    })

    TweenMax.set(jumpRef, {
        transformOrigin: '50% 110%',
        scaleY: -1,
        alpha: 0.05
    })

    var tl = new TimelineMax({
        repeat: -1,
        yoyo: false
    });

    tl.timeScale(3);

    tl.set([jump, jumpRef], {
        drawSVG: '0% 0%'
    })
    .set([circleL, circleR], {
        attr: {
            rx: 0,
            ry: 0,
        }
    })
    .to([jump, jumpRef], 0.4, {
        drawSVG: '0% 30%',
        ease: Linear.easeNone
    })
    .to(circleL, 2, {
        attr: {
            rx: '+=30',
            ry: '+=10'
        },
        alpha: 0,
        ease: Power1.easeOut
    }, '-=0.1')
    .to([jump, jumpRef], 1, {
        drawSVG: '50% 80%',
        ease: Linear.easeNone
    }, '-=1.9')
    .to([jump, jumpRef], 0.7, {
        drawSVG: '100% 100%',
        ease: Linear.easeNone
    }, '-=0.9')
    .to(circleR, 2, {
        attr: {
            rx: '+=30',
            ry: '+=10'
        },
        alpha: 0,
        ease: Power1.easeOut
    }, '-=.5');

}(jQuery, window));