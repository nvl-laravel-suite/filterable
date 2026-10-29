<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Nvl\Filterable\Definitions\FilterDefinition;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Definitions\SortDefinition;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\FilterValueType;
use Nvl\Filterable\Exceptions\FilterableException;
use Nvl\Filterable\Services\FilterCriterionNormalizer;
use Nvl\Filterable\Tests\Fixtures\ConsumerQueryFilters;

it('rejects invalid offsets and trailing input rather than normalizing another filter value', function (FilterValueType $type, string $value): void {
    $definition = new FilterDefinition('value', 'value', $type);
    $schema = new FilterSchema([$definition], []);
    expect(fn () => (new FilterCriterionNormalizer)->filters([
        ['alias' => 'value', 'operator' => FilterOperator::Equals, 'value' => $value],
    ], $schema))->toThrow(FilterableException::class);
})->with([
    'offset hour overflow' => [FilterValueType::DateTime, '2026-01-01T12:00:00+24:00'],
    'offset minute overflow' => [FilterValueType::DateTime, '2026-01-01T12:00:00+01:60'],
    'negative offset overflow' => [FilterValueType::DateTime, '2026-01-01T12:00:00-99:99'],
    'instant trailing newline' => [FilterValueType::DateTime, "2026-01-01T12:00:00Z\n"],
    'integer trailing newline' => [FilterValueType::Integer, "12\n"],
    'decimal trailing newline' => [FilterValueType::Decimal, "12.5\n"],
]);

it('rejects trailing newlines in declared aliases and database paths', function (Closure $definition): void {
    expect($definition)->toThrow(FilterableException::class);
})->with([
    'filter alias' => [fn () => new FilterDefinition("name\n", 'name')],
    'filter column' => [fn () => new FilterDefinition('name', "name\n")],
    'filter relation column' => [fn () => new FilterDefinition('name', "group.name\n")],
    'sort alias' => [fn () => new SortDefinition("name\n", 'name')],
    'sort column' => [fn () => new SortDefinition('name', "name\n")],
]);

it('rejects malformed filter definitions', function (Closure $definition, string $message): void {
    expect($definition)->toThrow(FilterableException::class, $message);
})->with([
    'empty operator list' => [
        fn (): FilterDefinition => new FilterDefinition('name', 'name', operators: []),
        'list of operators',
    ],
    'associative operator list' => [
        fn (): FilterDefinition => new FilterDefinition(
            'name',
            'name',
            operators: ['operator' => FilterOperator::Equals],
        ),
        'operators must be a list',
    ],
    'invalid operator member' => [
        fn (): FilterDefinition => new FilterDefinition('name', 'name', operators: ['equals']),
        'invalid operator',
    ],
    'duplicate operator' => [
        fn (): FilterDefinition => new FilterDefinition(
            'name',
            'name',
            operators: [FilterOperator::Equals, FilterOperator::Equals],
        ),
        'duplicate operators',
    ],
    'missing enum values' => [
        fn (): FilterDefinition => new FilterDefinition('status', 'status', FilterValueType::Enum),
        'must declare allowed values',
    ],
    'unexpected enum values' => [
        fn (): FilterDefinition => new FilterDefinition('name', 'name', enumValues: ['alpha']),
        'Only enum filter',
    ],
    'associative enum values' => [
        fn (): FilterDefinition => new FilterDefinition(
            'status',
            'status',
            FilterValueType::Enum,
            enumValues: ['status' => 'active'],
        ),
        'values must be a list',
    ],
    'empty enum value' => [
        fn (): FilterDefinition => new FilterDefinition(
            'status',
            'status',
            FilterValueType::Enum,
            enumValues: [''],
        ),
        'non-empty strings',
    ],
    'invalid enum value type' => [
        fn (): FilterDefinition => new FilterDefinition(
            'status',
            'status',
            FilterValueType::Enum,
            enumValues: [1],
        ),
        'non-empty strings',
    ],
    'duplicate enum value' => [
        fn (): FilterDefinition => new FilterDefinition(
            'status',
            'status',
            FilterValueType::Enum,
            enumValues: ['active', 'active'],
        ),
        'duplicate values',
    ],
    'empty relation segment' => [
        fn (): FilterDefinition => new FilterDefinition('name', 'relation..name'),
        'invalid column',
    ],
]);

