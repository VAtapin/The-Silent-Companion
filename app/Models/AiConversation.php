<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $guarded = [];

    public function messages()
    {
        return $this->hasMany(AiMessage::class);
    }

    public function requests()
    {
        return $this->hasMany(AiRequest::class);
    }
}
