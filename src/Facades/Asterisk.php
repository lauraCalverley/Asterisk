<?php namespace Calverley\Asterisk\Facades;

use Illuminate\Support\Facades\Facade;

class Asterisk extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'asterisk';
    }
}