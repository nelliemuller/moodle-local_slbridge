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

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/grade_service.php');

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/slbridge:manage', $context);

$station = null;
if ($id) {
    $station = $DB->get_record(
        'local_slbridge_station',
        ['id' => $id, 'courseid' => $courseid],
        '*',
        MUST_EXIST
    );
}

$params = ['courseid' => $courseid];
if ($id) {
    $params['id'] = $id;
}

$url = new moodle_url('/local/slbridge/station_edit.php', $params);
$listurl = new moodle_url('/local/slbridge/stations.php',
    ['courseid' => $courseid]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('addstation', 'local_slbridge'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_slbridge\form\station_form(
    $url, ['courseid' => $courseid]
);

if ($station) {
    $form->set_data($station);
}

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($data = $form->get_data()) {

    $location = \local_slbridge\world_location::parse(
        $data->slurl
    );

    if (!$location ||
        !\local_slbridge\course_worlds::allows(
            $courseid,
            $location['platform']
        )) {
        redirect(
            $url,
            get_string('worldnotenabled', 'local_slbridge'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $record = (object)[
        'courseid' => $courseid,
        'name' => trim($data->name),
        'slurl' => trim($data->slurl),
        'cmid' => (int)$data->cmid,
        'assessment' => $data->assessment,
        'maxgrade' => $data->assessment === 'automatic'
            ? (float)$data->maxgrade : 0,
        'timemodified' => time()
    ];

    if ($station) {
        $record->id = $station->id;
        $DB->update_record('local_slbridge_station', $record);
    } else {
        $record->enabled = 1;
        $record->timecreated = time();
        $record->id = $DB->insert_record('local_slbridge_station', $record);
    }

    {
        $saved = $DB->get_record(
            'local_slbridge_station',
            ['id' => $record->id],
            '*',
            MUST_EXIST
        );

        $result = local_slbridge_grade_service::sync_station($saved);

    }

    redirect($listurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(
    $station ? get_string('edit') :
        get_string('addstation', 'local_slbridge')
);
$form->display();
echo $OUTPUT->footer();
