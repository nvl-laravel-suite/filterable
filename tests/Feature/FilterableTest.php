<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Nvl\Filterable\Definitions\FilterDefinition;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Definitions\SortDefinition;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\FilterValueType;
use Nvl\Filterable\Enums\SortDirection;
use Nvl\Filterable\Exceptions\FilterableException;
use Nvl\Filterable\Input\RequestParamsAdapter;
use Nvl\Filterable\Services\ColumnStorageTypes;
use Nvl\Filterable\Services\EloquentFilterApplier;
use Nvl\Filterable\Services\FilterCriterionNormalizer;
use Nvl\Filterable\Tests\Fixtures\ConsumerQueryFilters;
use Nvl\Filterable\Tests\Fixtures\FilterableGroup;
use Nvl\Filterable\Tests\Fixtures\FilterableRecord;
use Nvl\Support\Contracts\TextMatcher;

beforeEach(function (): void {
    Schema::dropIfExists('filterable_microsecond_records');
    Schema::dropIfExists('filterable_records');
    Schema::dropIfExists('filterable_groups');

    Schema::create('filterable_groups', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    Schema::create('filterable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status');
        $table->integer('price');
        $table->decimal('amount', 10, 2);
        $table->boolean('is_active');
        $table->string('note')->nullable();
        $table->date('created_at');
        $table->dateTime('occurred_at', 6);
        $table->unsignedBigInteger('group_id');
    });

    FilterableGroup::query()->insert([
        ['id' => 1, 'name' => 'Editorial'],
        ['id' => 2, 'name' => 'Sales'],
    ]);

    FilterableRecord::query()->insert([
        [
            'id' => 1,
            'name' => 'Alpha',
            'status' => 'active',
            'price' => 10,
            'amount' => '10.50',
            'is_active' => true,
            'note' => '100% ready',
            'created_at' => '2026-01-01',
            'occurred_at' => '2026-01-01 10:00:00',
            'group_id' => 1,
        ],
        [
            'id' => 2,
            'name' => 'Beta',
            'status' => 'draft',
            'price' => 20,
            'amount' => '20.00',
            'is_active' => false,
            'note' => null,
            'created_at' => '2026-01-02',
            'occurred_at' => '2026-01-02 12:00:00',
            'group_id' => 2,
        ],
        [
            'id' => 3,
            'name' => 'Gamma',
            'status' => 'active',
            'price' => 30,
            'amount' => '30.75',
            'is_active' => true,
            'note' => 'under_score',
            'created_at' => '2026-01-03',
            'occurred_at' => '2026-01-03 14:00:00',
            'group_id' => 1,
        ],
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('filterable_microsecond_records');
    Schema::dropIfExists('filterable_records');
    Schema::dropIfExists('filterable_groups');
});

it('parses flat aliases operators range lists and both sort formats with the native allowlist', function (): void {
    $schema = new FilterSchema([
        new FilterDefinition('price', 'price', FilterValueType::Integer, [FilterOperator::Between]),
        new FilterDefinition('state', 'status', operators: [FilterOperator::In]),
        new FilterDefinition('label', 'name', operators: [FilterOperator::NotContains]),
    ], [new SortDefinition('item_name', 'name')]);
    $adapter = app(RequestParamsAdapter::class);
    $applier = app(EloquentFilterApplier::class);
    $params = ['price' => '10,30', 'price.operator' => 'between', 'state' => '"active","draft"', 'state_operator' => 'in'];
    $nativeFilters = [
        ['alias' => 'price', 'operator' => 'between', 'value' => [10, 30]],
        ['alias' => 'state', 'operator' => 'in', 'value' => ['active', 'draft']],
    ];
    $native = $applier->apply(FilterableRecord::query(), $schema, $nativeFilters, [['alias' => 'item_name', 'direction' => 'desc']])->pluck('name')->all();

    foreach ([['itemName' => 'DESC'], 'itemName:desc'] as $sort) {
        $input = $adapter->parse([...$params, 'sort' => $sort, 'page' => 2], $schema, ignoreUnknown: true);
        expect($applier->apply(FilterableRecord::query(), $schema, $input->filters, $input->sorts)->pluck('name')->all())->toBe($native);
    }
    expect($native)->toBe(['Gamma', 'Beta', 'Alpha'])
        ->and(fn () => $adapter->parse([...$params, 'page' => 2], $schema))->toThrow(FilterableException::class, 'Unknown')
        ->and(fn () => $adapter->parse(['price' => '10,30', 'price_operator' => 'in'], $schema, ignoreUnknown: true))->toThrow(FilterableException::class, 'not allowed')
        ->and($adapter->parse(['label' => 'Alpha', 'label_operator' => 'not'], $schema)->filters[0]['operator'])->toBe(FilterOperator::NotContains);
});

it('compares calendar dates against timestamp columns without relying on midnight', function (): void {
    $schema = new FilterSchema([
        new FilterDefinition('day', 'occurred_at', FilterValueType::Date, [FilterOperator::Equals, FilterOperator::Before, FilterOperator::After, FilterOperator::Between], calendarDate: true),
    ], [new SortDefinition('name', 'name')], defaultSorts: ['name']);
    $names = static fn (FilterOperator $operator, string|array $value): array => app(EloquentFilterApplier::class)
        ->apply(FilterableRecord::query(), $schema, [['alias' => 'day', 'operator' => $operator, 'value' => $value]])->pluck('name')->all();

    expect($names(FilterOperator::Equals, '2026-01-02'))->toBe(['Beta'])
        ->and($names(FilterOperator::Before, '2026-01-02'))->toBe(['Alpha'])
        ->and($names(FilterOperator::After, '2026-01-02'))->toBe(['Gamma'])
        ->and($names(FilterOperator::Between, ['2026-01-01', '2026-01-02']))->toBe(['Alpha', 'Beta']);
});

it('matches Cyrillic and Greek folded text with declared wildcard policies', function (): void {
    FilterableRecord::query()->whereKey(1)->update(['note' => 'СНИМКА %_! ΌΣ']);
    FilterableRecord::query()->whereKey(2)->update(['note' => 'СНИМКА abc! ΌΣ']);
    $schema = new FilterSchema([
        new FilterDefinition('literal', 'note', operators: [FilterOperator::Contains, FilterOperator::NotContains]),
        new FilterDefinition('pattern', 'note', operators: [FilterOperator::Contains], allowWildcards: true),
    ], [new SortDefinition('name', 'name')], defaultSorts: ['name']);
    $names = static fn (string $alias, string $value, FilterOperator $operator = FilterOperator::Contains): array => app(EloquentFilterApplier::class)
        ->apply(FilterableRecord::query(), $schema, [['alias' => $alias, 'operator' => $operator, 'value' => $value]])->pluck('name')->all();

    expect($names('literal', 'снимка %_! ός'))->toBe(['Alpha'])
        ->and($names('literal', 'снимка %_! ός', FilterOperator::NotContains))->toBe(['Beta', 'Gamma'])
        ->and($names('pattern', 'снимка %_! ός'))->toBe(['Alpha', 'Beta']);
});

it('lets the host substitute the Core matcher for Filterable predicates', function (): void {
    $matcher = Mockery::mock(TextMatcher::class);
    $matcher->shouldReceive('contains')->once()->with(Mockery::type(QueryBuilder::class), 'note', 'example', true, true)
        ->andReturnUsing(static fn (QueryBuilder $query): QueryBuilder => $query->where('id', 3));
    app()->instance(TextMatcher::class, $matcher);
    $schema = new FilterSchema([new FilterDefinition('note', 'note', operators: [FilterOperator::NotContains], allowWildcards: true)], []);
    $ids = app(EloquentFilterApplier::class)->apply(FilterableRecord::query(), $schema, [['alias' => 'note', 'operator' => 'not_contains', 'value' => 'example']])->pluck('id')->all();

    expect($ids)->toBe([3]);
});

it('applies typed allowlisted filters and enum-backed sorts', function (): void {
    $set = ['filters' => [
        ['alias' => 'status', 'operator' => FilterOperator::Equals, 'value' => 'active'],
        ['alias' => 'price', 'operator' => FilterOperator::Gte, 'value' => '15'],
    ], 'sorts' => [['alias' => 'price', 'direction' => SortDirection::Desc->value]]];

    $names = FilterableRecord::query()
        ->applyFilters($set['filters'], $set['sorts'])
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Gamma']);
});

it('applies explicit negative equality and set operators', function (): void {
    $notDraft = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'status', 'operator' => FilterOperator::Equals, 'value' => 'active'],
            ['alias' => 'price', 'operator' => FilterOperator::NotIn, 'value' => ['10', '20']],
        ])
        ->pluck('name')
        ->all();

    $notAlpha = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'name', 'operator' => FilterOperator::NotEquals, 'value' => 'Alpha'],
        ])
        ->pluck('name')
        ->all();

    $included = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'price', 'operator' => FilterOperator::In, 'value' => ['10', '30']],
        ])
        ->pluck('name')
        ->all();

    expect($notDraft)->toBe(['Gamma'])
        ->and($notAlpha)->toBe(['Beta', 'Gamma'])
        ->and($included)->toBe(['Alpha', 'Gamma']);
});

