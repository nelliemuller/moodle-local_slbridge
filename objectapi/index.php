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

// SLBridge Object API v2.
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/object_request.php');
require_once(__DIR__ . '/../classes/station_visit_service.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function slbridge_object_reply(
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
    slbridge_object_reply(400, false, 'HTTPS POST required');
}

$objectuuid = strtolower(trim(
    $_SERVER['HTTP_X_SECONDLIFE_OBJECT_KEY'] ?? ''
));

$claimeduuid = strtolower(trim(
    $_SERVER['HTTP_X_SLBRIDGE_OBJECT'] ?? ''
));

if ($objectuuid === '' || $claimeduuid === '' ||
    !hash_equals($objectuuid, $claimeduuid)) {
    slbridge_object_reply(403, false, 'Object identity mismatch');
}

$timestamp = $_SERVER['HTTP_X_SLBRIDGE_TIMESTAMP'] ?? '';
$nonce = $_SERVER['HTTP_X_SLBRIDGE_NONCE'] ?? '';
$signature = $_SERVER['HTTP_X_SLBRIDGE_SIGNATURE'] ?? '';

$body = file_get_contents('php://input');

if ($body === false || strlen($body) > 8192) {
    slbridge_object_reply(413, false, 'Invalid request size');
}

$object = local_slbridge_object_request::verify(
    $objectuuid,
    $timestamp,
    $nonce,
    $body,
    $signature
);

if (!$object) {
    slbridge_object_reply(403, false, 'Authentication failed');
}


if (preg_match(
    '/^action=visit&avatar=([0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})$/',
    $body,
    $matches
)) {
    $recorded = local_slbridge_station_visit_service::record(
        (int)$object->stationid,
        $matches[1],
        $object->platform,
        $object->grid
    );

    if (!$recorded) {
        slbridge_object_reply(
            403, false, 'Visit not eligible'
        );
    }

    slbridge_object_reply(
        200, true, 'Station visit recorded'
    );
}

slbridge_object_reply(
    400, false, 'Unsupported object action'
);
