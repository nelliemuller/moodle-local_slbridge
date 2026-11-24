<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/platform.php');
require_once(__DIR__ . '/world_location.php');

final class avatar_kiosk {

    public static function registered(
        string $objectuuid,
        string $platform,
        string $grid
    ): ?\stdClass {
        global $DB;

        $objectuuid = strtolower(trim($objectuuid));

        if (!preg_match(
            '/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D',
            $objectuuid
        )) {
            return null;
        }

        try {
            [$platform, $grid] =
                \local_slbridge_platform::normalize(
                    $platform,
                    $grid
                );
        } catch (\invalid_parameter_exception $e) {
            return null;
        }

        $objects = $DB->get_records(
            'local_slbridge_object',
            [
                'objectuuid' => $objectuuid,
                'platform' => $platform,
                'grid' => $grid,
                'enabled' => 1,
                'status' => 'active'
            ],
            'id ASC',
            '*',
            0,
            2
        );

        if (count($objects) !== 1) {
            return null;
        }

        $object = reset($objects);

        if (empty($object->credentialenc)) {
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

        $location = world_location::parse(
            $station->slurl
        );

        if (!$location ||
            $location['platform'] !== $platform ||
            $location['grid'] !== $grid) {
            return null;
        }


        if (!course_worlds::allows(
            (int)$station->courseid,
            $platform
        )) {
            return null;
        }

        return (object)[
            'objectid' => (int)$object->id,
            'objectuuid' => $objectuuid,
            'stationid' => (int)$station->id,
            'courseid' => (int)$station->courseid,
            'platform' => $platform,
            'grid' => $grid
        ];
    }

    public static function eligible(
        \stdClass $kiosk,
        int $userid
    ): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }

        $user = $DB->get_record(
            'user',
            [
                'id' => $userid,
                'deleted' => 0,
                'suspended' => 0
            ],
            'id'
        );

        if (!$user) {
            return false;
        }

        $context = \context_course::instance(
            $kiosk->courseid
        );

        return is_enrolled(
            $context,
            $userid,
            '',
            true
        );
    }

    public static function legacy_matches(
        string $objectuuid,
        string $platform,
        string $grid
    ): bool {

        $registered = strtolower(
            trim((string)get_config(
                'local_slbridge',
                'objectuuid'
            ))
        );

        if ($registered === '' ||
            !hash_equals(
                $registered,
                strtolower(trim($objectuuid))
            )) {
            return false;
        }

        try {
            [$expectedplatform, $expectedgrid] =
                \local_slbridge_platform::normalize(
                    (string)(get_config(
                        'local_slbridge',
                        'kioskplatform'
                    ) ?: 'secondlife'),
                    (string)(get_config(
                        'local_slbridge',
                        'kioskgrid'
                    ) ?: 'agni')
                );

            [$platform, $grid] =
                \local_slbridge_platform::normalize(
                    $platform,
                    $grid
                );

        } catch (\invalid_parameter_exception $e) {
            return false;
        }

        return $platform === $expectedplatform &&
            $grid === $expectedgrid;
    }
}
