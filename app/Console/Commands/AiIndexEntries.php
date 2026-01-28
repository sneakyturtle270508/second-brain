<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiService;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;

class AiIndexEntries extends Command
{
    protected $signature = 'ai:index {collection?}';
    protected $description = 'Index Statamic entries into ai_documents with embeddings';

   public function handle(AiService $ai)
{
    $collection = $this->argument('collection');

    $entries = $collection
        ? Entry::query()->where('collection', $collection)->get()
        : Entry::all();

    $this->info('Indexing '.$entries->count().' entries…');

    foreach ($entries as $entry) {
        $sourceType = 'statamic_entry';
        $sourceId   = (string) $entry->id();

        $title = (string) ($entry->get('title') ?? '');
        $text  = $this->extractText($entry->data()->all());

        if (trim($text) === '') {
            $this->warn("Skipping empty entry: {$sourceId}");
            continue;
        }

        $hash = hash('sha256', $text);

        // (Valgfritt) hvis du vil spare tid: ikke re-embed hvis innholdet er uendret
        $existing = DB::table('ai_documents')
            ->where('source', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing && ($existing->content_hash ?? null) === $hash) {
            $this->line("↺ Skipped (unchanged) {$title} ({$sourceId})");
            continue;
        }

        $embedding = $ai->embed($text);

        DB::table('ai_documents')->updateOrInsert(
            [
                'source'    => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'collection'   => $entry->collectionHandle(),
                'slug'         => $entry->slug(),
                'title'        => $title,
                'content'      => $text,
                'content_hash' => $hash,
                'embedding'    => json_encode($embedding),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        $this->line("✓ Indexed {$title} ({$sourceId})");
    }

    $this->info('Done.');
    return self::SUCCESS;
}


    private function extractText(array $data): string
    {
        $out = [];

        foreach ($data as $value) {
            if ($value instanceof Value) {
                $value = $value->value();
            }

            if (is_string($value)) {
                $out[] = strip_tags($value);
            } elseif (is_array($value)) {
                $out[] = $this->extractText($value);
            }
        }

        return implode("\n", array_filter($out));
    }
}
