<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiController extends Controller
{
    public function ping()
    {
        return response()->json(['ok' => true]);
    }

    public function search(Request $request)
    {
        $q = (string) $request->input('q', '');
        $limit = (int) $request->input('limit', 5);

        if (trim($q) === '') {
            return response()->json(['ok' => false, 'error' => 'Missing q'], 422);
        }

        $queryVec = app(AiService::class)->embed($q);

        // Enkel løsning: last alt og rank i PHP (ok for små/middels datasett)
        $docs = DB::table('ai_documents')->select('source', 'title', 'content', 'embedding')->get();

        $scored = [];
        foreach ($docs as $doc) {
            $vec = json_decode($doc->embedding, true) ?: [];
            $score = $this->cosine($queryVec, $vec);

            $scored[] = [
                'source' => $doc->source,
                'title' => $doc->title,
                'score' => $score,
                'snippet' => mb_substr($doc->content, 0, 300),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, max(1, $limit));

        return response()->json([
            'ok' => true,
            'q' => $q,
            'results' => $top,
        ]);
    }

    public function ask(Request $request, AiService $ai)
    {
        $q = (string) $request->input('q', '');
        $k = (int) $request->input('k', 5);

        if (trim($q) === '') {
            return response()->json(['ok' => false, 'error' => 'Missing q'], 422);
        }

        // Rebruk search-logikken for å hente topp-kontekst
        $queryVec = $ai->embed($q);
        $docs = DB::table('ai_documents')->select('source', 'title', 'content', 'embedding')->get();

        $scored = [];
        foreach ($docs as $doc) {
            $vec = json_decode($doc->embedding, true) ?: [];
            $score = $this->cosine($queryVec, $vec);

            $scored[] = [
                'source' => $doc->source,
                'title' => $doc->title,
                'score' => $score,
                'content' => $doc->content,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, max(1, $k));

        $context = "";
        foreach ($top as $i => $d) {
            $n = $i + 1;
            $context .= "SOURCE {$n}: {$d['title']} ({$d['source']})\n";
            $context .= $this->clip($d['content'], 1600) . "\n\n";
        }

        $messages = [
            [
                'role' => 'system',
                'content' => "Du er en second-brain assistent. Svar kort, presist, og baser deg på kildene. Hvis kildene ikke dekker spørsmålet, si det og foreslå hva som mangler. Referer til kilder som (SOURCE 1), (SOURCE 2) osv."
            ],
            [
                'role' => 'user',
                'content' => "SPØRSMÅL:\n{$q}\n\nKILDER:\n{$context}"
            ],
        ];

        $resp = $ai->chat($messages);

        return response()->json([
            'ok' => true,
            'q' => $q,
            'answer' => data_get($resp, 'message.content'),
            'model' => data_get($resp, 'model'),
            'sources' => array_map(fn($d) => [
                'source' => $d['source'],
                'title' => $d['title'],
                'score' => $d['score'],
            ], $top),
        ]);
    }

    private function cosine(array $a, array $b): float
    {
        if (count($a) === 0 || count($b) === 0 || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        $len = count($a);
        for ($i = 0; $i < $len; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }

        $den = sqrt($na) * sqrt($nb);
        return $den > 0 ? ($dot / $den) : 0.0;
    }

    private function clip(string $text, int $max): string
    {
        $text = trim($text);
        return mb_strlen($text) > $max ? (mb_substr($text, 0, $max) . "…") : $text;
    }
}