it('treats contains wildcards as literal characters', function (): void {
    $percent = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'note', 'operator' => FilterOperator::Contains, 'value' => '%'],
        ])
        ->pluck('name')
        ->all();

    $underscore = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'note', 'operator' => FilterOperator::Contains, 'value' => '_'],
        ])
        ->pluck('name')
        ->all();

    $notPercent = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'name', 'operator' => FilterOperator::NotContains, 'value' => 'Alpha'],
        ])
        ->pluck('name')
        ->all();

    expect($percent)->toBe(['Alpha'])
        ->and($underscore)->toBe(['Gamma'])
        ->and($notPercent)->toBe(['Beta', 'Gamma']);
});

it('normalizes strict scalar values before applying queries and custom handlers', function (): void {
    $booleans = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'active', 'operator' => FilterOperator::Equals, 'value' => 'false'],
        ])
        ->pluck('name')
        ->all();

    $custom = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'active_handler', 'operator' => FilterOperator::Equals, 'value' => '1'],
        ])
        ->pluck('name')
        ->all();

    $decimal = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'amount', 'operator' => FilterOperator::Gte, 'value' => '20.00'],
        ])
        ->pluck('name')
        ->all();

    expect($booleans)->toBe(['Beta'])
        ->and($custom)->toBe(['Alpha', 'Gamma'])
        ->and($decimal)->toBe(['Beta', 'Gamma']);
});

it('rejects permissive boolean, numeric, empty, and oversized values', function (
    string $alias,
    mixed $value,
    string $message,
): void {
    expect(fn () => FilterableRecord::query()->applyFilters([
        ['alias' => $alias, 'operator' => FilterOperator::Equals, 'value' => $value],
    ])->get())->toThrow(FilterableException::class, $message);
})->with([
    'boolean word' => ['active', 'off', 'Boolean filters accept only'],
    'boolean null' => ['active', null, 'Boolean filters accept only'],
    'scientific decimal' => ['amount', '1e2', 'Decimal filter value is invalid'],
    'leading-zero integer' => ['price', '01', 'Integer filter value is invalid'],
    'empty string' => ['name', '   ', 'cannot be empty'],
    'oversized string' => ['name', str_repeat('a', 33), 'maximum string length'],
]);

it('normalizes exact dates, ranges, and timezone-aware instants', function (): void {
    $range = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'created', 'operator' => FilterOperator::Between, 'value' => ['2026-01-02', '2026-01-03']],
        ])
        ->pluck('name')
        ->all();

    $instant = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'occurred', 'operator' => FilterOperator::Equals, 'value' => '2026-01-01T12:00:00+02:00'],
        ])
        ->pluck('name')
        ->all();

    expect($range)->toBe(['Beta', 'Gamma'])
        ->and($instant)->toBe(['Alpha']);
});

it('preserves fractional date-time query bindings and caller predicates', function (FilterOperator $operator, mixed $value, array $expectedIds, array $expectedBindings): void {
    FilterableRecord::query()->whereKey(1)->update(['occurred_at' => '2026-01-01 10:00:00.123456']);
    $schema = new FilterSchema([
        new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [$operator]),
    ], []);
    $query = app(EloquentFilterApplier::class)->apply(
        FilterableRecord::query()->where('group_id', 1)->orderBy('id'),
        $schema,
        [['alias' => 'occurred', 'operator' => $operator, 'value' => $value]],
    );

    if ($query->getConnection()->getDriverName() === 'pgsql') {
        $expectedBindings = array_map(static fn (string $binding): string => $binding.'+00:00', $expectedBindings);
    }

    expect($query->pluck('id')->all())->toBe($expectedIds)
        ->and($query->getConnection()->prepareBindings($query->getBindings()))->toBe([1, ...$expectedBindings]);
})->with([
    'equality with timezone conversion' => [FilterOperator::Equals, '2026-01-01T12:00:00.123456+02:00', [1], ['2026-01-01 10:00:00.123456']],
    'set membership' => [FilterOperator::In, ['2026-01-01T10:00:00.123456Z'], [1], ['2026-01-01 10:00:00.123456']],
    'inclusive range' => [FilterOperator::Between, ['2026-01-01T10:00:00.123455Z', '2026-01-01T10:00:00.123457Z'], [1], ['2026-01-01 10:00:00.123455', '2026-01-01 10:00:00.123457']],
    'before a fractional instant' => [FilterOperator::Before, '2026-01-01T10:00:00.123457Z', [1], ['2026-01-01 10:00:00.123457']],
    'after a fractional instant' => [FilterOperator::After, '2026-01-01T10:00:00.123457Z', [3], ['2026-01-01 10:00:00.123457']],
]);

