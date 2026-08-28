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
