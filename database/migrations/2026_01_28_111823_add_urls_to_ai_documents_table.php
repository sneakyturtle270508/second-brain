<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->string('url')->nullable()->after('slug');        // relative /knowledge/ost-blehh
            $table->string('permalink')->nullable()->after('url');   // full http://127.0.0.1:8000/...
        });
    }

    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropColumn(['url', 'permalink']);
        });
    }
};