it('matches timestamptz instants independently of session timezone', function (FilterOperator $operator, mixed $value, array $expectedIds): void {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Session time zones apply only to native PostgreSQL timestamptz columns.');
    }

    Schema::dropIfExists('filterable_instant_records');
    Schema::create('filterable_instant_records', function (Blueprint $table): void {
        $table->id();
        $table->timestampTz('occurred_at', 6);
    });
    $connection->table('filterable_instant_records')->insert([
        ['id' => 1, 'occurred_at' => '2026-01-01 12:00:00+00:00'],
        ['id' => 2, 'occurred_at' => '2026-01-01 12:00:00.123456+00:00'],
        ['id' => 3, 'occurred_at' => '2026-10-25 00:30:00+00:00'],
        ['id' => 4, 'occurred_at' => '2026-10-25 01:30:00+00:00'],
    ]);
    $connection->statement("SET TIME ZONE 'Europe/Sofia'");
    $model = new class extends Model
    {
        public $timestamps = false;

        protected $table = 'filterable_instant_records';
    };
    $schema = new FilterSchema([new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [$operator])], [new SortDefinition('id', 'id')]);

    try {
        expect(app(EloquentFilterApplier::class)->apply($model->newQuery(), $schema, [['alias' => 'occurred', 'operator' => $operator, 'value' => $value]], [['alias' => 'id', 'direction' => SortDirection::Asc]])->pluck('id')->all())
            ->toBe($expectedIds);
    } finally {
        $connection->statement('SET TIME ZONE UTC');
        Schema::dropIfExists('filterable_instant_records');
    }
})->with([
    'equality' => [FilterOperator::Equals, '2026-01-01T12:00:00Z', [1]],
    'equality with fraction' => [FilterOperator::Equals, '2026-01-01T14:00:00.123456+02:00', [2]],
    'range' => [FilterOperator::Between, ['2026-01-01T11:59:59Z', '2026-01-01T12:00:00.000001Z'], [1]],
    'range with fractions' => [FilterOperator::Between, ['2026-01-01T12:00:00.000001Z', '2026-01-01T12:00:00.123456Z'], [2]],
    'set membership' => [FilterOperator::In, ['2026-01-01T12:00:00Z', '2026-01-01T12:00:00.123456Z'], [1, 2]],
    'first repeated DST hour' => [FilterOperator::Equals, '2026-10-25T03:30:00+03:00', [3]],
    'second repeated DST hour' => [FilterOperator::Equals, '2026-10-25T03:30:00+02:00', [4]],
    'after the repeated DST hour' => [FilterOperator::After, '2026-10-25T00:30:00Z', [4]],
]);

it('retains normalized Carbon instants for date-time custom handlers', function (): void {
    $captured = null;
    $schema = new FilterSchema([
        new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, handler: function (Builder $query, array $criterion) use (&$captured): Builder {
            $captured = $criterion['value'];

            return $query->whereKey(1);
        }),
    ], []);
    $query = app(EloquentFilterApplier::class)->apply(
        FilterableRecord::query()->where('group_id', 1),
        $schema,
        [['alias' => 'occurred', 'operator' => 'equals', 'value' => '2026-01-01T12:00:00.123456+02:00']],
    );

    expect($query->pluck('id')->all())->toBe([1])
        ->and($captured)->toBeInstanceOf(CarbonImmutable::class)
        ->and($captured->format('Y-m-d H:i:s.uP'))->toBe('2026-01-01 10:00:00.123456+00:00');
});

it('rejects relative, rollover, timezone-less, and malformed ranges', function (
    string $alias,
    FilterOperator $operator,
    mixed $value,
    string $message,
): void {
    expect(fn () => FilterableRecord::query()->applyFilters([
        ['alias' => $alias, 'operator' => $operator, 'value' => $value],
    ])->get())->toThrow(FilterableException::class, $message);
})->with([
    'relative date' => ['created', FilterOperator::Equals, 'tomorrow', 'must use YYYY-MM-DD'],
    'rollover date' => ['created', FilterOperator::Equals, '2026-02-30', 'valid calendar date'],
    'timezone-less instant' => ['occurred', FilterOperator::Equals, '2026-01-01 10:00:00', 'ISO-8601'],
    'rollover instant' => ['occurred', FilterOperator::Equals, '2026-02-30T10:00:00Z', 'valid instant'],
    'short range' => ['price', FilterOperator::Between, ['10'], 'exactly two values'],
    'long range' => ['price', FilterOperator::Between, ['10', '20', '30'], 'exactly two values'],
]);

it('applies null checks without accepting dummy values', function (): void {
    $null = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'note', 'operator' => FilterOperator::IsNull, 'value' => null],
        ])
        ->pluck('name')
        ->all();

    $notNull = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'note', 'operator' => FilterOperator::IsNotNull, 'value' => null],
        ])
        ->pluck('name')
        ->all();

    expect($null)->toBe(['Beta'])
        ->and($notNull)->toBe(['Alpha', 'Gamma'])
        ->and(fn () => FilterableRecord::query()->applyFilters([
            ['alias' => 'note', 'operator' => FilterOperator::IsNull, 'value' => 'ignored'],
        ])->get())->toThrow(FilterableException::class, 'must not contain a value');
});

it('applies comparison operator aliases', function (
    FilterOperator $operator,
    string $value,
    array $expected,
): void {
    $names = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'price', 'operator' => $operator, 'value' => $value],
        ])
        ->pluck('name')
        ->all();

    expect($names)->toBe($expected);
})->with([
    'greater than' => [FilterOperator::Gt, '20', ['Gamma']],
    'less than' => [FilterOperator::Lt, '20', ['Alpha']],
    'less than or equal' => [FilterOperator::Lte, '20', ['Alpha', 'Beta']],
]);

it('applies allowlisted nested relation filters', function (): void {
    $names = FilterableRecord::query()
        ->applyFilters([
            ['alias' => 'group', 'operator' => FilterOperator::Contains, 'value' => 'ditor'],
        ])
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha', 'Gamma']);
});

