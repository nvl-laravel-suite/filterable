<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Filterable\Definitions\FilterDefinition;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Definitions\SortDefinition;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\SortDirection;
use Nvl\Filterable\Services\EloquentFilterApplier;
use Nvl\Filterable\Tests\Fixtures\NullablePredicateRecord;
use Nvl\Filterable\Tests\Fixtures\PredicateGroup;
use Nvl\Filterable\Tests\Fixtures\PredicateRecord;
use Nvl\Filterable\Tests\Fixtures\RelatedPredicateRecord;

beforeEach(function (): void {
    Schema::dropIfExists('related_predicate_records');
    Schema::dropIfExists('nullable_predicate_records');
    Schema::dropIfExists('predicate_records');
    Schema::dropIfExists('predicate_groups');

    Schema::create('predicate_groups', function (Blueprint $table): void {
        $table->id();
        $table->string('owner');
        $table->string('name');
    });

    Schema::create('predicate_records', function (Blueprint $table): void {
        $table->id();
        $table->string('owner');
        $table->string('name');
    });

    Schema::create('nullable_predicate_records', function (Blueprint $table): void {
        $table->id();
        $table->string('owner');
        $table->string('name')->nullable();
    });

    Schema::create('related_predicate_records', function (Blueprint $table): void {
        $table->id();
        $table->string('owner');
        $table->string('name');
        $table->unsignedBigInteger('group_id');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('related_predicate_records');
    Schema::dropIfExists('nullable_predicate_records');
    Schema::dropIfExists('predicate_records');
    Schema::dropIfExists('predicate_groups');
});

test('custom OR filter preserves the caller ownership predicate', function (string $owner): void {
    $a = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'match']);
    $b = PredicateRecord::query()->create(['owner' => 'b', 'name' => 'match']);

    $schema = new FilterSchema([
        new FilterDefinition(
            'search',
            'name',
            handler: static fn (Builder $query, array $criterion): Builder => $query
                ->orWhere('name', $criterion['value']),
        ),
    ], []);
    $set = ['filters' => [
        ['alias' => 'search', 'operator' => FilterOperator::Equals, 'value' => 'match'],
    ], 'sorts' => []];
    $query = PredicateRecord::query()->where('owner', $owner);

    $ids = app(EloquentFilterApplier::class)
        ->apply($query, $schema, $set['filters'], $set['sorts'])
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$owner === 'a' ? $a->id : $b->id]);
})->with(['a', 'b']);

test('negative and null custom predicates preserve the caller ownership predicate', function (): void {
    $kept = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'keep']);
    PredicateRecord::query()->create(['owner' => 'a', 'name' => 'skip']);
    PredicateRecord::query()->create(['owner' => 'b', 'name' => 'keep']);
    $null = NullablePredicateRecord::query()->create(['owner' => 'a', 'name' => null]);
    NullablePredicateRecord::query()->create(['owner' => 'b', 'name' => null]);

    $schema = new FilterSchema([
        new FilterDefinition(
            'not_name',
            'name',
            operators: [FilterOperator::NotEquals],
            handler: static fn (Builder $query, array $criterion): Builder => $query
                ->orWhere('name', '!=', $criterion['value']),
        ),
        new FilterDefinition(
            'missing_name',
            'name',
            operators: [FilterOperator::IsNull],
            nullable: true,
            handler: static fn (Builder $query, array $criterion): Builder => $query
                ->orWhereNull('name'),
        ),
    ], []);
    $applier = app(EloquentFilterApplier::class);

    $negativeIds = $applier->apply(PredicateRecord::query()->where('owner', 'a'), $schema, [['alias' => 'not_name', 'operator' => FilterOperator::NotEquals, 'value' => 'skip']], [])->pluck('id')->all();
    $nullIds = $applier->apply(NullablePredicateRecord::query()->where('owner', 'a'), $schema, [['alias' => 'missing_name', 'operator' => FilterOperator::IsNull, 'value' => null]], [])->pluck('id')->all();

    expect($negativeIds)->toBe([$kept->id])
        ->and($nullIds)->toBe([$null->id]);
});

