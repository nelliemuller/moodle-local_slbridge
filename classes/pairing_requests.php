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

require_once(__DIR__ . '/object_pairing.php');
require_once(__DIR__ . '/platform.php');

final class local_slbridge_pairing_requests {

    public static function create(
        int $stationid,
        string $platform = 'secondlife',
        string $grid = 'agni'
    ): string {
        global $DB;

        if ($stationid <= 0) {
            throw new \invalid_parameter_exception(
                'Invalid station ID'
            );
        }

        $station = $DB->get_record(
            'local_slbridge_station',
            ['id' => $stationid, 'enabled' => 1],
            'id',
            MUST_EXIST
        );


        $courseid = $DB->get_field(
            'local_slbridge_station',
            'courseid',
            ['id' => $station->id],
            MUST_EXIST
        );

        require_capability(
            'local/slbridge:manage',
            \context_course::instance($courseid)
        );

        [$platform, $grid] =
            local_slbridge_platform::normalize(
                $platform,
                $grid
            );

        // Derive the platform and grid from the station location.
        require_once(__DIR__ . '/world_location.php');

        $stationlocation = $DB->get_field(
            'local_slbridge_station',
            'slurl',
            ['id' => $stationid],
            MUST_EXIST
        );

        $location = \local_slbridge\world_location::parse(
            $stationlocation
        );

        if (!$location) {
            throw new \invalid_parameter_exception(
                'Invalid virtual world station location'
            );
        }

        $platform = $location['platform'];
        $grid = $location['grid'];


        require_once(__DIR__ . '/course_worlds.php');

        if (!\local_slbridge\course_worlds::allows(
            (int)$courseid,
            $platform
        )) {
            throw new \invalid_parameter_exception(
                'Virtual world is not enabled in this course'
            );
        }

        $code = local_slbridge_object_pairing::generate_code();
        $hash = local_slbridge_object_pairing::hash_code($code);
        $now = time();

        $DB->insert_record('local_slbridge_pairing', (object)[
            'stationid' => $station->id,
            'platform' => $platform,
            'grid' => $grid,
            'codehash' => $hash,
            'objectuuid' => '',
            'expiresat' => $now + 600,
            'usedat' => 0,
            'timecreated' => $now
        ]);

        return $code;
    }

    public static function claim(
        string $code,
        string $objectuuid
    ): bool {
        global $DB;

        $code = strtoupper(trim($code));
        $objectuuid = strtolower(trim($objectuuid));

        if (!local_slbridge_object_pairing::valid_code($code)) {
            return false;
        }

        if (!preg_match(
            '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
            $objectuuid
        )) {
            return false;
        }

        $hash = local_slbridge_object_pairing::hash_code($code);

        $lockfactory =
            \core\lock\lock_config::get_lock_factory('local_slbridge');

        $lock = $lockfactory->get_lock(
            'pair_' . $hash,
            10
        );

        if (!$lock) {
            return false;
        }

        try {
            $pairing = $DB->get_record(
                'local_slbridge_pairing',
                ['codehash' => $hash]
            );

            if (!$pairing ||
                $pairing->usedat ||
                $pairing->expiresat < time() ||
                $pairing->objectuuid !== '') {
                return false;
            }

            $pairing->objectuuid = $objectuuid;

            $DB->update_record(
                'local_slbridge_pairing',
                $pairing
            );

            return true;
        } finally {
            $lock->release();
        }
    }



    public static function confirm(
        int $pairingid,
        int $stationid
    ): bool {
        global $DB;

        $station = $DB->get_record(
            'local_slbridge_station',
            ['id' => $stationid, 'enabled' => 1],
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
            'confirm_' . $pairingid, 10
        );

        if (!$lock) {
            return false;
        }

        try {
            $pairing = $DB->get_record(
                'local_slbridge_pairing',
                ['id' => $pairingid, 'stationid' => $stationid]
            );

            if (!$pairing || $pairing->usedat ||
                $pairing->expiresat < time() ||
                !local_slbridge_uuid_valid($pairing->objectuuid)) {
                return false;
            }


            require_once(__DIR__ . '/world_location.php');
            require_once(__DIR__ . '/course_worlds.php');

            $stationlocation = $DB->get_field(
                'local_slbridge_station',
                'slurl',
                ['id' => $stationid],
                MUST_EXIST
            );

            $location = \local_slbridge\world_location::parse(
                $stationlocation
            );

            if (!$location ||
                $location['platform'] !== $pairing->platform ||
                $location['grid'] !== $pairing->grid ||
                !\local_slbridge\course_worlds::allows(
                    (int)$station->courseid,
                    $location['platform']
                )) {
                return false;
            }

            $objectuuid = strtolower($pairing->objectuuid);

            if ($DB->record_exists(
                'local_slbridge_object',
                ['objectuuid' => $objectuuid]
            )) {
                return false;
            }

            $transaction = $DB->start_delegated_transaction();
            $now = time();

            $DB->insert_record(
                'local_slbridge_object',
                (object)[
                    'stationid' => $stationid,
                    'platform' => $pairing->platform,
                    'grid' => $pairing->grid,
                    'objectuuid' => $objectuuid,
                    'credentialhash' => hash(
                        'sha256', random_bytes(32)
                    ),
                    'enabled' => 0,
                    'status' => 'pending',
                    'timecreated' => $now,
                    'timemodified' => $now
                ]
            );

            $pairing->usedat = $now;
            $DB->update_record(
                'local_slbridge_pairing', $pairing
            );

            $transaction->allow_commit();
            return true;
        } finally {
            $lock->release();
        }
    }
}
