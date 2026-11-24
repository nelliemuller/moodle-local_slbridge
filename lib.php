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
function local_slbridge_uuid_valid($value) {
 return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
}
function local_slbridge_link_for_user($userid) {
 global $DB;

 $links = $DB->get_records(
     'local_slbridge_link',
     [
         'userid' => $userid,
         'revokedat' => 0
     ],
     'id DESC',
     '*',
     0,
     1
 );

 return $links ? reset($links) : false;
}

/**
 * Extend Moodle user settings navigation with SLBridge connections.
 *
 * @param navigation_node $parentnode
 * @param stdClass $user
 * @param context_user $context
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_slbridge_extend_navigation_user_settings(
    $parentnode,
    $user,
    $context,
    $course,
    $coursecontext
) {
    global $USER;

    if ((int)$user->id !== (int)$USER->id) {
        return;
    }

    $url = new moodle_url('/local/slbridge/preferences.php');

    $parentnode->add(
        get_string('virtualworldconnections', 'local_slbridge'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_slbridge_connections'
    );
}


/**
 * Add SLBridge management to the course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_slbridge_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {

    if ((int)$course->id === SITEID) {
        return;
    }

    if (!has_capability('local/slbridge:manage', $context)) {
        return;
    }

    $url = new moodle_url(
        '/local/slbridge/stations.php',
        ['courseid' => $course->id]
    );

    $navigation->add(
        'SLBridge',
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_slbridge_teacher',
        new pix_icon('i/settings', '')
    );
}
