<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';

class AdminController
{
    public function __construct()
    {
        requerirRol('admin');
    }
}
