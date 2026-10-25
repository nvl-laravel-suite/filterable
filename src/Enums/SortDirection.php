<?php

declare(strict_types=1);

namespace Nvl\Filterable\Enums;

/**
 * Directions accepted by allowlisted sorts.
 *
 * @api
 */
enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
