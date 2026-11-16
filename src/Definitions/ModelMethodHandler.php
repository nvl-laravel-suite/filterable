<?php

declare(strict_types=1);

namespace Nvl\Filterable\Definitions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Exceptions\FilterableException;
use ReflectionMethod;

/**
 * Calls a fixed, host-declared model method with a validated value and optional operator.
 *
 * @api
 */
final readonly class ModelMethodHandler
{
    public function __construct(public string $method)
    {
        if (preg_match('/^filter[A-Z][A-Za-z0-9]*$/D', $method) !== 1) {
            throw new FilterableException('A model filter method must be explicitly named.', 'invalid_filter_handler', 'filter');
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{alias:string,operator:FilterOperator,value:mixed}  $criterion
     * @return Builder<*>
     */
    public function __invoke(Builder $query, array $criterion): Builder
    {
        $model = $query->getModel();
        if (! method_exists($model, $this->method)) {
            throw $this->invalid($criterion['alias']);
        }
        $method = new ReflectionMethod($model, $this->method);
        if (! $method->isPublic() || $method->isStatic() || ! in_array($method->getNumberOfParameters(), [2, 3], true)) {
            throw $this->invalid($criterion['alias']);
        }
        $arguments = [$query, $criterion['value']];
        if ($method->getNumberOfParameters() === 3) {
            $arguments[] = $criterion['operator']->value;
        }
        $result = $method->invokeArgs($model, $arguments);
        if (! $result instanceof Builder) {
            throw $this->invalid($criterion['alias']);
        }

        return $result;
    }

    private function invalid(string $alias): FilterableException
    {
        return new FilterableException("Filter [{$alias}] requires a public model filter method that returns an Eloquent builder.", 'invalid_filter_handler', "filter.{$alias}");
    }
}
