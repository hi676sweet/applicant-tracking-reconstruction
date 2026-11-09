<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOpening extends Model
{
    protected $guarded = [];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
