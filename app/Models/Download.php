<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    protected $fillable = ['user_id', 'guest_session_id', 'book_id', 'ip_address'];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
