<?php

use App\Models\AiDocument;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
// ✅ Google login imports
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Statamic\Facades\Entry;
// ✅ Statamic Entry
use Statamic\Facades\User;

Route::post('/ai/ask', function (Request $request, AiService $ai) {

    // Frontend sender "message", curl kan sende "q". Støtt begge.
    $q = trim((string) ($request->input('q') ?? $request->input('message') ?? ''));
    $k = (int) ($request->input('k') ?? 5);
    $collection = trim((string) $request->input('collection', ''));
    $tag = trim((string) $request->input('tag', ''));

    if ($q === '') {
        return response()->json(['error' => 'Missing message/q'], 422);
    }

    try {
        // 1) Embed spørsmålet
        $queryEmbedding = $ai->embed($q);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => 'Kunne ikke lage embedding: '.$e->getMessage(),
        ], 500);
    }

    // 2) Last dokumenter (med enkel kontekstfiltrering)
    $docsQuery = AiDocument::query();
    if ($collection !== '') {
        $docsQuery->where('collection', $collection);
    }

    $docs = $docsQuery->get();
    if ($tag !== '' && Schema::hasColumn('ai_documents', 'tags')) {
        $docs = $docs->filter(function ($doc) use ($tag) {
            $tags = is_array($doc->tags) ? $doc->tags : [];

            return in_array($tag, $tags, true);
        })->values();
    }

    // 3) Score med cosine similarity
    $scored = [];

    $wordCount = count(array_filter(preg_split('/\s+/', $q) ?: []));
    $threshold = match (true) {
        $wordCount <= 2 => 0.35,
        $wordCount <= 5 => 0.3,
        $wordCount <= 9 => 0.27,
        default => 0.24,
    };

    foreach ($docs as $doc) {
        $docEmbedding = $doc->embedding;

        // embedding lagret som JSON-string i DB
        if (is_string($docEmbedding)) {
            $docEmbedding = json_decode($docEmbedding, true) ?: [];
        }

        if (! is_array($docEmbedding) || count($docEmbedding) === 0) {
            continue;
        }

        $score = cosine_similarity($queryEmbedding, $docEmbedding);

        if ($score < $threshold) {
            continue;
        }

        $scored[] = [
            'doc' => $doc,
            'score' => $score,
        ];
    }

    usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($scored, 0, max(1, $k));
    $primary = $top[0]['doc'] ?? null;

    if (! $primary) {
        return response()->json([
            'answer' => 'Jeg finner ikke dette i notatene dine.',
            'sources' => [],
            'primary_source' => null,
            'secondary_sources' => [],
            'threshold' => $threshold,
        ]);
    }

    // 4) Bygg kontekst + sources (title + url)
    $context = '';
    $sources = [];

    foreach ($top as $i => $row) {
        $n = $i + 1;
        $doc = $row['doc'];

        $title = (string) ($doc->title ?: '(uten tittel)');

        $url = $doc->permalink ?: ($doc->url ? url($doc->url) : null);

        $sources[] = [
            'n' => $n,
            'title' => $title,
            'url' => $url,
            'score' => $row['score'],
        ];

        if ($n === 1) {
            $context .= "SOURCE {$n}\n";
            $context .= "TITLE: {$title}\n";
            $context .= 'URL: '.($url ?: '')."\n";
            $context .= "CONTENT:\n".mb_substr((string) $doc->content, 0, 1500)."\n\n";
        }
    }

    // 5) Prompt som tvinger formatet du vil ha (og hindrer Wikipedia / generelt svar)
    $messages = [
        [
            'role' => 'system',
            'content' => <<<'SYS'
Du er en second-brain-assistent. Du får SOURCES med TITLE, URL og CONTENT.

REGLER:
- Svar KUN basert på CONTENT i SOURCES. Ikke bruk Wikipedia eller generell kunnskap.
- Hvis svaret ikke finnes i notatene: skriv nøyaktig: "Jeg finner ikke dette i notatene dine."
- Svar i maks 1–2 korte avsnitt. Ikke skriv essay.
- Skriv på naturlig norsk bokmål.
- Ikke skriv "SOURCE 1" i selve svaret.
- Bruk kun primærkilden (SOURCE 1) i selve svaret.

SVARFORMAT (kun én kilde):
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

    try {
        $out = $ai->chat($messages, [
            'options' => ['temperature' => 0.1],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => 'Kunne ikke hente svar fra modellen: '.$e->getMessage(),
        ], 500);
    }

    $primarySource = $sources[0] ?? null;
    $secondarySources = array_slice($sources, 1);

    return response()->json([
        'answer' => data_get($out, 'message.content'),
        'sources' => $sources,
        'primary_source' => $primarySource,
        'secondary_sources' => $secondarySources,
        'threshold' => $threshold,
    ]);

})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Load additional test UI routes
require __DIR__.'/test_ui.php';

