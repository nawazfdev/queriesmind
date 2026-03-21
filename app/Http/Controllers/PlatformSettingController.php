<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsPayload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json([
            'message' => 'Platform settings updated.',
            'data' => $this->settingsPayload(),
        ]);
    }

    protected function settingsPayload(): array
    {
        return PlatformSetting::query()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => $setting->value])
            ->all();
    }
}
