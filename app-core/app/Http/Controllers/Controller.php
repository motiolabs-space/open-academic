<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Every portal controller authorises explicitly. Guard separation alone is
    // not authorisation: it says which portal you are in, not what you may do
    // once inside it.
    use AuthorizesRequests;
}
