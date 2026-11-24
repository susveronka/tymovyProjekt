<?php

/**
 * Routy aplikace odjezdových tabulí.
 * Načítá se z app/Config/Routes.php řádkem:
 *     require APPPATH . 'Config/RoutesOdjezdy.php';
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->get('/', '\App\Controllers\Odjezdy\Home::index');
$routes->get('api/zastavky', '\App\Controllers\Odjezdy\Home::search');

// veřejná odjezdová tabule (na tuto adresu míří QR kódy)
$routes->get('tabule/(:segment)', '\App\Controllers\Odjezdy\Board::show/$1');
$routes->get('api/odjezdy/(:segment)', '\App\Controllers\Odjezdy\Board::departures/$1');

// mapa
$routes->get('mapa', '\App\Controllers\Odjezdy\MapPage::index');
$routes->get('api/mapa', '\App\Controllers\Odjezdy\MapPage::stops');

// administrace
$routes->get('admin/login', '\App\Controllers\Odjezdy\Admin::login');
$routes->post('admin/login', '\App\Controllers\Odjezdy\Admin::attempt', ['filter' => 'csrf']);
$routes->post('admin/logout', '\App\Controllers\Odjezdy\Admin::logout', ['filter' => 'csrf']);
$routes->get('admin', '\App\Controllers\Odjezdy\Admin::index');
$routes->get('admin/zastavka/(:segment)', '\App\Controllers\Odjezdy\Admin::stop/$1');
$routes->get('admin/qr', '\App\Controllers\Odjezdy\Admin::qr');
