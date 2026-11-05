<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AndarmaSiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AndarmaSiteContentController extends Controller {
    public function index() {
        return response()->json(['data' => AndarmaSiteContent::query()
            ->orderBy('content_key')->pluck('content_value', 'content_key')]);
    }
    public function update(Request $request) {
        $validated = $request->validate([
            'data' => ['required','array','max:150'],
            'data.*' => ['required','string','max:1500'],
        ]);
        $existingKeys = AndarmaSiteContent::query()->pluck('content_key')->all();
        $unknown = array_diff(array_keys($validated['data']), $existingKeys);
        if (count($unknown) > 0) {
            return response()->json(['message' => 'Claves de contenido no reconocidas.', 'keys' => array_values($unknown)], 422);
        }
        DB::transaction(function () use ($validated) {
            foreach ($validated['data'] as $key => $value) {
                AndarmaSiteContent::where('content_key', $key)->update(['content_value' => trim($value)]);
            }
        });
        return $this->index();
    }
}
