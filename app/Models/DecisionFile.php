<?php

namespace App\Models;

use App\Support\FileSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionFile extends Model
{
    protected $guarded = ['id'];

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function kindLabel(): string
    {
        return Decision::fileKindLabel($this->kind);
    }

    public function isReleasable(): bool
    {
        return in_array($this->kind, Decision::releasableKinds(), true);
    }

    public function humanSize(): string
    {
        return FileSize::format($this->size);
    }
}
