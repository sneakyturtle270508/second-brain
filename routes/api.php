<?php

use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/ai/summarize', function (Request $request, AiService $ai) {
    $text = (string) $request->input('text', '');

    if (mb_strlen(trim($text)) < 20) {
        return response()->json(['error' => 'Send litt mer tekst (minst ~20 tegn).'], 422);
    }

    $messages = [
        ['role' => 'system', 'content' => "Du er en presis assistent. Svar på norsk. Lag et kort sammendrag (maks 6 linjer) og deretter 3–6 punktlister med nøkkelpunkter."],
        ['role' => 'user', 'content' => $text],
    ];

    $out = $ai->chat($messages, [
        'options' => ['temperature' => 0.2],
    ]);

    return response()->json([
        'text' => data_get($out, 'message.content'),
        'model' => data_get($out, 'model'),
    ]);
});

use App\Http\Controllers\AiController;

Route::get('/ai/ping', [AiController::class, 'ping']);
Route::post('/ai/search', [AiController::class, 'search']);
Route::post('/ai/ask', [AiController::class, 'ask']);

