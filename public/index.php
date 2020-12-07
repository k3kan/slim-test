<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

function validate($user)
 {
    $errors = [];
    if (empty($user['name'])) {
        $errors['name'] = "Can't be blank name";
    }
    if (empty($user['email'])) {
         $errors['email'] = "Can't be blank email";
    }
    if (empty($user['id'])) {
         $errors['id'] = "Can't be blank email";
    }
    return $errors;
}

function validateEdit($user)
{
    $errors = [];
    if (empty($user['name'])) {
        $errors['name'] = "Can't be blank name";
    }
    return $errors;
}

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $cookie = json_decode($request->getCookieParam('users', json_encode([])), true);
    $names = [];
    foreach ($cookie as $users) {
        $names[] = $users['name'];
    }
    if (empty($term)) {
        $courses = $names;
    }
    else {
        $courses = array_filter($names, function ($name) use($term) {
            $pos = strpos(strtolower($name), strtolower($term));
            if ($pos !== false) {
                return $name;
            }
        });
    }
    $flash= $this->get('flash')->getMessages();
    $params = ['courses' => $courses,
        'term' => $term,
        'flash' => $flash];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');



$app->get('/users/new', function ($request, $response) {
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    foreach ($users as $user) {
        $userId = (int) $user['id'] + 1;
    }
    if (empty($userId)) {
        $userId = 1;
    }
    $params = [
        'user' => ['name' => '', 'email' => '', 'id' => $userId],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $username = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $count = 0;
    $nameUser = [];
    foreach ($users as $user) {
        if (strtolower($user['name']) === strtolower($username)) {
            $count = 1;
            $nameUser[] = $user['name'];
        }
    }
    if ($count === 0) {
        return $response->write("Пользователь не найден")->withStatus(404);
    }
    $params = ['user' => $nameUser];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->post('/users', function ($request, $response) use($router) {
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    if (count($errors) === 0) {
        $users = json_decode($request->getCookieParam('users', json_encode([])));
        $users[] = $user;
        $encodedUsers = json_encode($users);
        $this->get('flash')->addMessage('success', 'Пользователь добавлен');
        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect($router->urlFor('users'), 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/form', function ($request, $response) {
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $params = [
        'users' => $users,
    ];
    return $this->get('renderer')->render($response, "users/form.phtml", $params);
});


$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    foreach ($users as $user) {
        if ($user['id'] === $id) {
            $needfulUser[] = $user;
        }
    }
    if (count($needfulUser) === 0) {
        return $response->write("Пользователь не найден")->withStatus(404);
    }
    $params = [
        'user' => $needfulUser[0],
        'errors' => [],
        'postUser' => $needfulUser
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function ($request, $response, array $args)  use ($router) {
    $id = ['id' => $args['id']];
    $postUser = $request->getParsedBodyParam('user');
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $errors = validateEdit($postUser);
    if (count($errors) === 0) {
        $map = array_map( function ($user) use ($id, $postUser) {
            if ($user['id'] === $id['id']) {
                $user['name'] = $postUser['name'];
            }
            return $user;
        }, $users);
        $this->get('flash')->addMessage('success', 'User has been updated');
        $encodedUsers = json_encode($map);
        $url = $router->urlFor('users');
        return $response->withHeader('Set-Cookie', "users={$encodedUsers};Path=/")->withRedirect($url);
    }
    $params = [
        'user' => $id,
        'postUser' => $postUser,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->get('/session', function ($request, $response){
    $message = $this->get('flash')->getMessages();
    $params = [
        'user' => ['email' => ''],
        'session' => $_SESSION,
        'flash' => $message
    ];
    return $this->get('renderer')->render($response, 'users/authentication.phtml', $params);
})->setName('session');

$app->post('/session', function ($request, $response) use ($router) {
    $postData = $request->getParsedBodyParam('user');
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    foreach ($users as $user) {
        if ($user['email'] === $postData['email']) {
            $_SESSION['email'] = $user['email'];
        }
    }
    if (!isset($_SESSION['email'])) {
        $this->get('flash')->addMessage('false', 'Wrong password or name');
    }
    $url = $router->urlFor('session');
    return $response->withRedirect($url);
});

$app->delete('/session', function ($request, $response) use ($router) {
    $_SESSION = [];
    session_destroy();
    $url = $router->urlFor('session');
    return $response->withRedirect($url);
});

$app->run();
