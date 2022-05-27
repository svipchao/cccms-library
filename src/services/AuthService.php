<?php
declare (strict_types=1);

namespace cccms\services;

use cccms\Service;
use cccms\services\auth\{Group, Role, User, Data};

class AuthService extends Service
{
    use Group, Role, User, Data;
}