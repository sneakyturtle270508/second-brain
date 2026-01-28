<?php

namespace App\Console\Commands;

use App\Services\AiService;
use Illuminate\Console\Command;
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
            $sourceId = (string) $entry->id();

            $title = (string) ($entry->get('title') ?? '');
            $text = $this->extractText($entry->data()->all());
            $tags = $this->normalizeTags($entry->get('tags'));

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
            $slug = (string) $entry->slug();
            $url = $entry->url() ?: null;
            $permalink = $url ? rtrim(config('app.url'), '/').$url : null;

            DB::table('ai_documents')->updateOrInsert(
                [
                    'source' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'collection' => $entry->collectionHandle(),
                    'slug' => $slug,
                    'title' => $title,
                    'tags' => $tags ? json_encode($tags) : null,
                    'content' => $text,
                    'content_hash' => $hash,
                    'embedding' => json_encode($embedding),
                    'url' => $url,
                    'permalink' => $permalink,
                    'updated_at' => now(),
                    'created_at' => now(),
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

    private function normalizeTags($value): array
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if (is_string($value)) {
            return [$value];
        }

        if (is_array($value)) {
            $tags = [];
            foreach ($value as $item) {
                if ($item instanceof Value) {
                    $item = $item->value();
                }

                if (is_string($item)) {
                    $tags[] = $item;
                }
            }

            return array_values(array_unique(array_filter($tags)));
        }

        return [];
    }
}
