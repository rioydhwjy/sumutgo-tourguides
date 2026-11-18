<?php

namespace App\Controllers;

use App\Models\TourGuideModel;

class TourGuideController extends BaseController
{
    public function index(): string
    {
        $model = new TourGuideModel();

        $guides = $model->getFilteredGuides();
        $stats  = $model->getSummaryStats();

        $specializations = $model->db->table('specializations')
            ->orderBy('nama_spesialisasi', 'ASC')
            ->get()
            ->getResultArray();

        $languages = $model->db->table('languages')
            ->orderBy('nama_bahasa', 'ASC')
            ->get()
            ->getResultArray();

        return view('tour_guides/index', [
            'title'           => 'SUMUT ADVENTURE — Medan & North Sumatra Tour Guide Portal',
            'guides'          => $guides,
            'stats'           => $stats,
            'specializations' => $specializations,
            'languages'       => $languages,
        ]);
    }
}