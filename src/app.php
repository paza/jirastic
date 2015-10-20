<?php

date_default_timezone_set('Europe/Zurich');

require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpFoundation\Session\Session;
use Silex\Provider\TwigServiceProvider;

ErrorHandler::register();

$app = new Application();
$app->register(new DerAlex\Silex\YamlConfigServiceProvider(__DIR__ . '/../config.yml'));

$app['debug'] = '127.0.0.1' === $_SERVER['REMOTE_ADDR'] || '::1' === $_SERVER['REMOTE_ADDR'];

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Paza\Provider\GuzzleServiceProvider(), array(
    'guzzle.client.read.user' => $app['config']['parameters']['login_read_user'],
    'guzzle.client.read.pass' => $app['config']['parameters']['login_read_pass'],
    'guzzle.client.write.user' => $app['config']['parameters']['login_write_user'],
    'guzzle.client.write.pass' => $app['config']['parameters']['login_write_pass'],
));

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.options' => array('cache' => __DIR__.'/../cache/twig', 'debug' => true),
));

/**
 * Execute a read request
 *
 * @param string $url
 */
$guzzleRead = function ($url) use ($app) {
    $client = $app['guzzle.client.read']();

    $response = $client->get($app['config']['parameters']['url_rest'] . $url)->send();

    // TODO: extract duplicate code
    $body = json_decode($response->getBody());

    $body->urls = [
        'jira' => $app['config']['parameters']['url_jira'],
        'rest' => $app['config']['parameters']['url_rest'],
    ];

    return $body;
};

//
/**
 * Execute a post request
 *
 * @param string $url
 * @param array $data
 */
$guzzlePost = function ($url, $data) use ($app) {
    $client = $app['guzzle.client.write']();

    $response = $client->post($app['config']['parameters']['url_rest'] . $url, $data)->send();

    // TODO: extract duplicate code
    $body = json_decode($response->getBody());

    $body->urls = [
        'jira' => $app['config']['parameters']['url_jira'],
        'rest' => $app['config']['parameters']['url_rest'],
    ];

    return $response;
};

/**
 * Get list of issues
 *
 * @param string keys comma separated list of keys
 */
$app->get(
    '/issues',
    function (Request $request) use ($guzzleRead) {

        $keys = $request->query->get('keys');

        if (empty($keys)) {
            $url = 'api/latest/search';
        } else {
            $url = sprintf(
                'api/latest/search?jql=key in (%s)',
                $keys
            );
        }

        // TODO: Render template
        return $guzzleRead($url);
    }
)
->bind('issues-search');

/**
 * Get issue details
 */
$app->get(
    '/issues/{issueNumber}',
    function (Request $request, $issueNumber) use ($guzzleRead) {
        // TODO: Render template
        return $guzzleRead(sprintf(
            'api/latest/issue/%s',
            $issueNumber
        ));
    }
)
->bind('issue');

/**
 * Get list of all boards
 */
$app->get(
    '/boards',
    function (Request $request) use ($guzzleRead, $app) {
        $boards = $guzzleRead('greenhopper/1.0/rapidview');

        return $app['twig']->render('sprints.html.twig', array(
            'boards' => $boards->views,
            'activeBoard' => false
        ));
    }
)
->bind('boards');

/**
 * Get sprints in a given board
 *
 * @param string boardId
 */
$app->get(
    '/boards/{boardId}/sprints',
    function (Request $request, $boardId) use ($guzzleRead, $app) {
        $boards = $guzzleRead('greenhopper/1.0/rapidview');
        $board = $guzzleRead(sprintf(
            'greenhopper/latest/sprintquery/%d?includeHistoricSprints=true&includeFutureSprints=true',
            $boardId
        ));

        return $app['twig']->render('sprints.html.twig', array(
            'boards' => $boards->views,
            'sprints' => array_reverse($board->sprints),
            'activeBoard' => $boardId
        ));
    }
)
->bind('board-sprints');

/**
 * Get sprint details
 *
 * @param string boardId
 * @param string sprintId
 */
