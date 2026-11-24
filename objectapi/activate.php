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

// SLBridge Object API v2: activation.
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/object_lifecycle.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function slbridge_activation_reply(
    int $status,
    bool $ok,
    string $message
): void {
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'message' => $message
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    !is_https()) {
    slbridge_activation_reply(
        400, false, 'HTTPS POST required'
    );
}

$objectuuid = strtolower(trim(
    $_SERVER['HTTP_X_SECONDLIFE_OBJECT_KEY'] ?? ''
));

$claimeduuid = strtolower(trim(
    $_SERVER['HTTP_X_SLBRIDGE_OBJECT'] ?? ''
));

if ($objectuuid === '' || $claimeduuid === '' ||
    !hash_equals($objectuuid, $claimeduuid)) {
    slbridge_activation_reply(
        403, false, 'Object identity mismatch'
    );
}

if (!preg_match(
    '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',
    $objectuuid
)) {
    slbridge_activation_reply(
        403, false, 'Invalid object identity'
    );
}

$timestamp = $_SERVER['HTTP_X_SLBRIDGE_TIMESTAMP'] ?? '';
$nonce = $_SERVER['HTTP_X_SLBRIDGE_NONCE'] ?? '';
$signature = $_SERVER['HTTP_X_SLBRIDGE_SIGNATURE'] ?? '';

$body = file_get_contents('php://input');

if ($body !== 'action=activate') {
    slbridge_activation_reply(
        400, false, 'Invalid activation request'
    );
}


// SLBRIDGE_ACTIVATION_RATE_LIMIT_V1
$ratekey = hash('sha256', $objectuuid);

$factory = \core\lock\lock_config::get_lock_factory(
    'local_slbridge'
);

$lock = $factory->get_lock('activate_' . $ratekey, 5);

if (!$lock) {
    slbridge_activation_reply(503, false, 'Service busy');
}

try {
    $cache = \cache::make('local_slbridge', 'apiratelimit');
    $key = 'activate_' . $ratekey;
    $record = $cache->get($key);
    $now = time();

    if (!is_array($record) ||
        ($record['start'] ?? 0) + 600 <= $now) {
        $record = ['start' => $now, 'attempts' => 0];
    }

    $limited = $record['attempts'] >= 10;

    if (!$limited) {
        $record['attempts']++;
        $cache->set($key, $record);
    }
} finally {
    $lock->release();
}

if ($limited) {
    slbridge_activation_reply(429, false, 'Too many attempts');
}

$activated = local_slbridge_object_lifecycle::activate(
    $objectuuid,
    $timestamp,
    $nonce,
    $body,
    $signature
);

if (!$activated) {
    slbridge_activation_reply(
        403, false, 'Activation failed'
    );
}

slbridge_activation_reply(
    200, true, 'Object activated'
);
