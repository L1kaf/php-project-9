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
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

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
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'main.phtml');
})->setName("index");

$app->get('/urls', function ($request, $response) {
    $dataBase = new SqlQuery($this->get('connection'));
    $dataFromBase = $dataBase->query('SELECT id, name FROM urls ORDER BY id DESC');
    $dataFromChecks = $dataBase->query(
        'SELECT url_id, MAX(created_at) AS created_at
        FROM url_checks
        GROUP BY url_id'
    );

    $combinedData = array_map(function ($url) use ($dataFromChecks) {
        foreach ($dataFromChecks as $check) {
            if ($url['id'] === $check['url_id']) {
                $url['created_at'] = $check['created_at'];
            }
        }
        return $url;
    }, $dataFromBase);

    $params = ['data' => $combinedData];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName("urls.store");

$app->post('/urls', function ($request, $response) use ($router) {
    $urls = $request->getParsedBodyParam('url');
    $dataBase = new SqlQuery($this->get('connection'));
    $error = [];

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
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            return $response->withRedirect(
                $router->urlFor('urls.show', ['id' => $searchName[0]['id']])
            );
        }

        $urls['time'] = Carbon::now();
        $dataBase->query('INSERT INTO urls(name, created_at) VALUES(:name, :time) RETURNING id', $urls);

        $id = $dataBase->query('SELECT MAX(id) FROM urls');
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');

        return $response->withRedirect($router->urlFor('urls.show', ['id' => $id[0]['max']]));
    } else {
        if (isset($urls) && strlen($urls['name']) < 1) {
            $error['name'] = 'URL не должен быть пустым';
        } elseif (isset($urls)) {
            $error['name'] = 'Некорректный URL';
        }
    }

    $params = ['erorrs' => $error];
    return $this->get('renderer')->render($response->withStatus(422), 'main.phtml', $params);
});

$app->get('/urls/{id}', function ($request, $response, $args) {
    $messages = $this->get('flash')->getMessages();

    $dataBase = new SqlQuery($this->get('connection'));
    $dataFromBase = $dataBase->query('SELECT * FROM urls WHERE id = :id', $args);
    $dataFromChecks = $dataBase->query('SELECT * FROM url_checks WHERE url_id = :id ORDER BY id DESC', $args);
    $params = ['data' => $dataFromBase, 'flash' => $messages, 'checks' => $dataFromChecks];
    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName("urls.show");

$app->post('/urls/{id}/checks', function ($request, $response, $args) use ($router) {
    $id = $args['id'];

    $dataBase = new SqlQuery($this->get('connection'));

    $urls['url_id'] = $args['id'];
    $urls['time'] = Carbon::now();
    $dataBase->query('INSERT INTO url_checks(url_id, created_at) VALUES(:url_id, :time)', $urls);

    $this->get('flash')->addMessage('success', 'Страница успешно проверена');

    return $response->withRedirect(
        $router->urlFor('urls.show', ['id' => $id])
    );
})->setName("urls.checks");


$app->run();
