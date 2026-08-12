<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\GamificationService;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(): View
    {
        return view('badges.index', [
            'active' => 'badges',
            'badges' => GamificationService::badgeDefinitions(),
            'fallback' => [
                'name' => 'On Progress',
                'condition' => 'Ditampilkan ketika staff belum memenuhi syarat badge mana pun.',
                'source' => 'Status awal otomatis dari Arena Performa Staff dan Profil.',
                'tone' => 'slate',
            ],
        ]);
    }
}
