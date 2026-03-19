<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function show()
    {
        $settings = Cache::get('global_settings', [
            'maintenance' => false,
            'default_free_packs' => 1,
        ]);

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance' => ['boolean'],
            'default_free_packs' => ['integer', 'min:0', 'max:10'],
        ]);

        $settings = Cache::get('global_settings', []);
        $settings = array_merge($settings, $data);
        Cache::put('global_settings', $settings, now()->addDays(30));

        return response()->json(['settings' => $settings]);
    }
}
