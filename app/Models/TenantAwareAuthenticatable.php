<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class TenantAwareAuthenticatable extends Authenticatable
{
    use BelongsToTenant;

    protected bool $enforcesTenantContext = false;
}