/*
|--------------------------------------------------------------------------
| ✅ Google OAuth login (Statamic CP)
|--------------------------------------------------------------------------
| Start:  /auth/google
| Callback: /auth/google/callback
|
| This logs in an EXISTING Statamic user by email, then sends dem til /cp.
*/
Route::get('/auth/google', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();

    $email = (string) $googleUser->getEmail();

    // Default: only allow existing Statamic users
    $user = User::findByEmail($email);

    if (! $user) {
        abort(403, 'Not allowed');
    }

    Auth::login($user);

    return redirect('/cp');
});

Route::get('/auth/github', function () {
    return Socialite::driver('github')->redirect();
});

Route::get('/auth/github/callback', function () {
    $ghUser = Socialite::driver('github')->stateless()->user();

    // GitHub kan returnere null email hvis den er privat.
    $email = (string) ($ghUser->getEmail() ?? '');

    if ($email === '') {
        abort(403, 'GitHub account has no public email. Make it public or use Google login.');
    }

    $user = \Statamic\Facades\User::findByEmail($email);

    if (! $user) {
        abort(403, 'Not allowed');
    }

    \Illuminate\Support\Facades\Auth::login($user);

    return redirect('/cp');
});

Route::get('/clipper', function (Request $request, AiService $ai) {

    $text = trim((string) $request->query('text', ''));
    $url = trim((string) $request->query('url', ''));
    $title = trim((string) $request->query('title', ''));
    $tagsRaw = trim((string) $request->query('tags', ''));     // valgfritt: "ai,php"
    $summary = trim((string) $request->query('summary', ''));  // valgfritt

    if (mb_strlen($text) < 5) {
        return response('Select litt mer tekst (minst ~5 tegn).', 422);
    }

    if ($title === '') {
        $title = Str::limit($text, 60, '…');
    }

    if ($summary === '') {
        $summary = Str::limit(preg_replace('/\s+/', ' ', $text), 160, '…');
    }

    // Tags: "ai, php, statamic" -> ["ai","php","statamic"]
    $tags = collect(preg_split('/[,;]+/', $tagsRaw))
        ->map(fn ($t) => Str::slug(trim($t)))
        ->filter()
        ->values()
        ->all();

    // Content (Bard/ProseMirror blocks) – hvis "Content" er Bard
    $paragraphs = preg_split("/\R{2,}/", $text);
    $contentBlocks = collect($paragraphs)
        ->map(fn ($p) => trim($p))
        ->filter()
        ->map(fn ($p) => [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => $p],
            ],
        ])
        ->values()
        ->all();

    // Stabil "dedupe"-key (samme tekst+url = samme entry)
    $contentHash = hash('sha256', trim($text).'|'.trim($url));

    // 1) Embedding
    try {
        $embedding = $ai->embed($text);
    } catch (\Throwable $e) {
        return response('Kunne ikke lage embedding: '.$e->getMessage(), 500);
    }

    // 2) Lagre/oppdater AiDocument (for søk/rag)
    $doc = AiDocument::updateOrCreate(
        ['content_hash' => $contentHash],
        [
            'title' => $title,
            'content' => $text,
            'url' => $url,
            'collection' => 'clips',
            'source' => 'browser_clip',
            'embedding' => is_array($embedding) ? json_encode($embedding) : $embedding,
        ]
    );

    // 3) Lag/oppdater Statamic entry i collection "articles"
    $slug = Str::slug(Str::limit($title, 60, '')).'-'.substr($contentHash, 0, 8);

    $entry = Entry::query()
        ->where('collection', 'articles')
        ->where('slug', $slug)
        ->first();

    if (! $entry) {
        $entry = Entry::make()
            ->collection('articles')
            ->slug($slug);
    }

    $entry->data(array_merge($entry->data()->all(), [
        'title' => $title,
        'summary' => $summary,

        // ✅ HER: felt-handle "para" -> term slug "resources"
        'para' => ['resources'],

        // tags taxonomy
        'tags' => $tags,

        // Bard content
        'content' => $contentBlocks,

        // Bonus: lagre original url hvis du har et felt for det
        'source_url' => $url, // fjern hvis du ikke har dette feltet
    ]));

    $entry->save();

    // Feedback
    $safeTitle = e($title);
    $safeUrl = e($url);

    return response(<<<HTML
<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Saved</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:18px;}
    .card{max-width:680px;margin:0 auto;padding:16px;border:1px solid #eee;border-radius:12px;}
    .ok{font-size:18px;margin:0 0 8px;}
    .meta{color:#555;font-size:14px;word-break:break-all;}
    .small{color:#777;font-size:13px;margin-top:8px;}
  </style>
</head>
<body>
  <div class="card">
    <p class="ok">Saved ✅</p>
    <div><strong>{$safeTitle}</strong></div>
    <div class="meta">{$safeUrl}</div>
    <div class="small">Statamic entry: articles/{$slug}</div>
  </div>
  <script>setTimeout(()=>window.close(), 700)</script>
</body>
</html>
HTML)->header('Content-Type', 'text/html; charset=utf-8');

})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