it('matches negative operators on a relation path against any single related row', function (
    FilterOperator $operator,
    mixed $value,
    array $expected,
): void {
    FilterableGroup::query()->insert(['id' => 3, 'name' => 'Empty']);
    $schema = new FilterSchema(
        filters: [new FilterDefinition('record', 'records.name', operators: [
            FilterOperator::NotEquals,
            FilterOperator::NotIn,
            FilterOperator::NotContains,
        ])],
        sorts: [new SortDefinition('id', 'id')],
        defaultSorts: ['id'],
    );

    $names = app(EloquentFilterApplier::class)->apply(FilterableGroup::query(), $schema, [
        ['alias' => 'record', 'operator' => $operator, 'value' => $value],
    ])->pluck('name')->all();

    expect($names)->toBe($expected);
})->with([
    'not_equals keeps a parent that also has the excluded row' => [FilterOperator::NotEquals, 'Alpha', ['Editorial', 'Sales']],
    'not_in keeps a parent with one row outside the list' => [FilterOperator::NotIn, ['Alpha'], ['Editorial', 'Sales']],
    'not_in drops a parent whose rows are all listed' => [FilterOperator::NotIn, ['Alpha', 'Gamma'], ['Sales']],
    'not_contains keeps a parent that also has a matching row' => [FilterOperator::NotContains, 'lph', ['Editorial', 'Sales']],
    'not_contains drops a parent whose rows all match' => [FilterOperator::NotContains, 'a', []],
]);

it('fails closed for unknown aliases and unsupported operators', function (): void {
    expect(fn () => FilterableRecord::query()->applyFilters([
        ['alias' => 'private_column', 'operator' => FilterOperator::Equals, 'value' => 'secret'],
    ])->get())->toThrow(FilterableException::class, 'Unknown filter alias');

    expect(fn () => FilterableRecord::query()->applyFilters([
        ['alias' => 'status', 'operator' => FilterOperator::Contains, 'value' => 'active'],
    ])->get())->toThrow(FilterableException::class, 'is not allowed');
});

it('parses scalar shorthand, exact filter objects, null operators, and sort syntax', function (): void {
    $factory = app(ConsumerQueryFilters::class);
    $schema = (new FilterableRecord)->filterSchema();
    $set = $factory->fromQuery([
        'filter' => [
            'name' => 'Alpha',
            'status' => ['operator' => 'in', 'value' => ['active']],
            'note' => ['operator' => 'is_null'],
        ],
        'sort' => '-price,name',
    ], $schema);

    expect($set['filters'])->toHaveCount(3)
        ->and($set['filters'][0]['operator'])->toBe(FilterOperator::Equals)
        ->and($set['filters'][2]['operator'])->toBe(FilterOperator::IsNull)
        ->and($set['filters'][2]['value'])->toBeNull()
        ->and($set['sorts'][0]['direction'])->toBe('desc')
        ->and($set['sorts'][1]['direction'])->toBe('asc');
});

it('rejects malformed filter objects at the adapter boundary', function (
    array $payload,
    string $message,
): void {
    $factory = app(ConsumerQueryFilters::class);
    $schema = (new FilterableRecord)->filterSchema();

    expect(fn () => $factory->fromQuery(['filter' => ['note' => $payload]], $schema))
        ->toThrow(FilterableException::class, $message);
})->with([
    'extra key' => [['operator' => 'equals', 'value' => 'x', 'column' => 'password'], 'must contain only'],
    'missing operator' => [['value' => 'x'], 'must contain only'],
    'missing value' => [['operator' => 'equals'], 'requires a value'],
    'dummy null value' => [['operator' => 'is_null', 'value' => null], 'must not contain a value'],
]);

it('rejects unknown or disallowed HTTP operators before query application', function (): void {
    $factory = app(ConsumerQueryFilters::class);
    $schema = (new FilterableRecord)->filterSchema();

    expect(fn () => $factory->fromQuery([
        'filter' => ['status' => ['operator' => 'contains', 'value' => 'active']],
    ], $schema))->toThrow(FilterableException::class, 'is not allowed');

    expect(fn () => $factory->fromQuery([
        'filter' => ['status' => ['operator' => 'drop', 'value' => 'active']],
    ], $schema))->toThrow(FilterableException::class, 'Unsupported filter operator');
});

it('maps malformed HTTP filters and values to Laravel validation exceptions', function (
    array $query,
    string $path,
): void {
    $factory = app(ConsumerQueryFilters::class);
    $schema = (new FilterableRecord)->filterSchema();

    try {
        $factory->fromHttpQuery($query, $schema);
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->errors())->toHaveKey($path);

        return;
    }

    $this->fail('A malformed HTTP filter did not produce a validation exception.');
})->with([
    'unknown alias' => [
        ['filter' => ['private' => 'value']],
        'filter.private',
    ],
    'invalid normalized value' => [
        ['filter' => ['active' => 'off']],
        'filter.active.value',
    ],
]);

it('enforces filter, sort, list-value, and string complexity limits', function (): void {
    $factory = app(ConsumerQueryFilters::class);
    $applier = app(EloquentFilterApplier::class);
    $schema = new FilterSchema(
        filters: [
            new FilterDefinition('name', 'name', operators: [FilterOperator::Equals, FilterOperator::In]),
            new FilterDefinition('status', 'status'),
        ],
        sorts: [
            new SortDefinition('name', 'name'),
            new SortDefinition('id', 'id'),
        ],
        maximumFilters: 1,
        maximumSorts: 1,
        maximumValuesPerFilter: 2,
        maximumStringLength: 4,
    );

    expect(fn () => $factory->fromQuery([
        'filter' => ['name' => 'a', 'status' => 'b'],
    ], $schema))->toThrow(FilterableException::class, 'filter complexity');

    expect(fn () => $factory->fromQuery([
        'sort' => 'name,id',
    ], $schema))->toThrow(FilterableException::class, 'sort complexity');

    expect(fn () => $applier->apply(FilterableRecord::query(), $schema, [
        ['alias' => 'name', 'operator' => FilterOperator::In, 'value' => ['a', 'b', 'c']],
    ], [])->get())->toThrow(FilterableException::class, 'value complexity');

    expect(fn () => $applier->apply(FilterableRecord::query(), $schema, [
        ['alias' => 'name', 'operator' => FilterOperator::Equals, 'value' => 'abcde'],
    ], [])->get())->toThrow(FilterableException::class, 'maximum string length');
});

it('measures the string limit in characters rather than bytes', function (): void {
    $applier = app(EloquentFilterApplier::class);
    $schema = new FilterSchema(
        filters: [new FilterDefinition('name', 'name')],
        sorts: [],
        maximumStringLength: 4,
    );

    expect($applier->apply(FilterableRecord::query(), $schema, [
        ['alias' => 'name', 'operator' => FilterOperator::Equals, 'value' => 'Алфа'],
    ])->count())->toBe(0)
        ->and(fn () => $applier->apply(FilterableRecord::query(), $schema, [
            ['alias' => 'name', 'operator' => FilterOperator::Equals, 'value' => 'Алфа5'],
        ]))->toThrow(FilterableException::class, 'maximum string length');
});

