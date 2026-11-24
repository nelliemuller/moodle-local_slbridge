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
// If not, see <https://www.gnu.org/licenses/>.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

/**
 * Secure kiosk API secret generation.
 *
 * @package local_slbridge
 */
final class kiosk_secret {

    /**
     * Generate a 256-bit secret for kiosk HMAC authentication.
     *
     * @return string 64-character hexadecimal secret.
     */
    public static function generate(): string {
        return bin2hex(random_bytes(32));
    }
}
