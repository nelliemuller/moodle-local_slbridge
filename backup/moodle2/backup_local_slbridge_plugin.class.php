<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

defined('MOODLE_INTERNAL') || die();

/**
 * Native Moodle course backup support for SLBridge.
 */
class backup_local_slbridge_plugin extends backup_local_plugin {

    /**
     * Include SLBridge course configuration in course backups.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {

        global $DB;

        $plugin = $this->get_plugin_element();

        $wrapper = new backup_nested_element(
            $this->get_recommended_name()
        );

        $plugin->add_child($wrapper);

        $preferences = new backup_nested_element(
            'worldpreferences',
            null,
            ['selection']
        );

        $stations = new backup_nested_element(
            'stations'
        );

        $station = new backup_nested_element(
            'station',
            ['id'],
            [
                'name',
                'slurl',
                'cmid',
                'assessment',
                'maxgrade',
                'enabled',
                'timecreated',
                'timemodified'
            ]
        );

        $wrapper->add_child($preferences);
        $wrapper->add_child($stations);
        $stations->add_child($station);

        $configname = $DB->sql_concat(
            "'courseworlds_'",
            "?"
        );

        $preferences->set_source_sql(
            "SELECT value AS selection
               FROM {config_plugins}
              WHERE plugin = 'local_slbridge'
                AND name = $configname",
            [backup::VAR_COURSEID]
        );

        $station->set_source_table(
            'local_slbridge_station',
            [
                'courseid' => backup::VAR_COURSEID
            ]
        );

        return $plugin;
    }
}
