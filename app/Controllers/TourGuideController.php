<?php

namespace App\Controllers;

use App\Models\MasterDataModel;
use App\Models\TourGuideModel;

class TourGuideController extends BaseController
{
    public function index(): string
    {
        $model = new TourGuideModel();
        $masterModel = new MasterDataModel();

        // 1. Ambil daftar pemandu dari view vw_guides_detail
        $res = $model->getFilteredGuides([], 1, 500);
        $guides = $res['items'];

        // 2. Ambil statistik ringkasan
        $stats = $model->getSummaryStats();

        // 3. Ambil data master untuk filter dropdown
        $specializations = $masterModel->getItems('specializations');
        $languages = $masterModel->getItems('languages');
        $destinations = $masterModel->getItems('destinations');
        $culinaries = $masterModel->getItems('culinaries');
        $hotels = $masterModel->getItems('hotels');
        $transports = $masterModel->getItems('transports');

        return view('tour_guides/index', [
            'title'           => 'SumutGO | Pemandu Wisata Resmi Medan & Sumatera Utara',
            'guides'          => $guides,
            'stats'           => $stats,
            'specializations' => $specializations,
            'languages'       => $languages,
            'destinations'    => $destinations,
            'culinaries'      => $culinaries,
            'hotels'          => $hotels,
            'transports'      => $transports,
        ]);
    }

    public function admin(): string
    {
        $model = new TourGuideModel();
        $res = $model->getFilteredGuides([], 1, 500);
        $guides = $res['items'];
        $stats = $model->getSummaryStats();

        return view('tour_guides/admin', [
            'title'  => 'SumutGO | Portal Admin, Live API & SQL',
            'guides' => $guides,
            'stats'  => $stats,
        ]);
    }
}
