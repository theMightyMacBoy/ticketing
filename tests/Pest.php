<?php

use Carbon\Carbon;
use Coderflex\LaravelTicket\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabaseState::class);

afterEach(function () {
    Carbon::setTestNow();
});
