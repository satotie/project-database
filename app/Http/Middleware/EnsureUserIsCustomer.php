<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'customer', 403, 'หน้านี้สำหรับลูกค้าเท่านั้น');

        return $next($request);
    }
}
