<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/platform.php');

final class local_slbridge_kiosk_identity {

    public static function registered_object(
        string $objectuuid
    ): ?\stdClass {
        global $DB;

        $objectuuid = strtolower(trim($objectuuid));

        if (!preg_match(
            '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
            $objectuuid
        )) {
            return null;
        }

        $objects = $DB->get_records(
            'local_slbridge_object',
            [
                'objectuuid' => $objectuuid,
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

        if ($object->platform === 'legacy') {
            return null;
        }

        try {
            [$platform, $grid] =
                local_slbridge_platform::normalize(
                    $object->platform,
                    $object->grid
                );
        } catch (\invalid_parameter_exception $e) {
            return null;
        }

        $object->platform = $platform;
        $object->grid = $grid;

        return $object;
    }
}
