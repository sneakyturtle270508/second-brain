<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiDocument extends Model
{
    protected $table = 'ai_documents';

    protected $fillable = [
        'source',
        'source_id',
        'collection',
        'slug',
        'title',
        'content',
        'content_hash',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];
}
