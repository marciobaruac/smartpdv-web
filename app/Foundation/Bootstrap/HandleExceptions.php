<?php

namespace App\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions as BaseHandleExceptions;

class HandleExceptions extends BaseHandleExceptions
{
    /**
     * Bootstrap the exception handler while ignoring PHP 8.1+ deprecation notices
     * emitted by older Laravel 8 dependencies.
     */
    public function bootstrap(Application $app)
    {
        parent::bootstrap($app);

        require base_path('bootstrap/error_reporting.php');
    }
}
