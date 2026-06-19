<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /** @var array<string>|bool */
    protected $guarded = [];

    /** @var bool */
    public $timestamps = false;
}
