<?php

declare(strict_types=1);

namespace Nvl\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Minimal related model used to verify allowlisted relation filtering.
 */
final class FilterableGroup extends Model
{
    public $timestamps = false;

    protected $table = 'filterable_groups';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Return the group's records for to-many relation path filters.
     *
     * @return HasMany<FilterableRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(FilterableRecord::class, 'group_id');
    }
}
