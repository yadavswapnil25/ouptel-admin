<?php
namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // Global middleware
    protected $middleware = [
        \Illuminate\Http\Middleware\HandleCors::class,
    ];

}