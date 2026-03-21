<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantAccessDeniedException extends HttpException
{
    public function __construct(string $message = 'Tenant access denied.')
    {
        parent::__construct(403, $message);
    }
}
