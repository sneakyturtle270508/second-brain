<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source');            // f.eks. 'statamic_entry'
            $table->string('source_id')->index(); // entry id
            $table->string('collection')->nullable();
            $table->string('slug')->nullable();
            $table->string('title')->nullable();

            $table->longText('content');          // teksten vi embedder
            $table->string('content_hash')->index(); // for å skippe re-embed hvis uendret
            $table->json('embedding');            // lagrer vektor som JSON

            $table->timestamps();

            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_documents');
    }
};
