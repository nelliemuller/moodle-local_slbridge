<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/world_location.php');

final class course_worlds {

    public static function get(int $courseid): string {
        $value = get_config(
            'local_slbridge',
            'courseworlds_' . $courseid
        );

        return in_array(
            $value,
            ['secondlife', 'opensim', 'both'],
            true
        ) ? $value : 'both';
    }

    public static function allows(
        int $courseid,
        string $platform
    ): bool {

        if (!in_array(
            $platform,
            ['secondlife', 'opensim'],
            true
        )) {
            return false;
        }

        $selection = self::get($courseid);

        return $selection === 'both' ||
            $selection === $platform;
    }

    public static function save(
        int $courseid,
        string $selection
    ): bool {
        global $DB;

        require_capability(
            'local/slbridge:manage',
            \context_course::instance($courseid)
        );

        if (!in_array(
            $selection,
            ['secondlife', 'opensim', 'both'],
            true
        )) {
            return false;
        }

        $stations = $DB->get_records(
            'local_slbridge_station',
            ['courseid' => $courseid, 'enabled' => 1]
        );

        foreach ($stations as $station) {

            $location = world_location::parse(
                $station->slurl
            );

            if ($selection !== 'both' &&
                (!$location ||
                 $location['platform'] !== $selection)) {
                return false;
            }
        }

        return set_config(
            'courseworlds_' . $courseid,
            $selection,
            'local_slbridge'
        );
    }
}
