<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('connection', function () {
    $pdo = Connection::get()->connect();
    return $pdo;
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'main.phtml');
})->setName("index");

$app->get('/urls', function ($request, $response) {
    return $this->get('renderer')->render($response, 'show.phtml');
})->setName("urls.store");

$app->run();
