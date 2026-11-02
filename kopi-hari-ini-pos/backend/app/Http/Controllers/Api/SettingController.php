<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    /**
     * GET /api/settings
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json(['data' => $settings]);
    }

    /**
     * PUT /api/settings
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name'     => 'sometimes|string|max:100',
            'store_address'  => 'sometimes|string|max:255',
            'store_phone'    => 'sometimes|string|max:30',
            'tax_percent'    => 'sometimes|numeric|min:0|max:100',
            'hpp_method'     => ['sometimes', Rule::in(['weighted_average', 'fifo'])],
            'receipt_footer' => 'sometimes|string|max:255',
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value);
        }

        $settings = Setting::all()->pluck('value', 'key');
        return response()->json(['data' => $settings]);
    }
}
