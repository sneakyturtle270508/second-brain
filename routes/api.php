<?php

use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\AiDocument;
use Illuminate\Support\Str;

Route::post('/ai/clip', function (Request $request, AiService $ai) {

    $text  = trim((string) $request->input('text', ''));
    $url   = trim((string) $request->input('url', ''));
    $title = trim((string) $request->input('title', ''));

    if (mb_strlen($text) < 10) {
        return response()->json(['error' => 'Select litt mer tekst (minst ~10 tegn).'], 422);
    }

    // Title fallback
    if ($title === '') {
        $title = Str::limit($text, 60, '…');
    }

    // 1) Lag embedding med en gang (enklest)
    try {
        $embedding = $ai->embed($text);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Kunne ikke lage embedding: ' . $e->getMessage()], 500);
    }

    // 2) Lagre i ai_documents (samme DB du søker i)
    $doc = AiDocument::create([
        'title' => $title,
        'content' => $text,
        'url' => $url,
        'collection' => 'clips',
        'embedding' => $embedding, // hvis du lagrer som json-string, gjør json_encode($embedding)
    ]);

    return response()->json([
        'ok' => true,
        'id' => $doc->id,
        'title' => $doc->title,
    ]);
});




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

