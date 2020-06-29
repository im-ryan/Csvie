<?php

namespace Rhuett\Csvie\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Csvie.
 *
 * @package Rhuett\Csvie\Facades;
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
