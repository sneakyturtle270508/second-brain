<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\AiService;
use App\Models\AiDocument;

Route::post('/ai/ask', function (Request $request, AiService $ai) {
    // Støtt både {q:"..."} og {message:"..."} fra frontend
    $q = trim((string) ($request->input('q') ?? $request->input('message') ?? ''));
    $k = max(1, (int) ($request->input('k') ?? 5));

    if ($q === '') {
        return response()->json(['error' => 'Missing q/message'], 422);
    }

    // 1) Embed spørsmålet
    $queryEmbedding = $ai->embed($q);

    if (!is_array($queryEmbedding) || count($queryEmbedding) < 10) {
        return response()->json(['error' => 'Bad query embedding'], 500);
    }

    // 2) Last dokumenter
    $docs = AiDocument::query()->get();

    // 3) Score
    $scored = [];
    $needle = mb_strtolower($q);

    foreach ($docs as $doc) {
        // embedding ligger ofte lagret som JSON-string i sqlite -> decode til array
        $docEmbedding = $doc->embedding;

        if (is_string($docEmbedding)) {
            $decoded = json_decode($docEmbedding, true);
            $docEmbedding = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($docEmbedding) || count($docEmbedding) < 10) {
            continue; // skip docs uten embedding
        }

        $score = cosine_similarity($queryEmbedding, $docEmbedding);

        // liten keyword-boost så korte ord som "ost" treffer bedre
        if (str_contains(mb_strtolower((string) $doc->content), $needle)) {
            $score += 0.15;
        }

        $scored[] = ['doc' => $doc, 'score' => $score];
    }

    usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($scored, 0, $k);

    if (count($top) === 0) {
        return response()->json([
            'answer' => 'Jeg fant ingen relevante notater.',
            'sources' => [],
        ]);
    }

    // 4) Bygg kontekst
    $context = '';
    foreach ($top as $i => $row) {
        $n = $i + 1;
        $context .= "SOURCE {$n}: {$row['doc']->title}\n";
        $context .= mb_substr((string) $row['doc']->content, 0, 1500) . "\n\n";
    }

    // 5) Spør modellen
    $messages = [
        [
            'role' => 'system',
            'content' => 'Du er en second-brain-assistent. Svar kort og presist, kun basert på kildene. Hvis kildene ikke dekker spørsmålet, si det rett ut. Referer til kilder som (SOURCE 1), (SOURCE 2).'
        ],
        [
            'role' => 'user',
            'content' => "SPØRSMÅL:\n{$q}\n\nKILDER:\n{$context}"
        ],
    ];

    $out = $ai->chat($messages, [
        'options' => ['temperature' => 0.2],
    ]);

    return response()->json([
        'answer' => data_get($out, 'message.content') ?? '(tomt svar)',
        'sources' => array_map(fn ($row) => [
            'title' => $row['doc']->title,
            'score' => $row['score'],
        ], $top),
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
