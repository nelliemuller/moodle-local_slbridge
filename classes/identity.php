<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/platform.php');

final class local_slbridge_identity {

    public static function verified_link(
        string $platform,
        string $grid,
        string $avataruuid
    ): ?\stdClass {
        global $DB;

        [$platform, $grid] =
            local_slbridge_platform::normalize(
                $platform,
                $grid
            );

        $avataruuid = strtolower(trim($avataruuid));

        if (!preg_match(
            '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
            $avataruuid
        )) {
            return null;
        }

        $links = $DB->get_records(
            'local_slbridge_link',
            [
                'platform' => $platform,
                'grid' => $grid,
                'avataruuid' => $avataruuid,
                'revokedat' => 0
            ],
            'id ASC',
            '*',
            0,
            2
        );

        if (count($links) !== 1) {
            return null;
        }

        $link = reset($links);

        if (empty($link->verifiedat)) {
            return null;
        }

        return $link;
    }

    public static function verified_user(
        string $platform,
        string $grid,
        string $avataruuid
    ): ?int {
        $link = self::verified_link(
            $platform,
            $grid,
            $avataruuid
        );

        return $link ? (int)$link->userid : null;
    }
}
