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
require_once(__DIR__ . '/grade_service.php');
require_once(__DIR__ . '/identity.php');

final class local_slbridge_station_visit_service {

    public static function record(
        int $stationid,
        string $avataruuid,
        string $platform,
        string $grid
    ): bool {
        global $DB;

        $avataruuid = strtolower(trim($avataruuid));

        if (!preg_match(
            '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
            $avataruuid
        )) {
            return false;
        }

        $station = $DB->get_record(
            'local_slbridge_station',
            ['id' => $stationid, 'enabled' => 1]
        );

        if (!$station) {
            return false;
        }

        // Validate the registered object's virtual world
        // against the learning station's configured location.
        require_once(__DIR__ . '/world_location.php');
        require_once(__DIR__ . '/platform.php');

        $location = \local_slbridge\world_location::parse(
            $station->slurl
        );

        if (!$location) {
            return false;
        }

        try {
            [$platform, $grid] =
                local_slbridge_platform::normalize(
                    $platform,
                    $grid
                );
        } catch (\invalid_parameter_exception $e) {
            return false;
        }

        if ($location['platform'] !== $platform ||
            $location['grid'] !== $grid) {
            return false;
        }


        if (!\local_slbridge\course_worlds::allows(
            (int)$station->courseid,
            $platform
        )) {
            return false;
        }

        $link = local_slbridge_identity::verified_link(
            $platform,
            $grid,
            $avataruuid
        );

        if (!$link || empty($link->verifiedat)) {
            return false;
        }

        $context = \context_course::instance($station->courseid);

        if (!is_enrolled($context, $link->userid)) {
            return false;
        }

        $conditions = [
            'courseid' => $station->courseid,
            'stationid' => $stationid,
            'userid' => $link->userid
        ];

        $now = time();

        $existing = $DB->get_record(
            'local_slbridge_visit',
            $conditions
        );

        if ($existing) {
            $DB->set_field(
                'local_slbridge_visit',
                'lastseen',
                $now,
                ['id' => $existing->id]
            );
            self::grade_visit($station, (int)$link->userid);
            return true;
        }

        try {
            $DB->insert_record(
                'local_slbridge_visit',
                (object)[
                    'courseid' => $station->courseid,
                    'stationid' => $stationid,
                    'userid' => $link->userid,
                    'avataruuid' => $avataruuid,
                    'firstseen' => $now,
                    'lastseen' => $now
                ]
            );
        } catch (\dml_write_exception $e) {
            // Another request may have inserted the same visit.
            $existing = $DB->get_record(
                'local_slbridge_visit',
                $conditions
            );

            if (!$existing) {
                throw $e;
            }

            $DB->set_field(
                'local_slbridge_visit',
                'lastseen',
                $now,
                ['id' => $existing->id]
            );
        }

        self::grade_visit($station, (int)$link->userid);
        return true;
    }

    private static function grade_visit(
        \stdClass $station,
        int $userid
    ): void {
        if ($station->assessment !== 'automatic') {
            return;
        }

        $result = local_slbridge_grade_service::award_visit(
            $station,
            $userid
        );

        if ($result !== GRADE_UPDATE_OK) {
            throw new \moodle_exception(
                'Failed to award SLBridge participation grade'
            );
        }
    }
}
