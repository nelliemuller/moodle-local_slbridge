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

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/slbridge:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/slbridge/stations.php',
        ['courseid' => $courseid])
);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_slbridge'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(
    get_string('worldstations', 'local_slbridge')
);

echo $OUTPUT->notification(
    get_string('worldstationsintro', 'local_slbridge'),
    'info'
);


$settingsurl = new moodle_url(
    '/local/slbridge/course_settings.php',
    ['courseid' => $courseid]
);

echo $OUTPUT->single_button(
    $settingsurl,
    get_string('courseworldsettings', 'local_slbridge'),
    'get'
);

$currentworlds = \local_slbridge\course_worlds::get(
    $courseid
);

$worldlabels = [
    'secondlife' => 'worldsecondlife',
    'opensim' => 'worldopensim',
    'both' => 'worldboth'
];

echo html_writer::tag(
    'p',
    get_string('worldplatform', 'local_slbridge') .
    ': ' .
    get_string(
        $worldlabels[$currentworlds],
        'local_slbridge'
    )
);

$addurl = new moodle_url('/local/slbridge/station_edit.php',
    ['courseid' => $courseid]);

echo $OUTPUT->single_button(
    $addurl,
    get_string('addstation', 'local_slbridge')
);


$stations = $DB->get_records(
    'local_slbridge_station',
    ['courseid' => $courseid],
    'id ASC'
);

$table = new html_table();
$table->head = [
    get_string('stationname', 'local_slbridge'),
    get_string('worldplatform', 'local_slbridge'),
    get_string('worldgrid', 'local_slbridge'),
    get_string('worldlocation', 'local_slbridge'),
    get_string('stationassessment', 'local_slbridge'),
    get_string('actions')
];

foreach ($stations as $station) {
    $location = \local_slbridge\world_location::parse(
        $station->slurl
    );

    $platform = $location
        ? get_string(
            $location['platform'] === 'opensim'
                ? 'worldopensim' : 'worldsecondlife',
            'local_slbridge'
        )
        : get_string('worldunknown', 'local_slbridge');

    $grid = $location ? $location['grid'] : '';

    $table->data[] = [
        format_string($station->name),
        s($platform),
        s($grid),
        $location
            ? html_writer::link(
                $location['location'],
                get_string('visitworldstation', 'local_slbridge'),
                ['target' => '_blank',
                 'rel' => 'noopener noreferrer']
            )
            : s($station->slurl),
        s($station->assessment),
        html_writer::link(
            new moodle_url('/local/slbridge/station_edit.php', [
                'courseid' => $courseid,
                'id' => $station->id
            ]),
            get_string('edit')
        ) . ' | ' . html_writer::link(
            new moodle_url('/local/slbridge/object_pair.php', [
                'courseid' => $courseid,
                'stationid' => $station->id
            ]),
            'Register Object'
        )
    ];
}

if ($stations) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(
        'No learning stations have been configured.',
        'info'
    );
}

echo $OUTPUT->footer();
