<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

require_login();
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

global $DB;

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid < 0) {
    throw new invalid_parameter_exception('Invalid course');
}

if ($courseid === 0) {
    $sql = "SELECT c.id, c.fullname
              FROM {course} c
             WHERE c.id > 1
          ORDER BY c.fullname, c.id";

    $courses = $DB->get_records_sql($sql);

    $result = [];

    foreach ($courses as $course) {
        $result[] = [
            'id' => (int)$course->id,
            'name' => format_string(
                $course->fullname,
                true,
                ['context' => context_course::instance($course->id)]
            ),
        ];
    }

    echo json_encode(['ok' => true, 'courses' => $result]);
    exit;
}

if (!$DB->record_exists('course', ['id' => $courseid])) {
    throw new invalid_parameter_exception('Course not found');
}

$sessions = $DB->get_records(
    'local_slbridge_session',
    ['courseid' => $courseid],
    'starts DESC, id DESC'
);

$result = [];

foreach ($sessions as $session) {
    $start = userdate((int)$session->starts);
    $end = userdate((int)$session->ends);

    $result[] = [
        'id' => (int)$session->id,
        'label' => $start . ' – ' . $end,
        'active' => (
            time() >= (int)$session->starts &&
            time() <= (int)$session->ends
        ),
    ];
}

echo json_encode(['ok' => true, 'sessions' => $result]);
