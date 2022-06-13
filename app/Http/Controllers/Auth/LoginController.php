<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class LoginController extends Controller
{
    public function __invoke()
    {
        request()->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required']
        ]);

        /**
         * We are authenticating a request from our frontend.
         */

        if (EnsureFrontendRequestsAreStateful::fromFrontend(request())) {
            $this->authenticateFrontend();
        }
        /**
         * We are authenticating a request from a 3rd party.
         */
        else {
            // Use token authentication
        }
    }

    public function authenticateFrontend()
    {
        throw_if(
            !Auth::guard('web')->attempt(request()->only('email', 'password'), request()->boolean('remember')),
            ValidationException::withMessages([
                'email' => __('auth.failed')
            ])
        );
    }
}
