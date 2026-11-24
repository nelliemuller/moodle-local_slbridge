<?php
// SLBridge: Simulator Learning Bridge.
//
// Copyright (C) 2026 Nellie Deutsch
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program. If not, see <https://www.gnu.org/licenses/>.
//

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/credential_key.php');

try {
    if (!extension_loaded('sodium')) {
        throw new RuntimeException('Sodium unavailable');
    }

    $key = local_slbridge_credential_key::get();

    if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Invalid key length');
    }

    echo "PASS: Sodium available\n";
    echo "PASS: SLBridge encryption key configured\n";
    echo "PASS: Encryption key length valid\n";
    echo "Encryption key was not displayed.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