it('rejects duplicate HTTP and programmatic sorts', function (): void {
    $factory = app(ConsumerQueryFilters::class);
    $schema = (new FilterableRecord)->filterSchema();

    expect(fn () => $factory->fromQuery(['sort' => 'name,-name'], $schema))
        ->toThrow(FilterableException::class, 'Duplicate sort');

    expect(fn () => FilterableRecord::query()->applyFilters(
        sorts: [
            ['alias' => 'name', 'direction' => 'asc'],
            ['alias' => 'name', 'direction' => SortDirection::Desc->value],
        ],
    )->get())->toThrow(FilterableException::class, 'Duplicate sort');
});

it('appends a stable tie-breaker to default and explicit sorts', function (): void {
    $defaultQuery = FilterableRecord::query()->applyFilters([]);
    $explicitQuery = FilterableRecord::query()->applyFilters(
        sorts: [['alias' => 'price', 'direction' => SortDirection::Desc->value]],
    );

    expect($defaultQuery->getQuery()->orders)->toBe([
        ['column' => 'name', 'direction' => 'asc'],
        ['column' => 'id', 'direction' => 'asc'],
    ])->and($explicitQuery->getQuery()->orders)->toBe([
        ['column' => 'price', 'direction' => 'desc'],
        ['column' => 'id', 'direction' => 'asc'],
    ]);
});

it('validates schema identifiers, compatibility, nullability, defaults, and tie-breakers', function (
    Closure $definition,
    string $message,
): void {
    expect($definition)->toThrow(FilterableException::class, $message);
})->with([
    'filter alias' => [
        fn (): FilterDefinition => new FilterDefinition('bad alias', 'name'),
        'alias',
    ],
    'relation depth' => [
        fn (): FilterDefinition => new FilterDefinition('name', 'one.two.three.four'),
        'relation path',
    ],
    'unsafe column' => [
        fn (): FilterDefinition => new FilterDefinition('name', 'name;drop'),
        'relation path',
    ],
    'sort relation' => [
        fn (): SortDefinition => new SortDefinition('author', 'author.name'),
        'invalid column',
    ],
    'incompatible operator' => [
        fn (): FilterDefinition => new FilterDefinition(
            'active',
            'active',
            FilterValueType::Boolean,
            [FilterOperator::Contains],
        ),
        'incompatible',
    ],
    'non-null null operator' => [
        fn (): FilterDefinition => new FilterDefinition(
            'name',
            'name',
            operators: [FilterOperator::IsNull],
        ),
        'must be nullable',
    ],
    'unknown default' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: [new SortDefinition('name', 'name')],
            defaultSorts: ['private'],
        ),
        'not declared',
    ],
    'unknown tie breaker' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: [new SortDefinition('name', 'name')],
            tieBreakerSort: 'id',
        ),
        'Tie-breaker sort',
    ],
]);

it('exposes stable machine-readable exception context', function (): void {
    $exception = new FilterableException(
        'Invalid input.',
        'invalid_example',
        'filter.example.value',
    );

    expect($exception->errorCode)->toBe('invalid_example')
        ->and($exception->path)->toBe('filter.example.value');
});

it('applies native filter and sort arrays directly', function (): void {
    $query = FilterableRecord::query();
    $result = app(EloquentFilterApplier::class)->apply($query, (new FilterableRecord)->filterSchema(), [['alias' => 'price', 'operator' => 'gte', 'value' => '20']], [['alias' => 'price', 'direction' => 'desc']]);

    expect($result)->toBe($query)
        ->and($result->pluck('name')->all())->toBe(['Gamma', 'Beta']);
});

it('rejects malicious native shapes before mutating the query', function (array $filters, array $sorts): void {
    $query = FilterableRecord::query()->where('group_id', 1);
    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect(fn () => app(EloquentFilterApplier::class)->apply($query, (new FilterableRecord)->filterSchema(), $filters, $sorts))
        ->toThrow(FilterableException::class);
    expect($query->toSql())->toBe($sql)
        ->and($query->getBindings())->toBe($bindings);
})->with([
    'associative filters' => [['name' => ['operator' => 'equals', 'value' => 'Alpha']], []],
    'scalar entry' => [['Alpha'], []],
    'unknown key' => [[['alias' => 'name', 'operator' => 'equals', 'value' => 'Alpha', 'column' => 'password']], []],
    'OR key' => [[['alias' => 'name', 'operator' => 'equals', 'value' => 'Alpha', 'boolean' => 'or']], []],
    'missing alias' => [[['operator' => 'equals', 'value' => 'Alpha']], []],
    'missing operator' => [[['alias' => 'name', 'value' => 'Alpha']], []],
    'missing value' => [[['alias' => 'name', 'operator' => 'equals']], []],
    'object value' => [[['alias' => 'name', 'operator' => 'equals', 'value' => new stdClass]], []],
    'nested value' => [[['alias' => 'name', 'operator' => 'in', 'value' => [['secret']]]], []],
    'unknown relation' => [[['alias' => 'group.password', 'operator' => 'equals', 'value' => 'x']], []],
    'unknown operator' => [[['alias' => 'name', 'operator' => 'drop', 'value' => 'x']], []],
    'late invalid filter' => [[['alias' => 'name', 'operator' => 'equals', 'value' => 'Alpha'], ['alias' => 'private', 'operator' => 'equals', 'value' => 'x']], []],
    'associative sorts' => [[], ['name' => 'asc']],
    'sort unknown key' => [[], [['alias' => 'name', 'direction' => 'asc', 'column' => 'password']]],
    'sort missing direction' => [[], [['alias' => 'name']]],
    'sort invalid direction' => [[], [['alias' => 'name', 'direction' => 'ASC']]],
    'late invalid sort' => [[['alias' => 'name', 'operator' => 'equals', 'value' => 'Alpha']], [['alias' => 'private', 'direction' => 'asc']]],
]);

it('applies native null omission and explicit null with one declared tie-breaker', function (): void {
    $applier = app(EloquentFilterApplier::class);
    $schema = (new FilterableRecord)->filterSchema();
    foreach ([['alias' => 'note', 'operator' => 'is_null'], ['alias' => 'note', 'operator' => FilterOperator::IsNull, 'value' => null]] as $filter) {
        expect($applier->apply(FilterableRecord::query(), $schema, [$filter])->pluck('name')->all())->toBe(['Beta']);
    }

    $query = $applier->apply(FilterableRecord::query(), $schema, sorts: [['alias' => 'id', 'direction' => 'desc']]);
    expect($query->getQuery()->orders)->toBe([['column' => 'id', 'direction' => 'desc']])
        ->and($query->pluck('id')->all())->toBe([3, 2, 1]);
});

