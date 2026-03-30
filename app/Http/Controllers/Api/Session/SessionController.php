<?php

namespace App\Http\Controllers\Api\Session;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * L'app Flutter revient au premier plan : on crée une nouvelle session.
     * On ferme d'abord toute session ouverte pour éviter les doublons.
     */
    public function start(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        UserSession::where('user_id', $userId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()
            ?->close();

        UserSession::create([
            'user_id' => $userId,
            'started_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * L'app Flutter passe en arrière-plan : on ferme la session en cours.
     */
    public function end(Request $request): JsonResponse
    {
        UserSession::where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()
            ?->close();

        return response()->json(['ok' => true]);
    }
}
