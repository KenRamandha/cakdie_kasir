<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str; // Tambahkan ini

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', '*');
        $response->headers->set('Access-Control-Allow-Headers', '*');

        // Untuk file gambar
        if ($request->is('storage/*')) {
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
            $response->headers->set('Content-Type', 'image/*');
        }

        return $response;
    }
}
