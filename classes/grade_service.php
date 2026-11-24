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

final class local_slbridge_grade_service {

    public static function update_station(
        \stdClass $station
    ): int {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        if ($station->assessment !== 'automatic') {
            return GRADE_UPDATE_OK;
        }

        $maxgrade = (float)$station->maxgrade;

        if ($maxgrade <= 0 || $maxgrade > 1000) {
            throw new \invalid_parameter_exception(
                'Invalid station maximum grade'
            );
        }

        return grade_update(
            'local/slbridge',
            (int)$station->courseid,
            'local',
            'slbridge',
            (int)$station->id,
            0,
            null,
            [
                'itemname' => $station->name,
                'gradetype' => GRADE_TYPE_VALUE,
                'grademin' => 0,
                'grademax' => $maxgrade
            ]
        );
    }


    public static function sync_station(
        \stdClass $station
    ): void {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $params = [
            'courseid' => (int)$station->courseid,
            'itemtype' => 'local',
            'itemmodule' => 'slbridge',
            'iteminstance' => (int)$station->id,
            'itemnumber' => 0
        ];

        if ($station->assessment === 'automatic') {
            $result = self::update_station($station);

            if ($result !== GRADE_UPDATE_OK) {
                throw new \moodle_exception(
                    'Unable to synchronize SLBridge grade item'
                );
            }

            $item = \grade_item::fetch($params);

            if (!$item) {
                throw new \moodle_exception(
                    'SLBridge grade item not found'
                );
            }

            if ($item->is_hidden()) {
                $item->set_hidden(0);
            }

            return;
        }

        $item = \grade_item::fetch($params);

        if ($item && !$item->is_hidden()) {
            $item->set_hidden(1);
        }
    }

    public static function award_visit(
        \stdClass $station,
        int $userid
    ): int {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        if ($station->assessment !== 'automatic') {
            return GRADE_UPDATE_OK;
        }

        $maxgrade = (float)$station->maxgrade;

        if ($maxgrade <= 0 || $maxgrade > 1000) {
            throw new \invalid_parameter_exception(
                'Invalid station maximum grade'
            );
        }

        return grade_update(
            'local/slbridge',
            (int)$station->courseid,
            'local',
            'slbridge',
            (int)$station->id,
            0,
            [
                (int)$userid => [
                    'userid' => (int)$userid,
                    'rawgrade' => $maxgrade
                ]
            ],
            [
                'itemname' => $station->name,
                'gradetype' => GRADE_TYPE_VALUE,
                'grademin' => 0,
                'grademax' => $maxgrade
            ]
        );
    }
}
