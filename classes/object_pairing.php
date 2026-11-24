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

defined('MOODLE_INTERNAL') || die();

final class local_slbridge_object_pairing {

    public static function generate_code(): string {
        return strtoupper(bin2hex(random_bytes(8)));
    }

    public static function hash_code(string $code): string {
        return hash('sha256', strtoupper(trim($code)));
    }

    public static function valid_code(string $code): bool {
        return (bool)preg_match(
            '/^[A-F0-9]{16}$/',
            strtoupper(trim($code))
        );
    }

    public static function generate_secret(): string {
        return bin2hex(random_bytes(32));
    }
}
