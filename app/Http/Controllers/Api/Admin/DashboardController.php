<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'stats' => [
                    'total_users' => User::count(),
                    'total_cards' => Card::count(),
                    'recent_users' => User::latest()->take(5)->get(['id', 'name', 'email', 'created_at']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['stats' => ['total_users' => 0, 'total_cards' => 0, 'recent_users' => []]], 500);
        }
    }
}


