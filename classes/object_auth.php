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

final class local_slbridge_object_auth {

    public static function sign(
        string $secret,
        string $timestamp,
        string $nonce,
        string $objectuuid,
        string $body
    ): string {
        $message = $timestamp . "\n" .
            $nonce . "\n" .
            strtolower($objectuuid) . "\n" .
            $body;

        return base64_encode(
            hash_hmac('sha256', $message, $secret, true)
        );
    }

    public static function verify(
        string $secret,
        string $timestamp,
        string $nonce,
        string $objectuuid,
        string $body,
        string $signature
    ): bool {
        $expected = self::sign(
            $secret,
            $timestamp,
            $nonce,
            $objectuuid,
            $body
        );

        return hash_equals($expected, $signature);
    }

    public static function valid_timestamp(
        string $timestamp,
        int $now,
        int $window = 300
    ): bool {
        if (!preg_match('/^[0-9]{10}$/', $timestamp)) {
            return false;
        }

        return abs($now - (int)$timestamp) <= $window;
    }


    public static function claim_nonce(
        string $objectuuid,
        string $nonce
    ): bool {
        if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            return false;
        }

        $key = hash(
            'sha256',
            strtolower($objectuuid) . ':' . $nonce
        );

        $factory =
            \core\lock\lock_config::get_lock_factory(
                'local_slbridge'
            );

        $lock = $factory->get_lock('replay_' . $key, 5);

        if (!$lock) {
            return false;
        }

        try {
            $cache = \cache::make(
                'local_slbridge',
                'requestreplay'
            );

            if ($cache->get($key)) {
                return false;
            }

            $cache->set($key, 1);
            return true;
        } finally {
            $lock->release();
        }
    }

}
