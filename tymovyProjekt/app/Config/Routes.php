<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Odjezdy::admin');
$routes->get('admin', 'Odjezdy::admin');
$routes->post('admin/pridat', 'Odjezdy::pridat');
$routes->get('admin/smazat/(:num)', 'Odjezdy::smazat/$1');

$routes->get('odjezdy/(:segment)', 'Odjezdy::tabule/$1');
$routes->get('mapa', 'Odjezdy::mapa');

$routes->get('api/odjezdy/(:segment)', 'Odjezdy::odjezdyJson/$1');
$routes->get('api/tyden/(:segment)', 'Odjezdy::tydenJson/$1');
$routes->get('api/mapa', 'Odjezdy::mapaJson');
