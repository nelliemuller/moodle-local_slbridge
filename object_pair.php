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
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/pairing_requests.php');
require_once(__DIR__ . '/classes/credential_service.php');

// SLBRIDGE_SECURE_PROVISIONING_V1
if (!is_https()) {
    throw new \moodle_exception(
        'HTTPS is required for object credential management'
    );
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');


$courseid = required_param('courseid', PARAM_INT);
$stationid = required_param('stationid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/slbridge:manage', $context);

$station = $DB->get_record(
    'local_slbridge_station',
    ['id' => $stationid, 'courseid' => $courseid],
    '*',
    MUST_EXIST
);

$url = new moodle_url('/local/slbridge/object_pair.php', [
    'courseid' => $courseid,
    'stationid' => $stationid
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title('Register Second Life Object');
$PAGE->set_heading(format_string($course->fullname));


$confirmid = optional_param('confirmid', 0, PARAM_INT);

if ($confirmid) {
    require_sesskey();

    local_slbridge_pairing_requests::confirm(
        $confirmid,
        $stationid
    );

    redirect($url);
}


$provisionedsecret = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    optional_param('provisionid', 0, PARAM_INT)) {
    require_sesskey();

    $objectid = required_param('provisionid', PARAM_INT);

    $DB->get_record(
        'local_slbridge_object',
        ['id' => $objectid, 'stationid' => $stationid],
        'id',
        MUST_EXIST
    );

    $provisionedsecret =
        local_slbridge_credential_service::provision($objectid);
}

$code = null;

if (optional_param('generate', 0, PARAM_BOOL)) {
    require_sesskey();
    $platform = required_param('platform', PARAM_ALPHA);
    $grid = optional_param('grid', '', PARAM_RAW_TRIMMED);

    if ($platform === 'secondlife') {
        $grid = 'agni';
    }

    $code = local_slbridge_pairing_requests::create(
        $stationid,
        $platform,
        $grid
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading('Register Object: ' . format_string($station->name));

if ($code !== null) {
    echo $OUTPUT->notification(
        'Pairing code: ' . s($code) .
        ' (expires in 10 minutes)',
        'success'
    );
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false)
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey()
]);


$stationlocation = $DB->get_field(
    'local_slbridge_station',
    'slurl',
    ['id' => $stationid, 'courseid' => $courseid],
    MUST_EXIST
);

require_once(__DIR__ . '/classes/world_location.php');

$location = \local_slbridge\world_location::parse(
    $stationlocation
);

if (!$location) {
    throw new \invalid_parameter_exception(
        'Invalid virtual world station location'
    );
}

$worldname = $location['platform'] === 'opensim'
    ? 'OpenSimulator' : 'Second Life';

echo html_writer::tag(
    'p',
    'Virtual world: ' . s($worldname)
);

echo html_writer::tag(
    'p',
    'Grid: ' . s($location['grid'])
);

echo html_writer::tag(
    'p',
    'Region: ' . s($location['region'])
);

foreach ([
    'platform' => $location['platform'],
    'grid' => $location['grid']
] as $name => $value) {
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => $name,
        'value' => $value
    ]);
}

echo html_writer::tag(
    'p',
    'The virtual world and grid are determined automatically ' .
    'from the learning station location.'
);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'generate',
    'value' => '1'
]);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Generate Pairing Code'
]);

echo html_writer::end_tag('form');

$pending = $DB->get_records_select(
    'local_slbridge_pairing',
    'stationid = :stationid AND usedat = 0
     AND expiresat >= :now AND objectuuid <> :empty',
    [
        'stationid' => $stationid,
        'now' => time(),
        'empty' => ''
    ],
    'timecreated DESC'
);

if ($pending) {
    echo $OUTPUT->heading('Pending Object Registrations', 3);

    foreach ($pending as $request) {
        echo html_writer::tag(
            'p',
            'Object UUID: ' . s($request->objectuuid)
        );

        $confirmurl = new moodle_url(
            '/local/slbridge/object_pair.php',
            [
                'courseid' => $courseid,
                'stationid' => $stationid,
                'confirmid' => $request->id,
                'sesskey' => sesskey()
            ]
        );

        echo $OUTPUT->single_button(
            $confirmurl,
            'Confirm Object',
            'post'
        );
    }
}


if ($provisionedsecret !== null) {
    echo $OUTPUT->notification(
        'Object credential (copy now; shown once): ' .
        s($provisionedsecret),
        'success'
    );
}

$objects = $DB->get_records(
    'local_slbridge_object',
    [
        'stationid' => $stationid,
        'status' => 'pending',
        'enabled' => 0
    ]
);

if ($objects) {
    echo $OUTPUT->heading('Approved Objects Awaiting Activation', 3);

    foreach ($objects as $object) {
        echo html_writer::tag(
            'p',
            'Object UUID: ' . s($object->objectuuid)
        );

        if (empty($object->credentialenc)) {
            $provisionurl = new moodle_url(
                '/local/slbridge/object_pair.php',
                [
                    'courseid' => $courseid,
                    'stationid' => $stationid
                ]
            );

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $provisionurl->out(false)
            ]);

            foreach ([
                'sesskey' => sesskey(),
                'provisionid' => $object->id
            ] as $name => $value) {
                echo html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => $name,
                    'value' => $value
                ]);
            }

            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => 'Generate Object Credential'
            ]);

            echo html_writer::end_tag('form');
        } else {
            echo html_writer::tag(
                'p',
                'Credential generated. Awaiting activation.'
            );
        }
    }
}



echo $OUTPUT->heading(
    get_string('coursekioskscript', 'local_slbridge'),
    3
);

$worldname = $location['platform'] === 'opensim'
    ? get_string('worldopensim', 'local_slbridge')
    : get_string('worldsecondlife', 'local_slbridge');

$downloadurl = new moodle_url(
    '/local/slbridge/download_course_kiosk.php',
    [
        'courseid' => $courseid,
        'stationid' => $stationid,
        'sesskey' => sesskey()
    ]
);

echo html_writer::link(
    $downloadurl,
    get_string(
        'downloadworldkiosk',
        'local_slbridge',
        $worldname
    ),
    ['class' => 'btn btn-primary mb-3']
);

echo html_writer::tag(
    'p',
    get_string('kioskdownloadinfo', 'local_slbridge')
);

echo html_writer::tag(
    'p',
    get_string(
        'kiosksetupinstructions',
        'local_slbridge',
        $worldname
    )
);

if ($location['platform'] === 'opensim') {
    echo $OUTPUT->notification(
        get_string(
            'opensimrequirements',
            'local_slbridge'
        ),
        'info'
    );
}

echo $OUTPUT->footer();