test('multiple custom predicates retain sorting pagination and count semantics', function (): void {
    PredicateRecord::query()->create(['owner' => 'a', 'name' => 'alpha']);
    $beta = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'beta']);
    $bravo = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'bravo']);
    PredicateRecord::query()->create(['owner' => 'b', 'name' => 'beta']);

    $schema = new FilterSchema(
        filters: [
            new FilterDefinition(
                'starts_with',
                'name',
                handler: static fn (Builder $query, array $criterion): Builder => $query
                    ->orWhere('name', 'like', $criterion['value'].'%'),
            ),
            new FilterDefinition(
                'not_name',
                'name',
                operators: [FilterOperator::NotEquals],
                handler: static fn (Builder $query, array $criterion): Builder => $query
                    ->orWhere('name', '!=', $criterion['value']),
            ),
        ],
        sorts: [new SortDefinition('name', 'name')],
    );
    $set = ['filters' => [
        ['alias' => 'starts_with', 'operator' => FilterOperator::Equals, 'value' => 'b'],
        ['alias' => 'not_name', 'operator' => FilterOperator::NotEquals, 'value' => 'alpha'],
    ], 'sorts' => [['alias' => 'name', 'direction' => SortDirection::Desc->value]]];

    $query = app(EloquentFilterApplier::class)->apply(PredicateRecord::query()->where('owner', 'a'), $schema, $set['filters'], $set['sorts']);
    $count = (clone $query)->count();
    $page = (clone $query)->paginate(perPage: 1, page: 1);

    expect($count)->toBe(2)
        ->and($page->total())->toBe(2)
        ->and($page->items())->toHaveCount(1)
        ->and($page->items()[0]->is($bravo))->toBeTrue()
        ->and($page->items()[0]->is($beta))->toBeFalse();
});

test('relation predicates retain caller and related ownership constraints', function (): void {
    $groupA = PredicateGroup::query()->create(['owner' => 'a', 'name' => 'shared']);
    $groupB = PredicateGroup::query()->create(['owner' => 'b', 'name' => 'shared']);
    $valid = RelatedPredicateRecord::query()->create([
        'owner' => 'a',
        'name' => 'valid',
        'group_id' => $groupA->id,
    ]);
    RelatedPredicateRecord::query()->create([
        'owner' => 'a',
        'name' => 'corrupt',
        'group_id' => $groupB->id,
    ]);
    RelatedPredicateRecord::query()->create([
        'owner' => 'b',
        'name' => 'other',
        'group_id' => $groupB->id,
    ]);

    $schema = new FilterSchema([
        new FilterDefinition('group', 'group.name'),
        new FilterDefinition(
            'group_search',
            'group.name',
            handler: static fn (Builder $query, array $criterion): Builder => $query
                ->orWhereHas(
                    'group',
                    static fn (Builder $related): Builder => $related->where('name', $criterion['value']),
                ),
        ),
    ], []);
    $applier = app(EloquentFilterApplier::class);

    $declaredRelationIds = $applier->apply(RelatedPredicateRecord::query()->where('owner', 'a'), $schema, [['alias' => 'group', 'operator' => FilterOperator::Equals, 'value' => 'shared']], [])->pluck('id')->all();
    $customRelationIds = $applier->apply(RelatedPredicateRecord::query()->where('owner', 'a'), $schema, [['alias' => 'group_search', 'operator' => FilterOperator::Equals, 'value' => 'shared']], [])->pluck('id')->all();

    expect($declaredRelationIds)->toBe([$valid->id])
        ->and($customRelationIds)->toBe([$valid->id]);
});

test('empty sets and consumers without ownership predicates retain normal behavior', function (): void {
    $a = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'match']);
    $b = PredicateRecord::query()->create(['owner' => 'b', 'name' => 'match']);
    $other = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'other']);

    $schema = new FilterSchema([
        new FilterDefinition(
            'search',
            'name',
            handler: static fn (Builder $query, array $criterion): Builder => $query
                ->orWhere('name', $criterion['value']),
        ),
    ], []);
    $applier = app(EloquentFilterApplier::class);

    $emptyIds = $applier->apply(PredicateRecord::query()->where('owner', 'a'), $schema, [], [])->orderBy('id')->pluck('id')->all();
    $unscopedIds = $applier->apply(PredicateRecord::query(), $schema, [['alias' => 'search', 'operator' => FilterOperator::Equals, 'value' => 'match']], [])->orderBy('id')->pluck('id')->all();

    expect($emptyIds)->toBe([$a->id, $other->id])
        ->and($unscopedIds)->toBe([$a->id, $b->id]);
});

test('native custom OR filters cannot escape a grouped caller scope', function (): void {
    $kept = PredicateRecord::query()->create(['owner' => 'a', 'name' => 'match']);
    PredicateRecord::query()->create(['owner' => 'b', 'name' => 'match']);
    PredicateRecord::query()->create(['owner' => 'a', 'name' => 'other']);
    $schema = new FilterSchema([
        new FilterDefinition('search', 'name', handler: static fn (Builder $query, array $criterion): Builder => $query
            ->where('name', 'never')->orWhere('name', $criterion['value'])),
    ], []);
    $query = PredicateRecord::query()->where(fn (Builder $scope): Builder => $scope
        ->where('owner', 'a')->orWhere('owner', 'c'));

    expect(app(EloquentFilterApplier::class)->apply($query, $schema, [
        ['alias' => 'search', 'operator' => 'equals', 'value' => 'match'],
    ])->pluck('id')->all())->toBe([$kept->id]);
});
