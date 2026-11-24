<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$stationid = required_param('stationid', PARAM_INT);

$course = get_course($courseid);

require_login($course);
require_sesskey();

$context = context_course::instance($courseid);

require_capability(
    'local/slbridge:manage',
    $context
);

$station = $DB->get_record(
    'local_slbridge_station',
    [
        'id' => $stationid,
        'courseid' => $courseid
    ],
    '*',
    MUST_EXIST
);

$location = \local_slbridge\world_location::parse(
    $station->slurl
);

if (!$location ||
    !\local_slbridge\course_worlds::allows(
        $courseid,
        $location['platform']
    )) {
    throw new invalid_parameter_exception(
        'Invalid or disabled virtual world location'
    );
}

// Both supported platforms use the signed course kiosk protocol.
if (!in_array(
    $location['platform'],
    ['secondlife', 'opensim'],
    true
)) {
    throw new invalid_parameter_exception(
        'Unsupported virtual world platform'
    );
}

$filename = __DIR__ .
    '/kiosk/slbridge_course_kiosk.lsl';

if (!is_readable($filename)) {
    throw new moodle_exception('filenotfound');
}

$script = file_get_contents($filename);

if ($script === false) {
    throw new moodle_exception('filenotfound');
}

$baseurl = rtrim($CFG->wwwroot, '/') .
    '/local/slbridge';

$courseurl = (new moodle_url(
    '/course/view.php',
    ['id' => $courseid]
))->out(false);

function local_slbridge_lsl_string(string $value): string {
    return str_replace(
        ["\\", '"', "\r", "\n"],
        ["\\\\", '\\"', '', ''],
        $value
    );
}

$script = str_replace(
    [
        'https://YOUR-MOODLE-SITE.example/local/slbridge',
        'https://YOUR-MOODLE-SITE.example/course/view.php?id=0'
    ],
    [
        local_slbridge_lsl_string($baseurl),
        local_slbridge_lsl_string($courseurl)
    ],
    $script
);

$downloadname = 'slbridge_' .
    $location['platform'] . '_course_' .
    $courseid . '_station_' . $stationid . '.lsl';

\core\session\manager::write_close();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

header(
    'Content-Disposition: attachment; filename="' .
    $downloadname . '"'
);

echo $script;
exit;
