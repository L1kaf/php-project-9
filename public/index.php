<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use App\CreateTables;
use App\SqlQuery;
use Slim\Flash\Messages;
use Valitron\Validator;
use Carbon\Carbon;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('connection', function () {
    $pdo = Connection::get()->connect();
    return $pdo;
});

$container->set('flash', function () {
    return new Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'main.phtml');
})->setName("index");

$app->get('/urls', function ($request, $response) {
    return $this->get('renderer')->render($response, 'show.phtml');
})->setName("urls.store");

$app->post('/urls', function ($request, $response) use ($router) {
    $urls = $request->getParsedBodyParam('url');
    $dataBase = new SqlQuery($this->get('connection'));
    $errors = [];

    try {
        $tableCreator = new CreateTables($this->get('connection'));
        $table = $tableCreator->createTableUrls();
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $v = new Validator(array('name' => $urls['name'], 'count' => strlen((string) $urls['name'])));
    $v->rule('required', 'name')->rule('lengthMax', 'count.*', 255)->rule('url', 'name');
    if ($v->validate()) {
        $parseUrl = parse_url($urls['name']);
        $urls['name'] = "{$parseUrl['scheme']}://{$parseUrl['host']}";

        $searchName = $dataBase->query('SELECT id FROM urls WHERE name = :name', $urls);

        if (count($searchName) !== 0) {
            return $response->withRedirect($router->urlFor('index'));
        }

        $urls['time'] = Carbon::now();
        $dataBase->query('INSERT INTO urls(name, created_at) VALUES(:name, :time) RETURNING id', $urls);
        return $response->withRedirect($router->urlFor('urls.store'));
    }
});

$app->run();
