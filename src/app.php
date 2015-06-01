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

$config = json_decode(@file_get_contents(__DIR__ . '/../config.json'), true);

if (empty($config)) {
    throw new \LogicException('Please configure the app by copying config.json.dist to config.json');
}

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Paza\Provider\GuzzleServiceProvider(), array(
    'guzzle.client.read.user' => $config['logins']['read']['user'],
    'guzzle.client.read.pass' => $config['logins']['read']['pass'],
));

$guzzleRead = function ($url) use ($app, $config) {
    $client = $app['guzzle.client.read']();

    $response = $client->get($config['urls']['rest'] . $url)->send();

    $body = json_decode($response->getBody());

    $body->urls = $config['urls'];

    $response = new Response(json_encode($body), $response->getStatusCode());
    $response->headers->set('Content-Type', 'application/json');

    return $response;
};

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

$app->get(
    '/boards',
    function (Request $request) use ($guzzleRead) {
        return $guzzleRead('greenhopper/1.0/rapidview');
    }
)
->bind('boards');

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

// burndown rest/greenhopper/1.0/rapid/charts/scopechangeburndownchart?rapidViewId=223&sprintId=829

return $app;