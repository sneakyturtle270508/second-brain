<?php

use App\Models\AiDocument;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/ai/ask', function (Request $request, AiService $ai) {

    // Frontend sender "message", curl kan sende "q". Støtt begge.
    $q = trim((string) ($request->input('q') ?? $request->input('message') ?? ''));
    $k = (int) ($request->input('k') ?? 5);

    if ($q === '') {
        return response()->json(['error' => 'Missing message/q'], 422);
    }

    // 1) Embed spørsmålet
    $queryEmbedding = $ai->embed($q);

    // 2) Last dokumenter
    $docs = AiDocument::query()->get();

    // 3) Score med cosine similarity
    $scored = [];

    foreach ($docs as $doc) {
        $docEmbedding = $doc->embedding;

        // embedding lagret som JSON-string i DB
        if (is_string($docEmbedding)) {
            $docEmbedding = json_decode($docEmbedding, true) ?: [];
        }

        if (!is_array($docEmbedding) || count($docEmbedding) === 0) {
            continue;
        }

        $score = cosine_similarity($queryEmbedding, $docEmbedding);

        $scored[] = [
            'doc' => $doc,
            'score' => $score,
        ];
    }

    usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($scored, 0, max(1, $k));

    // 4) Bygg kontekst + sources (title + url)
    $context = '';
    $sources = [];

    foreach ($top as $i => $row) {
        $n = $i + 1;
        $doc = $row['doc'];

        $title = (string) ($doc->title ?: '(uten tittel)');

        // Bygg URL fra collection + slug (dette matcher eksempelet ditt)
        $collection = trim((string) ($doc->collection ?? ''), '/');
        $slug = trim((string) ($doc->slug ?? ''), '/');

        $path = ($collection !== '' && $slug !== '') ? "/{$collection}/{$slug}" : null;
        $url = $path ? url($path) : null;

        $sources[] = [
            'n' => $n,
            'title' => $title,
            'url' => $url,
            'score' => $row['score'],
        ];

        $context .= "SOURCE {$n}\n";
        $context .= "TITLE: {$title}\n";
        $context .= "URL: " . ($url ?: '') . "\n";
        $context .= "CONTENT:\n" . mb_substr((string) $doc->content, 0, 1500) . "\n\n";
    }

    // 5) Prompt som tvinger formatet du vil ha (og hindrer Wikipedia / generelt svar)
    $messages = [
        [
            'role' => 'system',
            'content' => <<<SYS
Du er en second-brain-assistent. Du får SOURCES med TITLE, URL og CONTENT.

REGLER:
- Svar KUN basert på CONTENT i SOURCES. Ikke bruk Wikipedia eller generell kunnskap.
- Hvis svaret ikke finnes i notatene: skriv nøyaktig: "Jeg finner ikke dette i notatene dine."
- Skriv på naturlig norsk bokmål.
- Ikke skriv "SOURCE 1" i selve svaret.

SVARFORMAT:
For hvert notat du bruker (maks 3 notater), skriv nøyaktig dette:
hentet fra: <TITLE>
ifølge <TITLE>, <kort svar med konkrete detaljer fra CONTENT>
gå til notat: <URL>
SYS
        ],
        [
            'role' => 'user',
            'content' => "SPØRSMÅL:\n{$q}\n\nSOURCES:\n{$context}",
        ],
    ];

    $out = $ai->chat($messages, [
        'options' => ['temperature' => 0.1],
    ]);

    return response()->json([
        'answer' => data_get($out, 'message.content'),
        'sources' => $sources,
    ]);

})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
