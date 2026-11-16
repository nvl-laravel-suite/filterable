<?php

declare(strict_types=1);

namespace Nvl\Filterable\Input;

use Nvl\Filterable\Enums\FilterOperator;

/**
 * Native criteria and sorts from a request-parameter adapter.
 *
 * @api
 */
final readonly class FilterInput
{
    /**
     * @param  list<array{alias:string, operator:FilterOperator|string, value?:mixed}>  $filters
     * @param  list<array{alias:string, direction:string}>  $sorts
     */
    public function __construct(public array $filters, public array $sorts) {}
}
