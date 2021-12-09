<?php

declare(strict_types=1);

namespace Snicco\Auth\Contracts;

use Snicco\Auth\Traits\UsesCurrentRequest;
use Snicco\Core\Contracts\Responsable;

abstract class AbstractTwoFactorChallengeResponse implements Responsable
{
    
    use UsesCurrentRequest;
}