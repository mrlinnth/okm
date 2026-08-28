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

$routes->get('/servers', 'Servers::index');
$routes->post('/servers', 'Servers::store');
$routes->post('/servers/(:segment)/activate', 'Servers::activate/$1');
$routes->post('/servers/(:segment)/deactivate', 'Servers::deactivate/$1');
$routes->post('/servers/(:segment)/delete', 'Servers::delete/$1');

$routes->get('/subscriptions', 'Subscriptions::index');
$routes->post('/subscriptions', 'Subscriptions::store');
$routes->post('/subscriptions/(:segment)', 'Subscriptions::update/$1');
$routes->post('/subscriptions/(:segment)/extend', 'Subscriptions::extend/$1');
$routes->post('/subscriptions/(:segment)/expiry', 'Subscriptions::setExpiry/$1');
$routes->post('/subscriptions/(:segment)/enable', 'Subscriptions::enable/$1');
$routes->post('/subscriptions/(:segment)/disable', 'Subscriptions::disable/$1');
$routes->post('/subscriptions/(:segment)/reroll', 'Subscriptions::reroll/$1');
$routes->post('/subscriptions/(:segment)/move', 'Subscriptions::move/$1');
$routes->post('/subscriptions/(:segment)/delete', 'Subscriptions::delete/$1');