it('enforces every native query complexity budget before mutation', function (array $filters, array $sorts, string $code): void {
    $schema = new FilterSchema(
        filters: [new FilterDefinition('name', 'name', operators: [FilterOperator::Equals, FilterOperator::In])],
        sorts: [new SortDefinition('id', 'id'), new SortDefinition('name', 'name')],
        maximumFilters: 1,
        maximumSorts: 1,
        maximumValuesPerFilter: 1,
        maximumStringLength: 4,
    );
    $query = FilterableRecord::query()->where('group_id', 1);
    $sql = $query->toSql();
    try {
        app(EloquentFilterApplier::class)->apply($query, $schema, $filters, $sorts);
        $this->fail('The native complexity limit was bypassed.');
    } catch (FilterableException $exception) {
        expect($exception->errorCode)->toBe($code)
            ->and($query->toSql())->toBe($sql)
            ->and($query->getBindings())->toBe([1]);
    }
})->with([
    'filters' => [[['alias' => 'name', 'operator' => 'equals', 'value' => 'a'], ['alias' => 'name', 'operator' => 'equals', 'value' => 'b']], [], 'filter_complexity_exceeded'],
    'sorts' => [[], [['alias' => 'id', 'direction' => 'asc'], ['alias' => 'name', 'direction' => 'asc']], 'sort_complexity_exceeded'],
    'values' => [[['alias' => 'name', 'operator' => 'in', 'value' => ['a', 'b']]], [], 'filter_value_complexity_exceeded'],
    'strings' => [[['alias' => 'name', 'operator' => 'equals', 'value' => 'abcde']], [], 'filter_string_too_long'],
]);

it('rejects string values a database cannot store as text', function (FilterOperator $operator, string $value): void {
    $query = FilterableRecord::query();

    try {
        $query->applyFilters([['alias' => 'name', 'operator' => $operator, 'value' => $value]])->get();
        $this->fail('A malformed string reached the database.');
    } catch (FilterableException $exception) {
        expect($exception->errorCode)->toBe('invalid_string_filter_value')
            ->and($exception->path)->toBe('filter.name.value');
    }
})->with([
    'NUL byte' => [FilterOperator::Equals, "Al\0pha"],
    'NUL byte in a substring' => [FilterOperator::Contains, "\0"],
    'invalid UTF-8' => [FilterOperator::Equals, "Al\xC3\x28"],
    'truncated UTF-8 in a substring' => [FilterOperator::Contains, "\xE2\x82"],
]);

it('treats backslashes and the escape character as literal substring characters', function (): void {
    FilterableRecord::query()->insert([
        ['id' => 4, 'name' => 'Delta', 'status' => 'active', 'price' => 40, 'amount' => '40.00', 'is_active' => true, 'note' => 'back\\slash', 'created_at' => '2026-01-04', 'occurred_at' => '2026-01-04 10:00:00', 'group_id' => 1],
        ['id' => 5, 'name' => 'Epsilon', 'status' => 'active', 'price' => 50, 'amount' => '50.00', 'is_active' => true, 'note' => 'bang!', 'created_at' => '2026-01-05', 'occurred_at' => '2026-01-05 10:00:00', 'group_id' => 1],
    ]);
    $names = static fn (string $needle): array => FilterableRecord::query()
        ->applyFilters([['alias' => 'note', 'operator' => FilterOperator::Contains, 'value' => $needle]])
        ->pluck('name')
        ->all();

    expect($names('\\'))->toBe(['Delta'])
        ->and($names('k\\s'))->toBe(['Delta'])
        ->and($names('\\%'))->toBe([])
        ->and($names('!'))->toBe(['Epsilon'])
        ->and($names('g!'))->toBe(['Epsilon'])
        ->and($names('!_'))->toBe([]);
});

it('rejects integers outside a declared range and calendar years no database accepts', function (): void {
    $applier = app(EloquentFilterApplier::class);
    $schema = new FilterSchema([
        new FilterDefinition('price', 'price', FilterValueType::Integer, [FilterOperator::Lte, FilterOperator::In], minimum: -2_147_483_648, maximum: 2_147_483_647),
        new FilterDefinition('created', 'created_at', FilterValueType::Date, [FilterOperator::After]),
        new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [FilterOperator::After]),
    ], []);
    $count = static fn (string $alias, FilterOperator $operator, mixed $value): int => $applier
        ->apply(FilterableRecord::query(), $schema, [['alias' => $alias, 'operator' => $operator, 'value' => $value]])
        ->count();
    $code = static function (string $alias, FilterOperator $operator, mixed $value) use ($count): ?string {
        try {
            $count($alias, $operator, $value);
        } catch (FilterableException $exception) {
            return $exception->errorCode;
        }

        return null;
    };

    expect($count('price', FilterOperator::Lte, '2147483647'))->toBe(3)
        ->and($count('created', FilterOperator::After, '0001-01-01'))->toBe(3)
        ->and($count('occurred', FilterOperator::After, '0001-01-01T00:00:00Z'))->toBe(3)
        ->and($code('price', FilterOperator::Lte, '2147483648'))->toBe('invalid_integer_filter_value')
        ->and($code('price', FilterOperator::In, [1, PHP_INT_MIN]))->toBe('invalid_integer_filter_value')
        ->and($code('created', FilterOperator::After, '0000-01-01'))->toBe('invalid_date_filter_value')
        ->and($code('occurred', FilterOperator::After, '0000-06-01T00:00:00Z'))->toBe('invalid_date_time_filter_value')
        ->and($code('occurred', FilterOperator::After, '0001-01-01T00:00:00+02:00'))->toBe('invalid_date_time_filter_value')
        ->and($code('occurred', FilterOperator::After, '9999-12-31T23:59:59-01:00'))->toBe('invalid_date_time_filter_value');
});

it('declares integer ranges only on integer filters with ordered bounds', function (Closure $definition): void {
    expect($definition)->toThrow(FilterableException::class, 'invalid integer range');
})->with([
    'string filter' => [fn () => new FilterDefinition('name', 'name', maximum: 5)],
    'decimal filter' => [fn () => new FilterDefinition('amount', 'amount', FilterValueType::Decimal, minimum: 0)],
    'inverted bounds' => [fn () => new FilterDefinition('price', 'price', FilterValueType::Integer, minimum: 5, maximum: 4)],
]);

it('accepts the sort direction enum in native sorts', function (): void {
    $query = app(EloquentFilterApplier::class)->apply(
        FilterableRecord::query(),
        (new FilterableRecord)->filterSchema(),
        sorts: [['alias' => 'price', 'direction' => SortDirection::Desc]],
    );

    expect($query->getQuery()->orders)->toBe([
        ['column' => 'price', 'direction' => 'desc'],
        ['column' => 'id', 'direction' => 'asc'],
    ])->and($query->pluck('name')->all())->toBe(['Gamma', 'Beta', 'Alpha']);
});

