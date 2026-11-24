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

final class local_slbridge_slurl {
    public static function parse(string $url): ?array {
        $parts = parse_url(trim($url));

        if (!$parts ||
            ($parts['scheme'] ?? '') !== 'https' ||
            ($parts['host'] ?? '') !== 'maps.secondlife.com' ||
            isset($parts['user'], $parts['pass']) ||
            isset($parts['port']) ||
            isset($parts['query']) ||
            isset($parts['fragment'])) {
            return null;
        }

        if (!preg_match(
            '~^/secondlife/([^/]+)/(\d+)/(\d+)/(\d+)/?$~',
            $parts['path'] ?? '',
            $m
        )) {
            return null;
        }

        $region = rawurldecode($m[1]);
        $x = (int)$m[2];
        $y = (int)$m[3];
        $z = (int)$m[4];

        if ($region === '' || $x > 255 ||
            $y > 255 || $z > 4096) {
            return null;
        }

        return [
            'region' => $region,
            'x' => $x,
            'y' => $y,
            'z' => $z
        ];
    }
}
