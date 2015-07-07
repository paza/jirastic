<?php

date_default_timezone_set('Europe/Zurich');

require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpFoundation\Session\Session;

ErrorHandler::register();

$app = new Application();

$app['debug'] = '127.0.0.1' === $_SERVER['REMOTE_ADDR'] || '::1' === $_SERVER['REMOTE_ADDR'];

// Load config
$config = json_decode(@file_get_contents(__DIR__ . '/../config.json'), true);

if (empty($config)) {
    throw new \LogicException('Please configure the app by copying config.json.dist to config.json');
}

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Paza\Provider\GuzzleServiceProvider(), array(
    'guzzle.client.read.user' => $config['logins']['read']['user'],
    'guzzle.client.read.pass' => $config['logins']['read']['pass'],
    'guzzle.client.write.user' => $config['logins']['write']['user'],
    'guzzle.client.write.pass' => $config['logins']['write']['pass'],
));

/**
 * Execute a read request
 *
 * @param string $url
 */
$guzzleRead = function ($url) use ($app, $config) {
    $client = $app['guzzle.client.read']();

    $response = $client->get($config['urls']['rest'] . $url)->send();

    // TODO: extract duplicate code
    $body = json_decode($response->getBody());

    $body->urls = $config['urls'];
    $body->retroepics = $config['retrospective-epics'];

    $response = new Response(json_encode($body), $response->getStatusCode());
    $response->headers->set('Content-Type', 'application/json');

    return $response;
};

//
/**
 * Execute a post request
 *
 * @param string $url
 * @param array $data
 */
$guzzlePost = function ($url, $data) use ($app, $config) {
    $client = $app['guzzle.client.write']();

    $response = $client->post($config['urls']['rest'] . $url, $data)->send();

    // TODO: extract duplicate code
    $body = json_decode($response->getBody());

    $body->urls = $config['urls'];
    $body->retroepics = $config['retrospective-epics'];

    $response = new Response(json_encode($body), $response->getStatusCode());
    $response->headers->set('Content-Type', 'application/json');

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
    function (Request $request) use ($guzzleRead) {
        return $guzzleRead('greenhopper/1.0/rapidview');
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
    function (Request $request, $boardId) use ($guzzleRead) {
        return $guzzleRead(sprintf(
            'greenhopper/latest/sprintquery/%d?includeHistoricSprints=true&includeFutureSprints=true',
            $boardId
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
    function (Request $request, $boardId, $sprintId) use ($guzzleRead) {
        return $guzzleRead(sprintf(
            'greenhopper/latest/rapid/charts/sprintreport?rapidViewId=%d&sprintId=%d',
            $boardId,
            $sprintId
        ));
    }
)
->bind('board-sprint-detail');

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