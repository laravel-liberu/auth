<?php

namespace LaravelLiberu\Core\Http\Controllers;

use Illuminate\Routing\Controller;
use LaravelLiberu\Core\Http\Responses\GuestState;

class Guest extends Controller
{
    public function __invoke()
    {
        return new GuestState();
    }
}
