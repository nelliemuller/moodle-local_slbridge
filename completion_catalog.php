<?php
// SLBridge completion-enabled course content catalog.
// SPDX-License-Identifier: GPL-3.0-or-later.

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$courseid = required_param('courseid', PARAM_INT);

if ($courseid <= SITEID) {
    throw new invalid_parameter_exception('Invalid course');
}

$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);

$modinfo = get_fast_modinfo($course);
$completion = new completion_info($course);

$items = [];

foreach ($modinfo->get_cms() as $cm) {
    if (empty($cm->completion)) {
        continue;
    }

    // Do not expose hidden course content through this catalog.
    if (!$cm->uservisible) {
        continue;
    }

    $mode = (int)$cm->completion;

    if ($mode === COMPLETION_TRACKING_MANUAL) {
        $description = 'Manual completion';
    } else if ($mode === COMPLETION_TRACKING_AUTOMATIC) {
        $description = 'Automatic completion using Moodle conditions';
    } else {
        continue;
    }

    $items[] = [
        'cmid' => (int)$cm->id,
        'name' => format_string(
            $cm->name,
            true,
            ['context' => context_module::instance($cm->id)]
        ),
        'module' => $cm->modname,
        'completionmode' => $mode,
        'description' => $description,
    ];
}

echo json_encode([
    'ok' => true,
    'courseid' => $courseid,
    'completionenabled' => $completion->is_enabled(),
    'items' => array_values($items),
]);
