<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryItem extends Model
{
    protected $fillable = ['user_id', 'book_id', 'is_favorite'];

    protected $casts = ['is_favorite' => 'boolean'];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
