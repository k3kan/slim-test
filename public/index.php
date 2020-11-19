<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
$userId = 0;

function validate($user)
 {
    $errors = [];
    if (empty($user['name'])) {
        $errors['name'] = "Can't be blank name";
    }
    if (empty($user['email'])) {
         $errors['name'] = "Can't be blank email";
    }
    if (empty($user['id'])) {
         $errors['id'] = "Can't be blank email";
    }
    return $errors;
}

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);


$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->get('/users', function ($request, $response) use($users) {
    $term = $request->getQueryParam('term');
    if (empty($term)) {
        $courses = $users;
    }
    else {
        $courses = array_filter($users, function ($name) use($term) {
            $pos = strpos(strtolower($name), strtolower($term));
            if ($pos !== false) {
                return $name;
            }
        });
    }
    $params = ['courses' => $courses, 'term' => $term];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->post('/form', function ($request, $response) use($router) {
    $file = 'users.txt';
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    if (count($errors) === 0) {
        $encode = json_encode($user);
        file_put_contents($file, "$encode\n", FILE_APPEND);
        return $response->withRedirect($router->urlFor('users'), 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/form', function ($request, $response) {
    $users = file_get_contents('./users.txt');
    $arrayUsers = explode("\n", $users);
    $params = [
        'users' => $arrayUsers,
    ];
    return $this->get('renderer')->render($response, "users/form.phtml", $params);
});

$app->get('/form/new', function ($request, $response) {
    $userId = rand();
    $params = [
        'user' => ['name' => '', 'email' => '', 'id' => $userId],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->run();
