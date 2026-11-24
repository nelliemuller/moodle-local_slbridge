<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/object_auth.php');

/**
 * Authentication regression tests.
 *
 * @covers \local_slbridge_object_auth
 */
final class object_auth_test extends \basic_testcase {

    public function test_signature_validation(): void {
        $secret = 'temporary-test-secret';
        $timestamp = '1795478400';
        $nonce = str_repeat('a', 32);
        $uuid = '12345678-1234-1234-1234-123456789abc';
        $body = 'action=activate';

        $signature = \local_slbridge_object_auth::sign(
            $secret, $timestamp, $nonce, $uuid, $body
        );

        $this->assertTrue(
            \local_slbridge_object_auth::verify(
                $secret, $timestamp, $nonce,
                $uuid, $body, $signature
            )
        );

        $invalid = [
            ['wrong-secret', $timestamp, $nonce, $uuid, $body],
            [$secret, '1795478401', $nonce, $uuid, $body],
            [$secret, $timestamp, str_repeat('b', 32), $uuid, $body],
            [$secret, $timestamp, $nonce, '87654321-4321-4321-4321-cba987654321', $body],
            [$secret, $timestamp, $nonce, $uuid, 'action=visit'],
        ];

        foreach ($invalid as $args) {
            $args[] = $signature;
            $this->assertFalse(
                \local_slbridge_object_auth::verify(...$args)
            );
        }
    }

    public function test_timestamp_window(): void {
        $now = 1795478400;

        foreach ([-300, 0, 300] as $offset) {
            $this->assertTrue(
                \local_slbridge_object_auth::valid_timestamp(
                    (string)($now + $offset), $now
                )
            );
        }

        foreach ([-301, 301] as $offset) {
            $this->assertFalse(
                \local_slbridge_object_auth::valid_timestamp(
                    (string)($now + $offset), $now
                )
            );
        }

        $this->assertFalse(
            \local_slbridge_object_auth::valid_timestamp(
                'invalid', $now
            )
        );
    }
}
