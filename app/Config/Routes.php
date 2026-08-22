<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/about', 'About::index');
$routes->get('/blog', 'Blog::index');
$routes->get('/blog/(:segment)', 'Blog::show/$1');
$routes->get('/products', 'Products::index');
$routes->get('/products/(:segment)', 'Products::show/$1');

$routes->get('/classic', 'Classic::index');
$routes->post('/classic/keys/list', 'Classic::listKeys');
$routes->post('/classic/keys/create', 'Classic::createKey');
$routes->post('/classic/keys/delete', 'Classic::deleteKey');
$routes->post('/classic/keys/delete-all', 'Classic::deleteAllKeys');
$routes->post('/classic/keys/migrate', 'Classic::migrate');
