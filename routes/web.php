<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\AiService;
use App\Models\AiDocument;

Route::post('/ai/ask', function (Request $request, AiService $ai) {

    // Frontend sender "message", curl-sendte du "q" før. Støtter begge.
    $q = trim((string) ($request->input('message') ?? $request->input('q') ?? ''));
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
        // embedding er lagret som JSON-string
        $docEmbedding = $doc->embedding;
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

    // 4) Bygg kontekst + source mapping (title + url)
    $context = '';
    $sourceMap = [];

    foreach ($top as $i => $row) {
        $n = $i + 1;

        $title = $row['doc']->title ?: '(uten tittel)';
        $url   = $row['doc']->url ?? null; // sørg for at url finnes i tabellen/filled i indexeren

        $sourceMap[] = [
            'source' => "SOURCE {$n}",
            'title' => $title,
            'url' => $url,
            'score' => $row['score'],
        ];

        $context .= "SOURCE {$n}\n";
        $context .= "TITLE: {$title}\n";
        if ($url) $context .= "URL: {$url}\n";
        $context .= "CONTENT:\n" . mb_substr((string) $row['doc']->content, 0, 1500) . "\n\n";
    }

    // 5) Prompt som tvinger formatet du vil ha
    $messages = [
        [
            'role' => 'system',
            'content' =>
                "Du er en second-brain-assistent som bare bruker kildene under.\n".
                "SVARFORMAT (må følges):\n".
                "Hentet fra: <TITLE>\n".
                "Svar: <1-3 setninger som besvarer spørsmålet>\n".
                "Ifølge <TITLE> står det at ... (bruk konkrete punkter fra content)\n".
                "Gå til notat: <URL>\n\n".
                "Regler:\n".
                "- Bruk kun TITLE/URL som står i kildene.\n".
                "- Hvis URL mangler, skriv: Gå til notat: (mangler url)\n".
                "- Ikke nevn Wikipedia eller eksterne kilder.\n".
                "- Ikke skriv 'SOURCE 1' i svaret, bruk TITLE.\n"
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
        'answer' => data_get($out, 'message.content'),
        'sources' => $sourceMap,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
