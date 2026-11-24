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

// SLBridge Object API v2: pairing claim.
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/object_pairing.php');
require_once(__DIR__ . '/../classes/pairing_requests.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function slbridge_claim_reply(bool $ok, string $message): void {
    echo json_encode([
        'ok' => $ok,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_https()) {
    http_response_code(400);
    slbridge_claim_reply(false, 'HTTPS POST required');
}

$objectuuid = strtolower(trim(
    $_SERVER['HTTP_X_SECONDLIFE_OBJECT_KEY'] ?? ''
));

if (!preg_match(
    '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
    $objectuuid
)) {
    http_response_code(403);
    slbridge_claim_reply(false, 'Invalid object identity');
}


// Limit repeated pairing attempts.
$ratekey = hash(
    'sha256',
    $objectuuid . ':' . ($_SERVER['REMOTE_ADDR'] ?? '')
);

$factory = \core\lock\lock_config::get_lock_factory(
    'local_slbridge'
);

$lock = $factory->get_lock(
    'slbridge_claim_rate_' . $ratekey, 5
);

if (!$lock) {
    http_response_code(503);
    slbridge_claim_reply(false, 'Service busy');
}

try {
    $cache = \cache::make(
        'local_slbridge',
        'apiratelimit'
    );

    $key = 'claim_' . $ratekey;
    $record = $cache->get($key);
    $now = time();

    if (!is_array($record) ||
        ($record['start'] ?? 0) + 600 <= $now) {
        $record = [
            'start' => $now,
            'attempts' => 0
        ];
    }

    if ($record['attempts'] >= 10) {
        $limited = true;
    } else {
        $record['attempts']++;
        $cache->set($key, $record);
        $limited = false;
    }
} finally {
    $lock->release();
}

if ($limited) {
    http_response_code(429);
    slbridge_claim_reply(false, 'Too many pairing attempts');
}

$code = strtoupper(trim(
    optional_param('code', '', PARAM_ALPHANUM)
));

if (!local_slbridge_object_pairing::valid_code($code)) {
    http_response_code(400);
    slbridge_claim_reply(false, 'Invalid pairing code');
}

$claimed = local_slbridge_pairing_requests::claim(
    $code,
    $objectuuid
);

if (!$claimed) {
    http_response_code(403);
    slbridge_claim_reply(false, 'Pairing unavailable');
}

slbridge_claim_reply(true, 'Awaiting teacher approval');
