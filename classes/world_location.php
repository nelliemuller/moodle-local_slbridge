<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

final class world_location {

    public static function parse(string $location): ?array {
        $location = trim($location);

        if ($location === '' || strlen($location) > 2048) {
            return null;
        }

        $url = parse_url($location);

        if ($url === false ||
            isset($url['user']) ||
            isset($url['pass']) ||
            isset($url['query']) ||
            isset($url['fragment'])) {
            return null;
        }

        $scheme = strtolower($url['scheme'] ?? '');
        $host = strtolower($url['host'] ?? '');

        $parts = explode(
            '/',
            trim($url['path'] ?? '', '/')
        );

        if ($scheme === 'hop') {

            if (!filter_var(
                $host,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            )) {
                return null;
            }

            $port = $url['port'] ?? null;

            if ($port !== null &&
                ($port < 1 || $port > 65535)) {
                return null;
            }

            $platform = 'opensim';
            $grid = $host;

            if ($port !== null) {
                $grid .= ':' . $port;
            }

        } elseif (
            in_array($scheme, ['http', 'https'], true) &&
            in_array(
                $host,
                ['maps.secondlife.com', 'slurl.com',
                 'www.slurl.com'],
                true
            )
        ) {

            if (($parts[0] ?? '') !== 'secondlife') {
                return null;
            }

            array_shift($parts);

            $platform = 'secondlife';
            $grid = 'agni';

        } elseif (
            $scheme === 'secondlife' &&
            $host === ''
        ) {

            $platform = 'secondlife';
            $grid = 'agni';

        } else {
            return null;
        }

        if (count($parts) !== 4) {
            return null;
        }

        $region = rawurldecode($parts[0]);

        if ($region === '' ||
            strlen($region) > 255 ||
            preg_match('/[\x00-\x1F\x7F\/]/', $region)) {
            return null;
        }

        foreach (array_slice($parts, 1) as $coordinate) {
            if (!preg_match(
                '/^[0-9]+(?:\.[0-9]+)?$/D',
                $coordinate
            )) {
                return null;
            }
        }

        return [
            'platform' => $platform,
            'grid' => $grid,
            'region' => $region,
            'x' => (float)$parts[1],
            'y' => (float)$parts[2],
            'z' => (float)$parts[3],
            'location' => $location,
        ];
    }
}
