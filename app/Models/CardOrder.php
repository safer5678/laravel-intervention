<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardOrder extends Model
{
  protected $fillable = [
        'order_number',
        'user_id',
        'card_name',
        'width_mm',
        'height_mm',
        'background_color',
        'background_image',
        'card_data',
        'preview_url',
        'print_file_url',
        'status',
        'error_message',
    ];

    protected $casts = [
        'card_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->order_number) {
                $model->order_number = 'CARD-' . time() . '-' . strtoupper(substr(uniqid(), -6));
            }
        });
    }
}