it('rejects malformed filter schemas', function (Closure $schema, string $message): void {
    expect($schema)->toThrow(FilterableException::class, $message);
})->with([
    'associative filters' => [
        fn (): FilterSchema => new FilterSchema(
            filters: ['name' => new FilterDefinition('name', 'name')],
            sorts: [],
        ),
        'Filter definitions must be a list',
    ],
    'invalid filter member' => [
        fn (): FilterSchema => new FilterSchema(filters: ['name'], sorts: []),
        'Filter definitions are invalid',
    ],
    'associative sorts' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: ['name' => new SortDefinition('name', 'name')],
        ),
        'Sort definitions must be a list',
    ],
    'invalid sort member' => [
        fn (): FilterSchema => new FilterSchema(filters: [], sorts: ['name']),
        'Sort definitions are invalid',
    ],
    'associative defaults' => [
        fn (): FilterSchema => new FilterSchema(filters: [], sorts: [], defaultSorts: ['sort' => 'name']),
        'Default sorts must be a list',
    ],
    'invalid default member' => [
        fn (): FilterSchema => new FilterSchema(filters: [], sorts: [], defaultSorts: [1]),
        'non-empty strings',
    ],
    'empty default member' => [
        fn (): FilterSchema => new FilterSchema(filters: [], sorts: [], defaultSorts: ['']),
        'non-empty strings',
    ],
    'double-prefixed default' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: [new SortDefinition('name', 'name')],
            defaultSorts: ['--name'],
        ),
        'not declared',
    ],
    'duplicate default' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: [new SortDefinition('name', 'name')],
            defaultSorts: ['name', '-name'],
        ),
        'Duplicate default sorts',
    ],
    'excess defaults' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: [new SortDefinition('name', 'name'), new SortDefinition('id', 'id')],
            defaultSorts: ['name', 'id'],
            maximumSorts: 1,
        ),
        'Default sorts exceed',
    ],
    'duplicate filter alias' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [
                new FilterDefinition('name', 'name'),
                new FilterDefinition('name', 'other_name'),
            ],
            sorts: [],
        ),
        'Duplicate filter alias',
    ],
    'duplicate sort alias' => [
        fn (): FilterSchema => new FilterSchema(
            filters: [],
            sorts: [
                new SortDefinition('name', 'name'),
                new SortDefinition('name', 'other_name'),
            ],
        ),
        'Duplicate sort alias',
    ],
]);

it('rejects invalid schema limits', function (
    int $filters,
    int $sorts,
    int $values,
    int $length,
    string $message,
): void {
    expect(fn () => new FilterSchema(
        filters: [],
        sorts: [],
        maximumFilters: $filters,
        maximumSorts: $sorts,
        maximumValuesPerFilter: $values,
        maximumStringLength: $length,
    ))->toThrow(FilterableException::class, $message);
})->with([
    'zero filters' => [0, 1, 1, 1, 'Maximum filters'],
    'excess filters' => [101, 1, 1, 1, 'Maximum filters'],
    'zero sorts' => [1, 0, 1, 1, 'Maximum sorts'],
    'excess sorts' => [1, 26, 1, 1, 'Maximum sorts'],
    'zero values' => [1, 1, 0, 1, 'Maximum values'],
    'excess values' => [1, 1, 1_001, 1, 'Maximum values'],
    'zero length' => [1, 1, 1, 0, 'Maximum string length'],
    'excess length' => [1, 1, 1, 10_001, 'Maximum string length'],
]);

it('rejects malformed query transport variants', function (array $query, string $message): void {
    $schema = new FilterSchema(
        filters: [
            new FilterDefinition('name', 'name', operators: [FilterOperator::Equals, FilterOperator::In]),
        ],
        sorts: [
            new SortDefinition('name', 'name'),
        ],
        maximumFilters: 1,
        maximumSorts: 1,
    );

    expect(fn () => app(ConsumerQueryFilters::class)->fromQuery($query, $schema))
        ->toThrow(FilterableException::class, $message);
})->with([
    'scalar filter container' => [['filter' => 'name'], 'must be an object'],
    'integer filter alias' => [['filter' => ['name']], 'aliases must be strings'],
    'unsafe unknown alias' => [['filter' => ['name;drop' => 'value']], 'Unknown filter alias'],
    'scalar sort container' => [['sort' => 1], 'string or object'],
    'non-string sort list value' => [['sort' => [1]], 'non-empty strings'],
    'empty sort list value' => [['sort' => ['']], 'non-empty strings'],
    'unknown sort' => [['sort' => 'private'], 'Unknown sort alias'],
    'double-prefix sort' => [['sort' => '--name'], 'Unknown sort alias'],
    'empty associative direction' => [['sort' => ['name' => null]], 'direction must be asc or desc'],
]);

