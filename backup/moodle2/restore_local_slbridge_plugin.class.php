<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

defined('MOODLE_INTERNAL') || die();

/**
 * Native course configuration restoration for SLBridge.
 */
class restore_local_slbridge_plugin extends restore_local_plugin {

    /** @var array Stations awaiting activity remapping. */
    private $pendingstations = [];

    /**
     * Define course restoration paths.
     *
     * @return array
     */
    protected function define_course_plugin_structure() {

        return [
            new restore_path_element(
                $this->get_namefor('worldpreferences'),
                $this->get_pathfor('/worldpreferences')
            ),
            new restore_path_element(
                $this->get_namefor('station'),
                $this->get_pathfor('/stations/station')
            ),
        ];
    }

    /**
     * Restore the course platform preference.
     *
     * @param array $data
     */
    public function process_local_slbridge_worldpreferences($data) {
        global $DB;

        $data = (object)$data;

        $courseid = (int)$this->get_task()->get_courseid();

        $selection = (string)$data->selection;

        if (!in_array(
            $selection,
            ['secondlife', 'opensim', 'both'],
            true
        )) {
            return;
        }

        // Preserve existing destination course preferences.
        if ($DB->record_exists(
            'local_slbridge_station',
            ['courseid' => $courseid]
        )) {
            return;
        }

        $existing = get_config(
            'local_slbridge',
            'courseworlds_' . $courseid
        );

        if ($existing !== false) {
            return;
        }

        set_config(
            'courseworlds_' . $courseid,
            $selection,
            'local_slbridge'
        );
    }

    /**
     * Restore a learning station without its object credentials.
     *
     * @param array $data
     */
    public function process_local_slbridge_station($data) {
        global $DB;

        $data = (object)$data;

        $oldid = (int)$data->id;

        $courseid = (int)$this->get_task()->get_courseid();

        $location = \local_slbridge\world_location::parse(
            (string)$data->slurl
        );

        if (!$location) {
            return;
        }

        $assessment = (string)$data->assessment;

        if (!in_array(
            $assessment,
            ['attendance', 'automatic', 'activity'],
            true
        )) {
            $assessment = 'attendance';
        }

        $maxgrade = (float)$data->maxgrade;

        if ($maxgrade < 0 || $maxgrade > 1000) {
            $maxgrade = 0;
        }

        if ($assessment === 'automatic' && $maxgrade <= 0) {
            $assessment = 'attendance';
        }

        $oldcmid = (int)$data->cmid;

        $record = (object)[
            'courseid' => $courseid,
            'name' => \core_text::substr(
                clean_param($data->name, PARAM_TEXT),
                0,
                255
            ),
            'slurl' => (string)$data->slurl,
            'cmid' => 0,
            'assessment' => $assessment,
            'maxgrade' => $maxgrade,
            'enabled' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        if ($record->name === '') {
            $record->name = 'Restored learning station';
        }

        $newid = $DB->insert_record(
            'local_slbridge_station',
            $record
        );

        $this->set_mapping(
            'local_slbridge_station',
            $oldid,
            $newid
        );

        $this->pendingstations[$newid] = [
            'oldcmid' => $oldcmid,
            'assessment' => $assessment,
        ];
    }

    /**
     * Resolve activity references after Moodle restores activities.
     */
    public function after_restore_course() {
        global $DB;

        $courseid = (int)$this->get_task()->get_courseid();

        foreach ($this->pendingstations as $stationid => $pending) {

            $station = $DB->get_record(
                'local_slbridge_station',
                [
                    'id' => $stationid,
                    'courseid' => $courseid
                ]
            );

            if (!$station) {
                continue;
            }

            $newcmid = 0;

            if ($pending['oldcmid'] > 0) {

                $mapped = (int)$this->get_mappingid(
                    'course_module',
                    $pending['oldcmid'],
                    0
                );

                if ($mapped > 0 && $DB->record_exists(
                    'course_modules',
                    [
                        'id' => $mapped,
                        'course' => $courseid
                    ]
                )) {
                    $newcmid = $mapped;
                }
            }

            $station->cmid = $newcmid;
            $station->timemodified = time();

            // Keep restored stations disabled until teacher review.
            $station->enabled = 0;


            // An excluded activity must not leave a broken reference.
            if ($station->assessment === 'activity' &&
                $newcmid === 0) {

                $station->assessment = 'attendance';
                $station->maxgrade = 0;
            }

            $DB->update_record(
                'local_slbridge_station',
                $station
            );

            // Recreate the automatic grade item using the new station ID.
            // Historical learner grades are not copied here.
            if ($station->assessment === 'automatic') {

                require_once(
                    __DIR__ . '/../../classes/grade_service.php'
                );

                $result = \local_slbridge_grade_service::update_station(
                    $station
                );

                if ($result !== GRADE_UPDATE_OK) {
                    throw new \restore_step_exception(
                        'slbridge_grade_restore_failed',
                        $stationid
                    );
                }

                require_once(
                    $GLOBALS['CFG']->libdir . '/grade/grade_item.php'
                );

                $item = \grade_item::fetch([
                    'courseid' => $courseid,
                    'itemtype' => 'local',
                    'itemmodule' => 'slbridge',
                    'iteminstance' => $stationid,
                    'itemnumber' => 0,
                ]);

                if (!$item) {
                    throw new \restore_step_exception(
                        'slbridge_grade_item_missing',
                        $stationid
                    );
                }

                // Restored stations remain disabled until teacher review.
                $item->set_hidden(1);
            }

        }
    }
}
