<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Web Frontend Routes
$routes->get('/', 'TourGuideController::index');
$routes->get('tour-guides', 'TourGuideController::index');
$routes->get('admin', 'TourGuideController::admin');

// Legacy API endpoints (backward compatibility)
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    $routes->get('tour-guides', 'TourGuideApiController::index');
    $routes->get('tour-guides/stats', 'TourGuideApiController::stats');
    $routes->get('tour-guides/(:segment)', 'TourGuideApiController::show/$1');
    $routes->post('tour-guides/(:segment)/book', 'TourGuideApiController::book/$1');
    $routes->put('tour-guides/(:segment)/status', 'TourGuideApiController::updateStatus/$1');
});

// ====================================================================
// RESTful Web API v1 (CodeIgniter 4.7 + PostgreSQL 17 Supabase)
// ====================================================================
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function ($routes) {

    // 1. Authentication (Shared between Web Admin & Flutter Mobile)
    $routes->group('auth', static function ($routes) {
        $routes->post('register', 'AuthController::register', ['filter' => 'ratelimit:15,60']);
        $routes->post('login', 'AuthController::login', ['filter' => 'ratelimit:15,60']);
        $routes->get('me', 'AuthController::me', ['filter' => 'jwt']);
        $routes->post('logout', 'AuthController::logout', ['filter' => 'jwt']);
    });

    // 2. Master Data (6 Tables: specializations, languages, destinations, culinaries, hotels, transports)
    $routes->group('master', static function ($routes) {
        $routes->get('all', 'MasterDataController::all');
        $routes->get('(:segment)', 'MasterDataController::index/$1');
        $routes->get('(:segment)/(:num)', 'MasterDataController::show/$1/$2');
        $routes->post('(:segment)', 'MasterDataController::create/$1', ['filter' => ['jwt', 'role:admin']]);
        $routes->put('(:segment)/(:num)', 'MasterDataController::update/$1/$2', ['filter' => ['jwt', 'role:admin']]);
        $routes->delete('(:segment)/(:num)', 'MasterDataController::delete/$1/$2', ['filter' => ['jwt', 'role:admin']]);
    });

    // 3. Tour Guides Catalog & Management (with Optimistic Locking & Audit Trail)
    $routes->group('guides', static function ($routes) {
        $routes->get('', 'GuideController::index');
        $routes->get('(:segment)', 'GuideController::show/$1');
        $routes->post('', 'GuideController::create', ['filter' => ['jwt', 'role:admin']]);
        $routes->put('(:num)', 'GuideController::update/$1', ['filter' => ['jwt', 'role:admin']]);
        $routes->delete('(:num)', 'GuideController::delete/$1', ['filter' => ['jwt', 'role:admin']]);
    });

    // 4. Bookings (Transactional Stored Functions & Double-Booking Protection)
    $routes->group('bookings', static function ($routes) {
        $routes->get('', 'BookingController::index', ['filter' => 'jwt']);
        $routes->get('(:num)', 'BookingController::show/$1', ['filter' => 'jwt']);
        $routes->post('', 'BookingController::create', ['filter' => ['jwt', 'role:wisatawan,admin']]);
        $routes->patch('(:num)/status', 'BookingController::updateStatus/$1', ['filter' => 'jwt']);
    });

    // 5. Reviews (Triggers Business Guard: Completed Bookings Only)
    $routes->group('reviews', static function ($routes) {
        $routes->get('', 'ReviewController::index');
        $routes->post('', 'ReviewController::create', ['filter' => ['jwt', 'role:wisatawan']]);
    });

    // 6. Analytics & Audit Logs (Admin Audit Trail & Public Stats)
    $routes->get('audit-logs', 'AnalyticsController::auditLogs', ['filter' => ['jwt', 'role:admin']]);
    $routes->get('stats', 'AnalyticsController::stats');
});
