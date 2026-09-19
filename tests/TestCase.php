<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledViewPath = sys_get_temp_dir().'/regionalb-online-laravel-tests/views';

        if (! is_dir($compiledViewPath)) {
            mkdir($compiledViewPath, 0775, true);
        }

        config()->set('view.compiled', $compiledViewPath);
    }
}
