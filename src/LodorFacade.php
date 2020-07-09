<?php

namespace Cybex\Lodor;

use Illuminate\Support\Facades\Facade;

class LodorFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'lodor';
    }
}
