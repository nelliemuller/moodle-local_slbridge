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

final class local_slbridge_object_lifecycle {

    public static function revoke(int $objectid): bool {
        global $DB;

        $object = $DB->get_record(
            'local_slbridge_object',
            ['id' => $objectid],
            '*',
            MUST_EXIST
        );

        $station = $DB->get_record(
            'local_slbridge_station',
            ['id' => $object->stationid],
            'id,courseid',
            MUST_EXIST
        );

        require_capability(
            'local/slbridge:manage',
            \context_course::instance($station->courseid)
        );

        $factory = \core\lock\lock_config::get_lock_factory(
            'local_slbridge'
        );

        $lock = $factory->get_lock(
            'credential_' . $objectid, 10
        );

        if (!$lock) {
            throw new \RuntimeException(
                'Object revocation is busy'
            );
        }

        try {
            $DB->update_record(
                'local_slbridge_object',
                (object)[
                    'id' => $objectid,
                    'enabled' => 0,
                    'credentialenc' => null,
                    'status' => 'revoked'
                ]
            );
        } finally {
            $lock->release();
        }

        return true;
    }

    public static function activate(
        string $objectuuid,
        string $timestamp,
        string $nonce,
        string $body,
        string $signature
    ): bool {
        global $DB;

        require_once(__DIR__ . '/object_auth.php');
        require_once(__DIR__ . '/credential_crypto.php');
        require_once(__DIR__ . '/credential_key.php');

        $objectuuid = strtolower($objectuuid);

        if (!local_slbridge_object_auth::valid_timestamp(
            $timestamp, time()
        )) {
            return false;
        }

        $object = $DB->get_record(
            'local_slbridge_object',
            [
                'objectuuid' => $objectuuid,
                'enabled' => 0,
                'status' => 'pending'
            ]
        );

        if (!$object || empty($object->credentialenc)) {
            return false;
        }

        $factory = \core\lock\lock_config::get_lock_factory(
            'local_slbridge'
        );

        $lock = $factory->get_lock(
            'credential_' . $object->id, 10
        );

        if (!$lock) {
            return false;
        }

        try {
            $current = $DB->get_record(
                'local_slbridge_object',
                [
                    'id' => $object->id,
                    'enabled' => 0,
                    'status' => 'pending'
                ]
            );

            if (!$current || empty($current->credentialenc)) {
                return false;
            }


            require_once(__DIR__ . '/world_location.php');
            require_once(__DIR__ . '/course_worlds.php');

            $station = $DB->get_record(
                'local_slbridge_station',
                [
                    'id' => $current->stationid,
                    'enabled' => 1
                ]
            );

            if (!$station) {
                return false;
            }

            $location = \local_slbridge\world_location::parse(
                $station->slurl
            );

            if (!$location ||
                $location['platform'] !== $current->platform ||
                $location['grid'] !== $current->grid ||
                !\local_slbridge\course_worlds::allows(
                    (int)$station->courseid,
                    $location['platform']
                )) {
                return false;
            }

            $secret = local_slbridge_credential_crypto::decrypt(
                $current->credentialenc,
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
                return false;
            }

            if (!local_slbridge_object_auth::claim_nonce(
                $objectuuid, $nonce
            )) {
                return false;
            }

            $DB->update_record(
                'local_slbridge_object',
                (object)[
                    'id' => $object->id,
                    'status' => 'active',
                    'enabled' => 1,
                    'timemodified' => time()
                ]
            );

            return true;
        } finally {
            $lock->release();
        }
    }

}
