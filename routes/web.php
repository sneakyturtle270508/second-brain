<?php

use App\Models\AiDocument;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

// ✅ Google login imports
use Laravel\Socialite\Facades\Socialite;
use Statamic\Facades\User;
use Illuminate\Support\Facades\Auth;

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
            'error' => 'Kunne ikke lage embedding: ' . $e->getMessage(),
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

        if (!is_array($docEmbedding) || count($docEmbedding) === 0) {
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

    if (!$primary) {
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
            $context .= "URL: " . ($url ?: '') . "\n";
            $context .= "CONTENT:\n" . mb_substr((string) $doc->content, 0, 1500) . "\n\n";
        }
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
            'error' => 'Kunne ikke hente svar fra modellen: ' . $e->getMessage(),
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

/*
|--------------------------------------------------------------------------
| ✅ Google OAuth login (Statamic CP)
|--------------------------------------------------------------------------
| Start:  /auth/google
| Callback: /auth/google/callback
|
| This logs in an EXISTING Statamic user by email, then sends them to /cp.
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
    // Da prøver vi å hente den fra user-objektet, ellers stopper vi.
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
