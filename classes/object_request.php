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

require_once(__DIR__ . '/object_auth.php');
require_once(__DIR__ . '/credential_crypto.php');
require_once(__DIR__ . '/credential_key.php');

final class local_slbridge_object_request {

    public static function verify_platform(
        string $platform,
        string $grid,
        string $objectuuid,
        string $timestamp,
        string $nonce,
        string $body,
        string $signature
    ): ?object {
        require_once(__DIR__ . '/platform.php');

        [$platform, $grid] =
            local_slbridge_platform::normalize(
                $platform,
                $grid
            );

        // Preserve existing credential and HMAC verification.
        $object = self::verify(
            $objectuuid,
            $timestamp,
            $nonce,
            $body,
            $signature
        );

        if (!$object) {
            return null;
        }

        if ($object->platform !== $platform ||
            $object->grid !== $grid) {
            return null;
        }

        return $object;
    }



    public static function verify(
        string $objectuuid,
        string $timestamp,
        string $nonce,
        string $body,
        string $signature
    ): ?object {
        global $DB;

        $objectuuid = strtolower(trim($objectuuid));

        if (!preg_match(
            '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
            $objectuuid
        )) {
            return null;
        }

        if (!local_slbridge_object_auth::valid_timestamp(
            $timestamp, time()
        )) {
            return null;
        }

        if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $signature)) {
            return null;
        }

        $object = $DB->get_record(
            'local_slbridge_object',
            [
                'objectuuid' => $objectuuid,
                'enabled' => 1,
                'status' => 'active'
            ]
        );

        if (!$object || empty($object->credentialenc)) {
            return null;
        }

        $station = $DB->get_record(
            'local_slbridge_station',
            [
                'id' => $object->stationid,
                'enabled' => 1
            ]
        );

        if (!$station) {
            return null;
        }

        $secret = local_slbridge_credential_crypto::decrypt(
            $object->credentialenc,
            local_slbridge_credential_key::get()
        );

        if (!local_slbridge_object_auth::verify(
            $secret,
            $timestamp,
            $nonce,
            $objectuuid,
            $body,
            $signature
        )) {
            return null;
        }

        if (!local_slbridge_object_auth::claim_nonce(
            $objectuuid,
            $nonce
        )) {
            return null;
        }

        return $object;
    }
}
