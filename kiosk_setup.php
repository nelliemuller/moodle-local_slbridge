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
// If not, see <https://www.gnu.org/licenses/>.

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/slbridge/kiosk_setup.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title('SLBridge kiosk setup');
$PAGE->set_heading('SLBridge kiosk setup');

echo $OUTPUT->header();

echo $OUTPUT->heading('Kiosk API configuration');

echo html_writer::tag(
    'p',
    'Generate and manage the secure API secret used by your trusted Second Life kiosk.'
);

$existing = (string)get_config('local_slbridge', 'apikey');
$hassecret = ($existing !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && optional_param('generate', 0, PARAM_BOOL)) {
    require_sesskey();

    $confirm = optional_param('confirmreplace', 0, PARAM_BOOL);

    if ($hassecret && !$confirm) {
        throw new moodle_exception(
            'An existing API secret is configured. Confirm replacement before continuing.'
        );
    }

    $secret = \local_slbridge\kiosk_secret::generate();

    set_config('apikey', $secret, 'local_slbridge');

    echo $OUTPUT->notification(
        'API secret generated and saved successfully.',
        'notifysuccess'
    );

    echo html_writer::tag(
        'p',
        'Copy this secret now and store it securely. Replacing it will invalidate the previous kiosk secret.'
    );

    echo html_writer::tag(
        'textarea',
        s($secret),
        ['readonly' => 'readonly', 'rows' => 3, 'cols' => 75]
    );

    $hassecret = true;
}

if ($hassecret) {
    echo $OUTPUT->notification(
        'An API secret is already configured.',
        'notifymessage'
    );
}

echo html_writer::start_tag(
    'form',
    [
        'method' => 'post',
        'action' => (new moodle_url('/local/slbridge/kiosk_setup.php'))->out(false)
    ]
);

echo html_writer::empty_tag(
    'input',
    ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]
);

echo html_writer::empty_tag(
    'input',
    ['type' => 'hidden', 'name' => 'generate', 'value' => '1']
);

if ($hassecret) {
    echo html_writer::start_div('mb-2');

    echo html_writer::checkbox(
        'confirmreplace',
        1,
        false,
        'I understand that replacing the secret will disconnect kiosks using the previous secret.',
        ['class' => 'me-2']
    );

    echo html_writer::end_div();
}

echo html_writer::empty_tag(
    'input',
    [
        'type' => 'submit',
        'class' => 'btn btn-secondary mt-2',
        'value' => $hassecret ? 'Replace API Secret' : 'Generate API Secret'
    ]
);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