it('normalizes comma-separated sets and associative sort directions', function (): void {
    $schema = new FilterSchema(
        filters: [
            new FilterDefinition('name', 'name', operators: [FilterOperator::In]),
        ],
        sorts: [
            new SortDefinition('name', 'name'),
        ],
    );
    $set = app(ConsumerQueryFilters::class)->fromQuery([
        'filter' => [
            'name' => ['operator' => 'in', 'value' => 'Alpha,Beta'],
        ],
        'sort' => ['name' => 'DESC'],
    ], $schema);

    expect($set['filters'][0]['value'])->toBe(['Alpha', 'Beta'])
        ->and($set['sorts'][0]['direction'])->toBe('desc');
});

it('rejects invalid normalization shapes and values', function (
    FilterDefinition $definition,
    FilterOperator $operator,
    mixed $value,
    FilterSchema $schema,
    string $message,
): void {
    expect(fn () => app(FilterCriterionNormalizer::class)->normalize(
        ['alias' => $definition->alias, 'operator' => $operator, 'value' => $value],
        $definition,
        $schema,
    ))->toThrow(FilterableException::class, $message);
})->with([
    'single array' => [
        new FilterDefinition('name', 'name'),
        FilterOperator::Equals,
        ['name'],
        new FilterSchema(filters: [], sorts: []),
        'one scalar value',
    ],
    'associative set' => [
        new FilterDefinition('name', 'name', operators: [FilterOperator::In]),
        FilterOperator::In,
        ['name' => 'Alpha'],
        new FilterSchema(filters: [], sorts: []),
        'values must be a list',
    ],
    'non-string set scalar' => [
        new FilterDefinition('name', 'name', operators: [FilterOperator::In]),
        FilterOperator::In,
        1,
        new FilterSchema(filters: [], sorts: []),
        'list',
    ],
    'empty set' => [
        new FilterDefinition('name', 'name', operators: [FilterOperator::In]),
        FilterOperator::In,
        [],
        new FilterSchema(filters: [], sorts: []),
        'at least one value',
    ],
    'set complexity' => [
        new FilterDefinition('name', 'name', operators: [FilterOperator::In]),
        FilterOperator::In,
        ['a', 'b'],
        new FilterSchema(filters: [], sorts: [], maximumValuesPerFilter: 1),
        'value complexity',
    ],
    'invalid enum' => [
        new FilterDefinition('status', 'status', FilterValueType::Enum, enumValues: ['active']),
        FilterOperator::Equals,
        'draft',
        new FilterSchema(filters: [], sorts: []),
        'invalid value',
    ],
    'non-string value' => [
        new FilterDefinition('name', 'name'),
        FilterOperator::Equals,
        1,
        new FilterSchema(filters: [], sorts: []),
        'String filter value is invalid',
    ],
    'integer overflow' => [
        new FilterDefinition('price', 'price', FilterValueType::Integer),
        FilterOperator::Equals,
        '999999999999999999999999',
        new FilterSchema(filters: [], sorts: []),
        'outside the supported range',
    ],
]);

it('normalizes native dates, backed enum values, and consumer string projections', function (): void {
    $normalizer = app(FilterCriterionNormalizer::class);
    $schema = new FilterSchema(filters: [], sorts: []);
    $dateDefinition = new FilterDefinition('created', 'created_at', FilterValueType::Date);
    $dateTimeDefinition = new FilterDefinition('occurred', 'occurred_at', FilterValueType::DateTime);
    $enumDefinition = new FilterDefinition(
        'operator',
        'operator',
        FilterValueType::Enum,
        enumValues: ['equals'],
    );
    $stringDefinition = new FilterDefinition('name', 'name');
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return ' Alpha ';
        }
    };

    $date = $normalizer->normalize(
        ['alias' => 'created', 'operator' => FilterOperator::Equals, 'value' => '2026-01-01'],
        $dateDefinition,
        $schema,
    );
    $dateTime = $normalizer->normalize(
        ['alias' => 'occurred', 'operator' => FilterOperator::Equals, 'value' => '2026-01-01T12:00:00+02:00'],
        $dateTimeDefinition,
        $schema,
    );
    $enum = $normalizer->normalize(
        ['alias' => 'operator', 'operator' => FilterOperator::Equals, 'value' => FilterOperator::Equals->value],
        $enumDefinition,
        $schema,
    );
    $string = $normalizer->normalize(
        ['alias' => 'name', 'operator' => FilterOperator::Equals, 'value' => (string) $stringable],
        $stringDefinition,
        $schema,
    );

    expect($date['value'])->toBe('2026-01-01')
        ->and($dateTime['value'])->toBeInstanceOf(CarbonImmutable::class)
        ->and($dateTime['value']->toIso8601String())->toBe('2026-01-01T10:00:00+00:00')
        ->and($enum['value'])->toBe('equals')
        ->and($string['value'])->toBe('Alpha');
});