it('filters schema-qualified joined models with literal substring operators', function (FilterOperator $operator, array $names): void {
    $model = new FilterableRecord;
    $schema = $model->getConnection()->getDriverName() === 'pgsql' ? 'public' : 'main';
    $model->setTable($schema.'.filterable_records');

    $query = app(EloquentFilterApplier::class)->apply(
        $model->newQuery()
            ->join('filterable_groups', 'filterable_groups.id', '=', $model->qualifyColumn('group_id'))
            ->select($model->qualifyColumn('name')),
        $model->filterSchema(),
        [['alias' => 'name', 'operator' => $operator, 'value' => 'lph']],
        [['alias' => 'id', 'direction' => SortDirection::Asc]],
    );

    expect($query->pluck($model->qualifyColumn('name'))->all())->toBe($names);
})->with([
    'contains' => [FilterOperator::Contains, ['Alpha']],
    'not contains' => [FilterOperator::NotContains, ['Beta', 'Gamma']],
]);

it('rejects unsafe qualified identifiers on joined models', function (): void {
    $model = (new FilterableRecord)->setTable('public.filterable_records;drop');
    $query = $model->newQuery()->join('filterable_groups', 'filterable_groups.id', '=', $model->qualifyColumn('group_id'));

    expect(fn () => app(EloquentFilterApplier::class)->apply(
        $query,
        $model->filterSchema(),
        [['alias' => 'name', 'operator' => FilterOperator::Contains, 'value' => 'lph']],
    ))->toThrow(FilterableException::class, 'unsafe column identifier');
});

it('qualifies columns on joined queries and normalizes small finite floats and microsecond timestamps', function (): void {
    $applier = app(EloquentFilterApplier::class);
    $schema = (new FilterableRecord)->filterSchema();

    $joined = $applier->apply(
        FilterableRecord::query()
            ->join('filterable_groups', 'filterable_groups.id', '=', 'filterable_records.group_id')
            ->select('filterable_records.*'),
        $schema,
        [['alias' => 'name', 'operator' => FilterOperator::Contains, 'value' => 'lph']],
        [['alias' => 'id', 'direction' => SortDirection::Asc]],
    );

    expect($joined->pluck('filterable_records.name')->all())->toBe(['Alpha']);

    $normalizer = app(FilterCriterionNormalizer::class);
    $normalized = $normalizer->filters(
        [['alias' => 'amount', 'operator' => FilterOperator::Equals, 'value' => 0.00001]],
        $schema,
    );
    expect($normalized[0]['value'])->toBe('0.00001');

    Schema::create('filterable_microsecond_records', function (Blueprint $table): void {
        $table->id();
        $table->string('occurred_at');
    });

    $microModel = new class extends Model
    {
        public $timestamps = false;

        protected $table = 'filterable_microsecond_records';

        protected $dateFormat = 'Y-m-d H:i:s.u';

        protected $guarded = [];
    };

    $microModel->newQuery()->create(['occurred_at' => '2026-01-01 00:00:00.000000']);
    $microSchema = new FilterSchema(
        filters: [new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [FilterOperator::Lte, FilterOperator::Equals])],
        sorts: [new SortDefinition('id', 'id')],
    );

    expect($applier->apply($microModel->newQuery(), $microSchema, [['alias' => 'occurred', 'operator' => FilterOperator::Lte, 'value' => '2026-01-01T00:00:00Z']])->count())->toBe(1)
        ->and($applier->apply($microModel->newQuery(), $microSchema, [['alias' => 'occurred', 'operator' => FilterOperator::Equals, 'value' => '2026-01-01T00:00:00Z']])->count())->toBe(1);
});

it('compares timestamptz columns by instant even with an offset-free microsecond model format', function (FilterOperator $operator, mixed $value, array $expectedIds): void {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Session time zones apply only to native PostgreSQL timestamptz columns.');
    }

    Schema::dropIfExists('filterable_instant_records');
    Schema::create('filterable_instant_records', function (Blueprint $table): void {
        $table->id();
        $table->timestampTz('occurred_at', 6);
    });
    $connection->table('filterable_instant_records')->insert([
        ['id' => 1, 'occurred_at' => '2026-01-01 12:00:00+00:00'],
        ['id' => 2, 'occurred_at' => '2026-01-01 12:00:00.123456+00:00'],
        ['id' => 3, 'occurred_at' => '2026-01-01 14:00:00+00:00'],
    ]);
    $connection->statement("SET TIME ZONE 'Europe/Sofia'");
    $model = new class extends Model
    {
        public $timestamps = false;

        protected $table = 'filterable_instant_records';

        protected $dateFormat = 'Y-m-d H:i:s.u';
    };
    $schema = new FilterSchema([new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [$operator])], [new SortDefinition('id', 'id')]);

    try {
        expect(app(EloquentFilterApplier::class)->apply($model->newQuery(), $schema, [['alias' => 'occurred', 'operator' => $operator, 'value' => $value]], [['alias' => 'id', 'direction' => SortDirection::Asc]])->pluck('id')->all())
            ->toBe($expectedIds);
    } finally {
        $connection->statement('SET TIME ZONE UTC');
        Schema::dropIfExists('filterable_instant_records');
    }
})->with([
    'equality' => [FilterOperator::Equals, '2026-01-01T12:00:00.123456Z', [2]],
    'range' => [FilterOperator::Between, ['2026-01-01T11:59:59Z', '2026-01-01T12:00:00.000001Z'], [1]],
    'set membership' => [FilterOperator::In, ['2026-01-01T12:00:00Z', '2026-01-01T16:00:00+02:00'], [1, 3]],
]);

it('caches the date-time column storage type between filters', function (): void {
    $connection = DB::connection();
    $schema = new FilterSchema([new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [FilterOperator::Gte])], []);
    $filters = [['alias' => 'occurred', 'operator' => FilterOperator::Gte, 'value' => '2026-01-02T00:00:00Z']];
    $applier = app(EloquentFilterApplier::class);
    $countFilterQueries = static function () use ($connection, $applier, $schema, $filters): int {
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        expect($applier->apply(FilterableRecord::query()->orderBy('id'), $schema, $filters)->pluck('id')->all())->toBe([2, 3]);
        $connection->disableQueryLog();

        return count($connection->getQueryLog());
    };

    $first = $countFilterQueries();
    $second = $countFilterQueries();

    expect($first)->toBe($connection->getDriverName() === 'pgsql' ? 2 : 1)
        ->and($second)->toBe(1);
});

it('resolves column storage types per table prefix', function (): void {
    $connection = DB::connection();
    $prefix = $connection->getTablePrefix();
    Schema::create('alpha_filterable_storage_events', fn (Blueprint $table) => $table->timestamp('occurred_at'));
    Schema::create('beta_filterable_storage_events', fn (Blueprint $table) => $table->string('occurred_at'));
    $types = new ColumnStorageTypes;

    try {
        $connection->setTablePrefix($prefix.'alpha_');
        $alpha = $types->typeName($connection, 'filterable_storage_events', 'occurred_at');
        $connection->setTablePrefix($prefix.'beta_');
        $beta = $types->typeName($connection, 'filterable_storage_events', 'occurred_at');
    } finally {
        $connection->setTablePrefix($prefix);
        Schema::dropIfExists('alpha_filterable_storage_events');
        Schema::dropIfExists('beta_filterable_storage_events');
    }

    expect($alpha)->not->toBe('varchar')
        ->and($beta)->toBe('varchar');
});

