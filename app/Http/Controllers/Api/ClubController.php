<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubTeam;

class ClubController extends Controller
{
    public function index()
    {
        $clubs = ClubTeam::select('id', 'name', 'is_active', 'logo', 'primary_color')
            ->where('is_active', true)
            ->where('is_main_club', true)
            ->orderBy('name')
            ->get();

        return response()->json(['clubs' => $clubs]);
    }
}
