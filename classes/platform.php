<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

defined('MOODLE_INTERNAL') || die();

final class local_slbridge_platform {

    public const SECOND_LIFE = 'secondlife';
    public const OPENSIM = 'opensim';

    public static function normalize(
        string $platform,
        string $grid
    ): array {
        $platform = strtolower(trim($platform));
        $grid = strtolower(trim($grid));

        if ($platform === self::SECOND_LIFE) {
            if ($grid !== '' && $grid !== 'agni') {
                throw new \invalid_parameter_exception(
                    'Invalid Second Life grid'
                );
            }

            return [self::SECOND_LIFE, 'agni'];
        }

        if ($platform === self::OPENSIM) {
            if ($grid === '' ||
                strlen($grid) > 255 ||
                !preg_match(
                    '/^[a-z0-9][a-z0-9.:-]*$/',
                    $grid
                )) {
                throw new \invalid_parameter_exception(
                    'Invalid OpenSimulator grid'
                );
            }

            return [self::OPENSIM, $grid];
        }

        throw new \invalid_parameter_exception(
            'Unsupported virtual world platform'
        );
    }
}
