<?php

namespace Rhuett\Csvie\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Csvie.
 */
class Csvie extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'csvie';
    }
}
