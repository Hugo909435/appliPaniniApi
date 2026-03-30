<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSessionStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week'); // today | week | month | all

        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week'  => now()->startOfWeek(\Carbon\Carbon::MONDAY),
            'month' => now()->startOfMonth(),
            default => null,
        };

        // Fermeture automatique des sessions abandonnées (app tuée sans logout)
        // On les considère comme terminées après 30 min d'inactivité
        UserSession::whereNull('ended_at')
            ->where('started_at', '<', now()->subMinutes(30))
            ->each(function (UserSession $s) {
                $end = $s->started_at->copy()->addMinutes(30);
                $s->ended_at = $end;
                $s->duration_seconds = (int) $s->started_at->diffInSeconds($end);
                $s->save();
            });

        $query = UserSession::whereNotNull('ended_at')
            ->with('user:id,name,email');

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        $sessions = $query->get();

        // Agrégation par utilisateur
        $byUser = $sessions
            ->groupBy('user_id')
            ->map(function ($userSessions, $userId) {
                $user = $userSessions->first()->user;
                $totalSeconds = (int) $userSessions->sum('duration_seconds');
                $count = $userSessions->count();

                return [
                    'id'                  => (int) $userId,
                    'name'                => $user?->name ?? '—',
                    'email'               => $user?->email ?? '—',
                    'total_seconds'       => $totalSeconds,
                    'session_count'       => $count,
                    'avg_session_seconds' => $count > 0 ? (int) round($totalSeconds / $count) : 0,
                    'last_seen'           => $userSessions->max('ended_at'),
                ];
            })
            ->values()
            ->sortByDesc('total_seconds')
            ->values();

        $totalSeconds = (int) $sessions->sum('duration_seconds');
        $totalSessions = $sessions->count();
        $activeUsers = $sessions->pluck('user_id')->unique()->count();

        return response()->json([
            'period' => $period,
            'totals' => [
                'total_seconds'       => $totalSeconds,
                'total_sessions'      => $totalSessions,
                'active_users'        => $activeUsers,
                'avg_session_seconds' => $totalSessions > 0 ? (int) round($totalSeconds / $totalSessions) : 0,
            ],
            'users' => $byUser,
        ]);
    }
}
