<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Phase 27.10 — no test may reach a real network service (API.Bible
        // quota, payment providers, KJV source). Any un-faked outbound HTTP
        // request fails the test instead of silently going out.
        Http::preventStrayRequests();
    }
}
