<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/course_worlds.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

require_capability(
    'local/slbridge:manage',
    $context
);

$url = new moodle_url(
    '/local/slbridge/course_settings.php',
    ['courseid' => $courseid]
);

$listurl = new moodle_url(
    '/local/slbridge/stations.php',
    ['courseid' => $courseid]
);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(
    get_string('courseworldsettings', 'local_slbridge')
);
$PAGE->set_heading(format_string($course->fullname));

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $selection = required_param('worlds', PARAM_ALPHA);

    if (\local_slbridge\course_worlds::save(
        $courseid,
        $selection
    )) {
        redirect($listurl, get_string('changessaved'));
    }

    $error = true;
}

$current = \local_slbridge\course_worlds::get($courseid);

echo $OUTPUT->header();

echo $OUTPUT->heading(
    get_string('courseworldsettings', 'local_slbridge')
);

if ($error) {
    echo $OUTPUT->notification(
        get_string('courseworldconflict', 'local_slbridge'),
        'error'
    );
}

echo html_writer::tag(
    'p',
    get_string('courseworldsettingsintro', 'local_slbridge')
);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false)
]);

foreach ([
    'sesskey' => sesskey(),
    'courseid' => $courseid
] as $name => $value) {
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => $name,
        'value' => $value
    ]);
}

$options = [
    'secondlife' => get_string(
        'worldsecondlife', 'local_slbridge'
    ),
    'opensim' => get_string(
        'worldopensim', 'local_slbridge'
    ),
    'both' => get_string(
        'worldboth', 'local_slbridge'
    )
];

echo html_writer::tag(
    'label',
    get_string('worldplatform', 'local_slbridge'),
    ['for' => 'slbridge-worlds']
);

echo html_writer::select(
    $options,
    'worlds',
    $current,
    false,
    [
        'id' => 'slbridge-worlds',
        'class' => 'form-select mb-3'
    ]
);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary'
]);

echo ' ';

echo html_writer::link(
    $listurl,
    get_string('cancel'),
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
