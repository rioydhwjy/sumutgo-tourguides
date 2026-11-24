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

        // 1. Ambil 150 pemandu dari view vw_guides_detail
        $res = $model->getFilteredGuides([], 1, 150);
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
            'title'           => 'SUMUT GO — Enterprise Tour Guide Management & GIS Portal',
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
}
