<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'gutenberg_id', 'title', 'author', 'category', 'description',
        'cover_url', 'file_url', 'language',
    ];
}
