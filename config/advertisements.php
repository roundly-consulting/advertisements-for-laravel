<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;

return [

    /*
    |--------------------------------------------------------------------------
    | Advertisement Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store advertisements. Override this with your
    | own class (extending the package model) to customise behaviour.
    |
    */

    'model' => Advertisement::class,

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 currency code used when a price is supplied as a bare amount
    | (e.g. via AdvertisementData::fromAmount()) without an explicit currency.
    |
    */

    'default_currency' => env('ADVERTISEMENTS_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Facade Alias
    |--------------------------------------------------------------------------
    |
    | Register the `Advertisements` facade alias automatically so you can call
    | it without importing the fully-qualified class. Set to false to opt out.
    |
    */

    'register_facade_alias' => env('ADVERTISEMENTS_FACADE_ALIAS', true),

];
