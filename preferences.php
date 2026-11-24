<?php
// SLBridge virtual world connections.
// SPDX-License-Identifier: GPL-3.0-or-later.

require_once(__DIR__ . '/../../config.php');

require_login();

global $DB, $USER, $PAGE, $OUTPUT;

$context = context_user::instance($USER->id);

$url = new moodle_url(
    '/local/slbridge/preferences.php'
);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(
    get_string('virtualworldconnections', 'local_slbridge')
);
$PAGE->set_heading(
    get_string('virtualworldconnections', 'local_slbridge')
);


// Revoke only the selected avatar connection.
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        optional_param('revokeid', 0, PARAM_INT)) {
    require_sesskey();

    $revokeid = required_param('revokeid', PARAM_INT);

    $link = $DB->get_record(
        'local_slbridge_link',
        [
            'id' => $revokeid,
            'userid' => $USER->id,
            'revokedat' => 0
        ],
        '*',
        MUST_EXIST
    );

    $DB->set_field(
        'local_slbridge_link',
        'revokedat',
        time(),
        ['id' => $link->id]
    );

    redirect(
        new moodle_url('/local/slbridge/preferences.php'),
        'Avatar connection revoked.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$links = $DB->get_records(
    'local_slbridge_link',
    [
        'userid' => $USER->id,
        'revokedat' => 0
    ],
    'id DESC'
);

echo $OUTPUT->header();

echo $OUTPUT->heading(
    get_string('virtualworldconnections', 'local_slbridge')
);

echo html_writer::tag(
    'p',
    get_string('connectionsintro', 'local_slbridge')
);

if (!$links) {
    echo $OUTPUT->notification(
        get_string('noconnections', 'local_slbridge'),
        'info'
    );
} else {
    $table = new html_table();

    $table->head = [
        get_string('avatar', 'local_slbridge'),
        get_string('platform', 'local_slbridge'),
        get_string('status', 'local_slbridge'),
        get_string('actions', 'moodle')
    ];

    foreach ($links as $link) {
        $platform = !empty($link->platform)
            ? $link->platform
            : get_string('notavailable', 'local_slbridge');

        if (!empty($link->grid)) {
            $platform .= ' (' . $link->grid . ')';
        }

        $status = !empty($link->verifiedat)
            ? get_string('verified', 'local_slbridge')
            : get_string('pending', 'local_slbridge');

        $form = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url->out(false)
        ]);

        $form .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]);

        $form .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'revokeid',
            'value' => $link->id
        ]);

        $form .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('revokeconnection', 'local_slbridge'),
            'class' => 'btn btn-outline-danger btn-sm'
        ]);

        $form .= html_writer::end_tag('form');

        $table->data[] = [
            s($link->avataruuid),
            s($platform),
            s($status),
            $form
        ];
    }

    echo html_writer::table($table);
}

$linkurl = new moodle_url(
    '/local/slbridge/link.php'
);

echo html_writer::link(
    $linkurl,
    get_string('manageavatarlink', 'local_slbridge'),
    ['class' => 'btn btn-primary']
);

echo $OUTPUT->footer();
