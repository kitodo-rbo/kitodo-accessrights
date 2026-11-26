<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Model;

enum Decision: string
{
    case Permit = 'permit';
    case LoginRequired = 'login_required';
    case ElevationRequired = 'elevation_required';
    case Denied = 'denied';
}