it('resolves PostgreSQL column storage types by exact column name', function (): void {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Only PostgreSQL keeps columns whose names differ only in case.');
    }

    Schema::create('filterable_case_events', function (Blueprint $table): void {
        $table->string('Occurred_At');
        $table->timestampTz('occurred_at');
    });
    $types = new ColumnStorageTypes;

    try {
        expect($types->typeName($connection, 'filterable_case_events', 'occurred_at'))->toBe('timestamptz')
            ->and($types->typeName($connection, 'filterable_case_events', 'Occurred_At'))->toBe('varchar');
    } finally {
        Schema::dropIfExists('filterable_case_events');
    }
});

it('binds date-time filters in a custom model date format', function (string $format, string $storage, FilterOperator $operator, mixed $value, array $expectedIds): void {
    Schema::dropIfExists('filterable_formatted_records');
    Schema::create('filterable_formatted_records', function (Blueprint $table) use ($storage): void {
        $table->id();
        $storage === 'integer' ? $table->bigInteger('occurred_at') : $table->string('occurred_at');
    });
    $model = new class extends Model
    {
        public $timestamps = false;

        protected $table = 'filterable_formatted_records';

        protected $guarded = [];

        protected $casts = ['occurred_at' => 'datetime'];
    };
    $model->setDateFormat($format);
    foreach (['2026-01-01 10:00:00', '2026-01-01 12:00:00', '2026-01-01 14:00:00'] as $instant) {
        $model->newInstance()->setDateFormat($format)->fill(['occurred_at' => CarbonImmutable::parse($instant, 'UTC')])->save();
    }
    $query = $model->newQuery();
    $query->getModel()->setDateFormat($format);
    $schema = new FilterSchema([new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [$operator])], [new SortDefinition('id', 'id')]);

    try {
        expect(app(EloquentFilterApplier::class)->apply($query, $schema, [['alias' => 'occurred', 'operator' => $operator, 'value' => $value]], [['alias' => 'id', 'direction' => SortDirection::Asc]])->pluck('id')->all())
            ->toBe($expectedIds);
    } finally {
        Schema::dropIfExists('filterable_formatted_records');
    }
})->with([
    'unix equality' => ['U', 'integer', FilterOperator::Equals, '2026-01-01T12:00:00Z', [2]],
    'unix after' => ['U', 'integer', FilterOperator::After, '2026-01-01T12:00:00Z', [3]],
    'unix range' => ['U', 'integer', FilterOperator::Between, ['2026-01-01T11:00:00Z', '2026-01-01T13:00:00Z'], [2]],
    'iso equality' => ['Y-m-d\TH:i:sP', 'string', FilterOperator::Equals, '2026-01-01T14:00:00+02:00', [2]],
    'iso set membership' => ['Y-m-d\TH:i:sP', 'string', FilterOperator::In, ['2026-01-01T10:00:00Z', '2026-01-01T14:00:00Z'], [1, 3]],
    'iso before' => ['Y-m-d\TH:i:sP', 'string', FilterOperator::Before, '2026-01-01T12:00:00Z', [1]],
]);

it('resolves PostgreSQL column storage types through the search path the query uses', function (): void {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Only PostgreSQL resolves tables through a schema search path.');
    }

    Schema::dropIfExists('filterable_path_records');
    Schema::create('filterable_path_records', function (Blueprint $table): void {
        $table->id();
        $table->string('occurred_at');
    });
    $connection->table('filterable_path_records')->insert([['id' => 1, 'occurred_at' => '2026-01-01 10:00:00'], ['id' => 2, 'occurred_at' => '2026-01-01 12:00:00']]);
    $connection->statement('CREATE SCHEMA IF NOT EXISTS filterable_empty_tenant');
    $connection->statement('SET search_path TO filterable_empty_tenant, public');
    $model = new class extends Model
    {
        public $timestamps = false;

        protected $table = 'filterable_path_records';
    };
    $schema = new FilterSchema([new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [FilterOperator::Equals])], []);

    try {
        expect(app(EloquentFilterApplier::class)->apply($model->newQuery(), $schema, [['alias' => 'occurred', 'operator' => FilterOperator::Equals, 'value' => '2026-01-01T12:00:00Z']])->pluck('id')->all())
            ->toBe([2]);
    } finally {
        $connection->statement('SET search_path TO public');
        $connection->statement('DROP SCHEMA IF EXISTS filterable_empty_tenant CASCADE');
        Schema::dropIfExists('filterable_path_records');
    }
});

it('keeps PostgreSQL column storage types apart for each configured search path', function (): void {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Only PostgreSQL resolves tables through a schema search path.');
    }

    $name = $connection->getName();
    $original = config("database.connections.{$name}");
    $connection->statement('CREATE SCHEMA IF NOT EXISTS filterable_text_tenant');
    $connection->statement('CREATE SCHEMA IF NOT EXISTS filterable_native_tenant');
    $connection->statement('CREATE TABLE filterable_text_tenant.filterable_path_records (id bigint primary key, occurred_at varchar(255) not null)');
    $connection->statement('CREATE TABLE filterable_native_tenant.filterable_path_records (id bigint primary key, occurred_at timestamptz not null)');
    $connection->statement("INSERT INTO filterable_text_tenant.filterable_path_records VALUES (1, '2026-01-01 12:00:00')");
    $connection->statement("INSERT INTO filterable_native_tenant.filterable_path_records VALUES (1, '2026-01-01 12:00:00+00')");
    $model = new class extends Model
    {
        public $timestamps = false;

        protected $table = 'filterable_path_records';
    };
    $schema = new FilterSchema([new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime, [FilterOperator::Equals])], []);
    $filters = [['alias' => 'occurred', 'operator' => FilterOperator::Equals, 'value' => '2026-01-01T12:00:00Z']];
    $matches = [];

    try {
        foreach (['filterable_text_tenant', 'filterable_native_tenant'] as $tenant) {
            config(["database.connections.{$name}" => [...$original, 'search_path' => $tenant, 'timezone' => 'Europe/Sofia']]);
            DB::purge($name);
            $matches[$tenant] = app(EloquentFilterApplier::class)->apply($model->newQuery(), $schema, $filters)->pluck('id')->all();
        }
    } finally {
        config(["database.connections.{$name}" => $original]);
        DB::purge($name);
        DB::connection($name)->statement('DROP SCHEMA IF EXISTS filterable_text_tenant CASCADE');
        DB::connection($name)->statement('DROP SCHEMA IF EXISTS filterable_native_tenant CASCADE');
    }

    expect($matches)->toBe(['filterable_text_tenant' => [1], 'filterable_native_tenant' => [1]]);
});
