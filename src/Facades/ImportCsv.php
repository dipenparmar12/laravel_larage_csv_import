<?php

namespace Dipenparmar12\ImportCsv\Facades;

use Illuminate\Support\Facades\Facade;

class ImportCsv extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'importcsv';
    }
}
