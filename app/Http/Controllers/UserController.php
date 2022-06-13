<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Auth;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __invoke()
    {
        return UserResource::make(
            Auth::user()
        );
    }
}
