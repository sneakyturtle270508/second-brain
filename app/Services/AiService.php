<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    private string $baseUrl;
    private string $chatModel;
    private string $embedModel;

    public function chat(array $messages, array $options = []): array
    {
        $payload = array_merge([
            'model' => $this->chatModel,
            'messages' => $messages,
            'stream' => false,
        ], $options);

        $res = Http::timeout(60)->post("{$this->baseUrl}/api/chat", $payload);

        if (!$res->ok()) {
            throw new \RuntimeException("Ollama chat failed ({$res->status()}): " . $res->body());
        }

        return $res->json();
    }

   public function __construct()
{
    $this->baseUrl = rtrim(env('OLLAMA_URL', 'http://127.0.0.1:11434'), '/');
    $this->chatModel = env('OLLAMA_CHAT_MODEL', 'llama3.2:3b');
    $this->embedModel = env('OLLAMA_EMBED_MODEL', 'nomic-embed-text:latest');
}

public function embed(string $text): array
{
    $text = trim($text);

    if ($text === '') {
        return [];
    }

    // Rask guard: unngå ekstremt lange inputs (chars != tokens, men hjelper)
    if (mb_strlen($text) > 20000) {
        $text = mb_substr($text, 0, 20000);
    }

    // 1) Først: prøv direkte (raskest)
    $res = Http::timeout(120)->post("{$this->baseUrl}/api/embeddings", [
        'model' => $this->embedModel,
        'prompt' => $text,
    ]);

    if ($res->ok()) {
        $embedding = $res->json('embedding');
        if (is_array($embedding) && count($embedding) > 0) {
            return $embedding;
        }
        throw new \RuntimeException("Ollama embeddings response missing/empty 'embedding'. Raw: " . $res->body());
    }

    // 2) Hvis det IKKE er context-length-feil → kast
    $body = $res->body();
    if (!str_contains($body, 'exceeds the context length')) {
        throw new \RuntimeException("Ollama embeddings failed ({$res->status()}): " . $body);
    }

    // 3) Fallback: chunk + average
    $chunks = $this->chunkText($text, 2000); // juster ned til 1500 hvis fortsatt feil
    $vectors = [];

    foreach ($chunks as $chunk) {
        $r = Http::timeout(120)->post("{$this->baseUrl}/api/embeddings", [
            'model' => $this->embedModel,
            'prompt' => $chunk,
        ]);

        if (!$r->ok()) {
            throw new \RuntimeException("Ollama embeddings failed ({$r->status()}): " . $r->body());
        }

        $vec = $r->json('embedding') ?? [];
        if (is_array($vec) && count($vec) > 0) {
            $vectors[] = $vec;
        }
    }

    $avg = $this->averageVectors($vectors);

    if (count($avg) === 0) {
        throw new \RuntimeException("Chunked embedding failed: got 0 vectors.");
    }

    return $avg;
}

private function chunkText(string $text, int $maxChars): array
{
    // Split på “naturlige” grenser først (tomlinjer)
    $parts = preg_split("/\n{2,}/", $text) ?: [$text];

    $chunks = [];
    $buf = '';

    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;

        // Hvis en del er større enn max -> hard-splitt
        while (mb_strlen($p) > $maxChars) {
            $chunks[] = mb_substr($p, 0, $maxChars);
            $p = mb_substr($p, $maxChars);
        }

        if ($buf === '') {
            $buf = $p;
            continue;
        }

        if (mb_strlen($buf) + 2 + mb_strlen($p) <= $maxChars) {
            $buf .= "\n\n" . $p;
        } else {
            $chunks[] = $buf;
            $buf = $p;
        }
    }

    if ($buf !== '') $chunks[] = $buf;

    return $chunks;
}

private function averageVectors(array $vectors): array
{
    if (count($vectors) === 0) return [];

    $dim = count($vectors[0]);
    $sum = array_fill(0, $dim, 0.0);
    $n = 0;

    foreach ($vectors as $v) {
        if (!is_array($v) || count($v) !== $dim) continue;

        for ($i = 0; $i < $dim; $i++) {
            $sum[$i] += (float) $v[$i];
        }
        $n++;
    }

    if ($n === 0) return [];

    for ($i = 0; $i < $dim; $i++) {
        $sum[$i] /= $n;
    }

    return $sum;
}


}
