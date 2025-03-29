<?php

use Tests\TestCase;

uses(TestCase::class);

test('show database diagnostics', function () {
    $phpVersion = phpversion();
    $loadedExtensions = get_loaded_extensions();
    $pdoDrivers = extension_loaded('pdo') ? PDO::getAvailableDrivers() : [];

    echo "PHP Version: $phpVersion\n";
    echo "PDO Drivers: " . implode(', ', $pdoDrivers) . "\n";
    echo "SQLite in PDO Drivers: " . (in_array('sqlite', $pdoDrivers) ? 'Yes' : 'No') . "\n";

    expect(true)->toBeTrue();
})->group('diagnostics');
