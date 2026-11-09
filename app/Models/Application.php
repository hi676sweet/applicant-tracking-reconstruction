<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    protected $guarded = [];

    public const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

    public const TRANSITIONS = ['applied' => ['screening', 'rejected'], 'screening' => ['interview', 'rejected'], 'interview' => ['offer', 'rejected'], 'offer' => ['hired', 'rejected'], 'hired' => [], 'rejected' => []];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
