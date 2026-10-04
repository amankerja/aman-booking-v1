<?php

namespace App\Models;

use App\Domain\Identity\Models\User as DomainUser;

class User extends DomainUser
{
    // Extends domain user model for Laravel auth & starter kit interoperability
}
