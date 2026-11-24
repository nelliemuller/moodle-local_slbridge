<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles Moodle course lifecycle events.
 */
final class observer {

    /**
     * Remove SLBridge records belonging to a deleted course.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(
        \core\event\course_deleted $event
    ): void {
        global $DB;

        $courseid = (int)$event->objectid;

        if ($courseid <= 0 || $courseid === SITEID) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();

        $stations = $DB->get_records(
            'local_slbridge_station',
            ['courseid' => $courseid],
            'id ASC',
            'id'
        );

        foreach ($stations as $station) {

            $DB->delete_records(
                'local_slbridge_pairing',
                ['stationid' => $station->id]
            );

            $DB->delete_records(
                'local_slbridge_object',
                ['stationid' => $station->id]
            );
        }

        $DB->delete_records(
            'local_slbridge_visit',
            ['courseid' => $courseid]
        );

        $DB->delete_records(
            'local_slbridge_station',
            ['courseid' => $courseid]
        );

        $sessions = $DB->get_records(
            'local_slbridge_session',
            ['courseid' => $courseid],
            'id ASC',
            'id'
        );

        foreach ($sessions as $session) {
            $DB->delete_records(
                'local_slbridge_attend',
                ['sessionid' => $session->id]
            );
        }

        $DB->delete_records(
            'local_slbridge_session',
            ['courseid' => $courseid]
        );

        unset_config(
            'courseworlds_' . $courseid,
            'local_slbridge'
        );

        $transaction->allow_commit();
    }
}
