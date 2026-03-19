<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $admin  = $request->user();
            $clubId = (!$admin->is_super_admin && $admin->club_team_id) ? $admin->club_team_id : null;

            return response()->json([
                'stats' => [
                    'total_users'  => User::where('is_super_admin', false)
                        ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
                        ->count(),
                    'total_cards'  => Card::when($clubId, fn($q) => $q->where('club_team_id', $clubId))->count(),
                    'recent_users' => User::where('is_super_admin', false)
                        ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
                        ->latest()->take(5)->get(['id', 'name', 'email', 'created_at']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['stats' => ['total_users' => 0, 'total_cards' => 0, 'recent_users' => []]], 500);
        }
    }
}

