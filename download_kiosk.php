<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$filename = __DIR__ . '/kiosk/slbridge_kiosk.lsl';

if (!is_file($filename)) {
    throw new moodle_exception(
        'The SLBridge kiosk script is not yet available.'
    );
}

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="slbridge_kiosk.lsl"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

readfile($filename);
exit;
