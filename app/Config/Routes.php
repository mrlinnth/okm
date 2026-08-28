<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Classic::index');

$routes->get('/classic', 'Classic::index');
$routes->post('/classic/keys/list', 'Classic::listKeys');
$routes->post('/classic/keys/create', 'Classic::createKey');
$routes->post('/classic/keys/delete', 'Classic::deleteKey');
$routes->post('/classic/keys/delete-all', 'Classic::deleteAllKeys');
$routes->post('/classic/keys/migrate', 'Classic::migrate');

$routes->get('/manage', 'AdminAccess::index');
$routes->post('/manage', 'AdminAccess::authenticate', ['filter' => 'csrf']);
$routes->post('/manage/logout', 'AdminAccess::logout', ['filter' => ['adminauth', 'csrf']]);

$routes->group('servers', ['filter' => ['adminauth', 'csrf']], static function (RouteCollection $routes): void {
    $routes->get('/', 'Servers::index');
    $routes->post('/', 'Servers::store');
    $routes->post('(:segment)/activate', 'Servers::activate/$1');
    $routes->post('(:segment)/deactivate', 'Servers::deactivate/$1');
    $routes->post('(:segment)/delete', 'Servers::delete/$1');
});

$routes->group('subscriptions', ['filter' => ['adminauth', 'csrf']], static function (RouteCollection $routes): void {
    $routes->get('/', 'Subscriptions::index');
    $routes->post('/', 'Subscriptions::store');
    $routes->post('(:segment)', 'Subscriptions::update/$1');
    $routes->post('(:segment)/extend', 'Subscriptions::extend/$1');
    $routes->post('(:segment)/expiry', 'Subscriptions::setExpiry/$1');
    $routes->post('(:segment)/enable', 'Subscriptions::enable/$1');
    $routes->post('(:segment)/disable', 'Subscriptions::disable/$1');
    $routes->post('(:segment)/reroll', 'Subscriptions::reroll/$1');
    $routes->post('(:segment)/move', 'Subscriptions::move/$1');
    $routes->post('(:segment)/delete', 'Subscriptions::delete/$1');
});

$routes->get('/s/(:any)', 'Recipient::show/$1');
