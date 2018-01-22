<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Heart extends Model
{
    protected $fillable = [
        'user_id', 'heart_user_id', 'book_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function scopeHaveHeart($query, $userId, $bookId)
    {
        return $query->where('heart_user_id', $userId)->where('book_id', $bookId)->exists();
    }
}