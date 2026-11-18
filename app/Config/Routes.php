<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */




$routes->get('/', 'TourGuideController::index');
$routes->get('tour-guides', 'TourGuideController::index');

// RESTful API Endpoints (Untuk dikonsumsi halaman Web & Integrasi Kelompok 1/3/4/5)
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    $routes->get('tour-guides', 'TourGuideApiController::index');
    $routes->get('tour-guides/stats', 'TourGuideApiController::stats');
    $routes->get('tour-guides/(:segment)', 'TourGuideApiController::show/$1');
    $routes->post('tour-guides/(:segment)/book', 'TourGuideApiController::book/$1');
    $routes->put('tour-guides/(:segment)/status', 'TourGuideApiController::updateStatus/$1');
});