$app->get(
    '/boards/{boardId}/sprints/{sprintId}',
    function (Request $request, $boardId, $sprintId) use ($guzzleRead, $app) {

        $sprintData = $guzzleRead(sprintf(
            'greenhopper/latest/rapid/charts/sprintreport?rapidViewId=%d&sprintId=%d',
            $boardId,
            $sprintId
        ));

        $allIssues = array_merge($sprintData->contents->completedIssues, $sprintData->contents->incompletedIssues);

        $allIssuesDetails = $guzzleRead(sprintf(
            'api/latest/search?jql=key in (%s)&expand=renderedFields',
            implode(',', array_map(function($item) {
                return $item->key;
            }, $allIssues))
        ))->issues;

        $statusMapping = [
            1 =>     'open', // open
            4 =>     'open', // reopened
            10142 => 'open', // ready for implementation
            10040 => 'open', // in planning
            10039 => 'inProgress', // review
            3 =>     'inProgress', // in progress
            5 =>     'resolved', // resolved
            6 =>     'closed', // closed
        ];

        // colors from https://colorlib.com/etc/metro-colors/
        $mappedIssues = [
            'closed' => [
                'id' => 'closed',
                'title' => 'closed stories',
                'titleShort' => 'closed',
                'icon' => 'fa-check-circle-o',
                'class' => 'resolved',
                'bgcolor' => '#1e7145',
                'issues' => []
            ],
            'resolved' => [
                'id' => 'resolved',
                'title' => 'resolved stories',
                'titleShort' => 'resolved',
                'icon' => 'fa-check-circle-o',
                'class' => 'resolved',
                'bgcolor' => '#1e7145',
                'issues' => []
            ],
            'inProgress' => [
                'id' => 'inProgress',
                'title' => 'stories in progress',
                'titleShort' => 'in progress',
                'icon' => 'fa-cog',
                'class' => 'inprogress',
                'bgcolor' => '#2b5797',
                'issues' => []
            ],
            'open' => [
                'id' => 'open',
                'title' => 'unresolved stories',
                'titleShort' => 'unresolved',
                'icon' => 'fa-exclamation-triangle',
                'class' => 'unresolved',
                'bgcolor' => '#b91d47',
                'issues' => []
            ],
        ];

        foreach ($allIssuesDetails as $issue) {

            $statusId = $issue->fields->status->id;

            if (!array_key_exists($statusId, $statusMapping)) {
                throw new \Exception(sprintf(
                    'Status unknown: %s (%d)',
                    $issue->fields->status->name,
                    $statusId
                ));
            }

            $issue->testInstruction = $issue->renderedFields->customfield_10478;


            $issue->creatorName = $issue->fields->creator->displayName;
            $issue->ownerName = $issue->fields->customfield_16025->displayName;
            $issue->assigneeName = $issue->fields->assignee->displayName;

            # https://issue.swisscom.ch/rest/greenhopper/1.0/rapid/charts/controlchart?rapidViewId=430&sprintId=1290
            # TODO: https://issue.swisscom.ch/rest/greenhopper/1.0/rapid/charts/scopechangeburndownchart?rapidViewId=430&sprintId=1290

            // Story Points Mapping
            $issue->storyPoints = $issue->fields->customfield_10150;
            $issue->storyPointsEstimate = $issue->fields->customfield_12223;

            //$issue->testInstruction = '<p>' . implode('</p><p>', explode("\n", $issue->testInstruction)) . '</p>';

            $mappedIssues[$statusMapping[$statusId]]['issues'][] = $issue;
        }

        # http://localhost:8080/boards/427/sprints/1246#/6

        $mappedIssues = array_map(function($item) {
            $item['total'] = count($item['issues']);

            return $item;
        }, $mappedIssues);

        return $app['twig']->render('sprint.html.twig', array(
            'mappedIssues' => $mappedIssues,
            'sprintData' => $sprintData
        ));
    }
)
->bind('sprint-presentation');

/**
 * Update the workflow state of a given issue
 *
 * @param string issueNumber
 */
$app->post(
    '/issues/{issueNumber}',
    function (Request $request, $issueNumber) use ($guzzlePost) {

        $to = trim($request->query->get('transitionTo'));

        switch ($to) {
            case "closed":
                $toId = 2; // if current status is OPEN / REOPENED
                $toId = 701; // if current status is RESOLVED
                break;

            case "resolved":
                $toId = 5;
                break;

            case "reopened":
                $toId = 3;
                break;

            default:
                throw new \InvalidArgumentException('transitionTo can either be "closed", "resolved" or "reopened"');
        }

        // TODO: Render template?
        return $guzzlePost(sprintf(
            'api/2/issue/%s/transitions?expand=transitions.fields',
            $issueNumber
        ), [
            'update' => [
                'comment' => [
                    [
                        'add' => [
                            'body' => 'Status changed using jirastic bot'
                        ]
                    ]
                ]
            ],
            'transition' => $toId
        ]);
    }
)
->bind('issue-transition');

// burndown rest/greenhopper/1.0/rapid/charts/scopechangeburndownchart?rapidViewId=223&sprintId=829

return $app;