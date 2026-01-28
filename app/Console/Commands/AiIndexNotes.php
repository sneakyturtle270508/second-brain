<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiService;
use App\Models\AiDocument;
use Statamic\Facades\Entry;

class AiIndexNotes extends Command
{
    protected $signature = 'ai:index {collection=articles} {--force}';
    protected $description = 'Index Statamic entries into ai_documents with embeddings';

    public function handle(AiService $ai): int
    {
        $collection = (string) $this->argument('collection');
        $force = (bool) $this->option('force');

        $entries = Entry::query()
            ->where('collection', $collection)
            ->get();

        $this->info("Found {$entries->count()} entries in collection '{$collection}'.");

        $countNew = 0;
        $countSkip = 0;
        $countUpd = 0;

        foreach ($entries as $entry) {
            $id = (string) $entry->id();
            $title = (string) ($entry->get('title') ?? $entry->slug());
            $slug = (string) $entry->slug();

            // Prøv å hente “content” fra typiske felt. Tilpass hvis blueprinten din heter noe annet.
            $content =
                (string) ($entry->get('content') ?? '') .
                "\n" .
                (string) ($entry->get('text') ?? '') .
                "\n" .
                (string) ($entry->get('body') ?? '');

            $content = trim(preg_replace("/\n{3,}/", "\n\n", $content));

            if ($content === '') {
                $this->warn("Skip {$slug} (no content/text/body)");
                $countSkip++;
                continue;
            }

            $hash = hash('sha256', $title . "\n" . $content);

            $doc = AiDocument::where('source', 'statamic_entry')
                ->where('source_id', $id)
                ->first();

            if ($doc && !$force && $doc->content_hash === $hash) {
                $countSkip++;
                continue;
            }

            // embed
            $embedding = $ai->embed($content);

            if (!$doc) {
                $doc = new AiDocument();
                $doc->source = 'statamic_entry';
                $doc->source_id = $id;
                $countNew++;
            } else {
                $countUpd++;
            }

            $doc->collection = $collection;
            $doc->slug = $slug;
            $doc->title = $title;
            $doc->content = $content;
            $doc->content_hash = $hash;
            $doc->embedding = $embedding; // cast til array i modellen gjør dette til JSON i DB
            $doc->save();
        }

        $this->info("Done. new={$countNew}, updated={$countUpd}, skipped={$countSkip}");
        return self::SUCCESS;
    }
}
