<?php

use LoggedCloud\PageStudio\Tests\TestCase;

// Apply the Testbench-based TestCase to every test file that needs Laravel
// container access (config(), Eloquent, etc.). Structural tests that read
// files directly do not need it · `pest()->use(TestCase::class)` lets us
// opt-in per file.
