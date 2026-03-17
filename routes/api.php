<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\PackController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\MoneyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\CardController;
use App\Http\Controllers\Api\Admin\ChallengeAdminController;
use App\Http\Controllers\Api\Admin\PackController as AdminPackController;
use App\Http\Controllers\Api\Admin\ClubTeamController;
use App\Http\Controllers\Api\Admin\ClubMatchController;
use App\Http\Controllers\Api\Admin\MatchWeekAdminController;

/*
|--------------------------------------------------------------------------
| Auth Routes (public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
    Route::get('/profile/customize', [ProfileController::class, 'customize']);
    Route::post('/profile/customize', [ProfileController::class, 'updateCustomization']);
    Route::get('/profile/{userId}', [ProfileController::class, 'showUser']);

    // Collection
    Route::get('/collection', [CollectionController::class, 'index']);
    Route::get('/collection/card/{card}', [CollectionController::class, 'showCard']);

    // Challenges
    Route::get('/challenges', [ChallengeController::class, 'index']);
    Route::post('/challenges/{challenge}/claim', [ChallengeController::class, 'claim']);

    // Friends
    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends/request', [FriendController::class, 'request']);
    Route::post('/friends/{friendship}/accept', [FriendController::class, 'accept']);
    Route::post('/friends/{friendship}/decline', [FriendController::class, 'decline']);
    Route::delete('/friends/{friendship}', [FriendController::class, 'remove']);

    // Packs
    Route::get('/packs', [PackController::class, 'index']);
    Route::post('/packs/opening', [PackController::class, 'opening']);
    Route::post('/packs/claim', [PackController::class, 'claim']);

    // Card Trades
    Route::get('/trades', [TradeController::class, 'index']);
    Route::get('/trades/my', [TradeController::class, 'myTrades']);
    Route::post('/trades', [TradeController::class, 'store']);
    Route::post('/trades/{trade}/accept', [TradeController::class, 'accept']);
    Route::delete('/trades/{trade}', [TradeController::class, 'cancel']);

    // Money
    Route::get('/money', [MoneyController::class, 'index']);
    Route::post('/money/create-intent', [MoneyController::class, 'createPaymentIntent']);
    Route::post('/money/process', [MoneyController::class, 'processPayment']);
    Route::get('/money/history', [MoneyController::class, 'history']);

    // Predictions
    Route::get('/matches/weeks', [PredictionController::class, 'weeks']);
    Route::get('/matches/week', [PredictionController::class, 'week']);
    Route::post('/predictions', [PredictionController::class, 'store']);
    Route::get('/predictions/history', [PredictionController::class, 'history']);
    Route::get('/predictions/leaderboard', [PredictionController::class, 'leaderboard']);
    Route::get('/predictions/results', [PredictionController::class, 'results']);
    Route::post('/predictions/claim/{weekStart}', [PredictionController::class, 'claimWeek']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin']);
    Route::post('/users/{user}/add-coins', [UserController::class, 'addCoins']);
    Route::post('/users/{user}/add-packs', [UserController::class, 'addPacks']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Cards
    Route::get('/cards', [CardController::class, 'index']);
    Route::post('/cards', [CardController::class, 'store']);
    Route::get('/cards/{card}', [CardController::class, 'show']);
    Route::post('/cards/{card}', [CardController::class, 'update']);
    Route::delete('/cards/{card}', [CardController::class, 'destroy']);

    // Packs
    Route::get('/packs', [AdminPackController::class, 'index']);
    Route::post('/packs', [AdminPackController::class, 'store']);
    Route::post('/packs/{pack}', [AdminPackController::class, 'update']);
    Route::delete('/packs/{pack}', [AdminPackController::class, 'destroy']);

    // Club Teams
    Route::get('/club-teams', [ClubTeamController::class, 'index']);
    Route::post('/club-teams', [ClubTeamController::class, 'store']);
    Route::post('/club-teams/{clubTeam}', [ClubTeamController::class, 'update']);
    Route::delete('/club-teams/{clubTeam}', [ClubTeamController::class, 'destroy']);

    // Club Matches
    Route::get('/club-matches', [ClubMatchController::class, 'index']);
    Route::post('/club-matches', [ClubMatchController::class, 'store']);
    Route::post('/club-matches/{clubMatch}', [ClubMatchController::class, 'update']);
    Route::delete('/club-matches/{clubMatch}', [ClubMatchController::class, 'destroy']);

    // Match Weeks
    Route::get('/match-weeks', [MatchWeekAdminController::class, 'index']);
    Route::post('/match-weeks', [MatchWeekAdminController::class, 'store']);
    Route::post('/match-weeks/{matchWeek}', [MatchWeekAdminController::class, 'update']);
    Route::delete('/match-weeks/{matchWeek}', [MatchWeekAdminController::class, 'destroy']);


    // Challenges
    Route::get('/challenges', [ChallengeAdminController::class, 'index']);
    Route::post('/challenges', [ChallengeAdminController::class, 'store']);
    Route::get('/challenges/{challenge}', [ChallengeAdminController::class, 'show']);
    Route::post('/challenges/{challenge}', [ChallengeAdminController::class, 'update']);
    Route::delete('/challenges/{challenge}', [ChallengeAdminController::class, 'destroy']);
    Route::post('/challenges/{challenge}/toggle-active', [ChallengeAdminController::class, 'toggleActive']);
});
