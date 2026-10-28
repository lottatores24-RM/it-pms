<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Enums\RiskRating;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use Database\Factories\RiskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'title',
    'description',
    'category',
    'probability',
    'impact',
    'response_plan',
    'status',
    'owner_id',
])]
class Risk extends Model
{
    /** @use HasFactory<RiskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'owner_id' => 'integer',
            'probability' => RiskRating::class,
            'impact' => RiskRating::class,
            'risk_score' => 'integer',
            'risk_level' => RiskLevel::class,
            'response_plan' => RiskResponse::class,
            'status' => RiskStatus::class,
        ];
    }

    /**
     * Keep risk_score and risk_level derived from probability × impact (FR-013).
     */
    protected static function booted(): void
    {
        static::saving(function (Risk $risk): void {
            $risk->risk_score = $risk->probability->weight() * $risk->impact->weight();
            $risk->risk_level = RiskLevel::fromScore($risk->risk_score);
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'related_risk_id');
    }
}
