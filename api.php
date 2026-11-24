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

// Trusted-object endpoint: requires a kiosk secret and a registered object UUID.
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/platform.php');
require_once(__DIR__ . '/classes/avatar_kiosk.php');
require_once(__DIR__ . '/classes/object_request.php');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function slbridge_reply($ok, $message) { echo json_encode(['ok'=>$ok,'message'=>$message]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_https()) { http_response_code(400); slbridge_reply(false,'HTTPS POST required'); }

$objectid = strtolower(trim(
    $_SERVER['HTTP_X_SECONDLIFE_OBJECT_KEY'] ?? ''
));

$claimedobject = strtolower(trim(
    $_SERVER['HTTP_X_SLBRIDGE_OBJECT'] ?? ''
));

$coursekiosk = null;

if ($claimedobject !== '' &&
    !hash_equals($objectid, $claimedobject)) {
    http_response_code(403);
    slbridge_reply(false, 'Kiosk identity mismatch');
}

$legacyobject = strtolower(
    (string)get_config('local_slbridge', 'objectuuid')
);

if ($legacyobject !== '' &&
    hash_equals($legacyobject, $objectid)) {

$secret = get_config('local_slbridge','apikey');
$object = get_config('local_slbridge','objectuuid');
// Authentication uses the HMAC signature, not a transmitted secret.
$objectid = $_SERVER['HTTP_X_SECONDLIFE_OBJECT_KEY'] ?? '';
$claimedobject = $_SERVER['HTTP_X_SLBRIDGE_OBJECT'] ?? '';
if ($claimedobject !== '' &&
    !hash_equals(strtolower($objectid), strtolower($claimedobject))) {
 http_response_code(403);
 slbridge_reply(false, 'Kiosk identity mismatch');
}
if (!$secret || strlen($secret) < 32 || !local_slbridge_uuid_valid($object) || !hash_equals(strtolower($object), strtolower($objectid))) {
 http_response_code(403); slbridge_reply(false,'Unauthorized object');
}

// SLBridge signed request verification.
$timestamp = $_SERVER['HTTP_X_SLBRIDGE_TIMESTAMP'] ?? '';
$nonce = $_SERVER['HTTP_X_SLBRIDGE_NONCE'] ?? '';
$signature = $_SERVER['HTTP_X_SLBRIDGE_SIGNATURE'] ?? '';

if (!preg_match('/^[0-9]{10}$/', $timestamp) ||
    !preg_match('/^[a-f0-9-]{36}$/', $nonce) ||
    !preg_match('/^[A-Za-z0-9+\/]{43}=$/', $signature) ||
    abs(time() - (int)$timestamp) > 300) {
    http_response_code(403);
    slbridge_reply(false, 'Invalid or expired request');
}

$body = file_get_contents('php://input');
$canonical = $timestamp . "\n" . $nonce . "\n" .
    strtolower($objectid) . "\n" . $body;

// Standard HMAC-SHA256, Base64 encoded to match Second Life LSL.
$expected = base64_encode(hash_hmac('sha256', $canonical, $secret, true));

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    slbridge_reply(false, 'Invalid request signature');
}

// Serialize replay checks and claims using Moodle locks.
$replaykey = hash('sha256', strtolower($objectid) . ':' . $nonce);
$replayfactory = \core\lock\lock_config::get_lock_factory('local_slbridge');
$replaylock = $replayfactory->get_lock('replay_' . $replaykey, 5);

if (!$replaylock) {
    http_response_code(503);
    slbridge_reply(false, 'Request verification busy');
}

try {
    $replaycache = \cache::make('local_slbridge', 'requestreplay');
    $replayed = (bool)$replaycache->get($replaykey);
    if (!$replayed) {
        $replaycache->set($replaykey, 1);
    }
} finally {
    $replaylock->release();
}

if ($replayed) {
    http_response_code(409);
    slbridge_reply(false, 'Duplicate request');
}



} else {

    $timestamp = $_SERVER['HTTP_X_SLBRIDGE_TIMESTAMP'] ?? '';
    $nonce = $_SERVER['HTTP_X_SLBRIDGE_NONCE'] ?? '';
    $signature = $_SERVER['HTTP_X_SLBRIDGE_SIGNATURE'] ?? '';
    $body = file_get_contents('php://input');

    if ($body === false || strlen($body) > 8192 ||
        !local_slbridge_uuid_valid($objectid) ||
        !hash_equals($objectid, $claimedobject)) {
        http_response_code(403);
        slbridge_reply(false, 'Invalid object request');
    }

    $authenticated = local_slbridge_object_request::verify(
        $objectid,
        $timestamp,
        $nonce,
        $body,
        $signature
    );

    if (!$authenticated) {
        http_response_code(403);
        slbridge_reply(false, 'Authentication failed');
    }

    $coursekiosk = \local_slbridge\avatar_kiosk::registered(
        $objectid,
        $authenticated->platform,
        $authenticated->grid
    );

    if (!$coursekiosk) {
        http_response_code(403);
        slbridge_reply(false, 'Unauthorized course kiosk');
    }
}

$ratecache = \cache::make('local_slbridge', 'apiratelimit');
$ratekey = hash('sha256', $objectid . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$ratelockfactory = \core\lock\lock_config::get_lock_factory('local_slbridge');
$ratelock = $ratelockfactory->get_lock('rate_' . $ratekey, 5);

if (!$ratelock) {
    http_response_code(503);
    slbridge_reply(false, 'Service busy. Please try again.');
}

try {
    $attempts = (int)$ratecache->get($ratekey);
    $ratelimited = $attempts >= 30;

    if (!$ratelimited) {
        $ratecache->set($ratekey, $attempts + 1);
    }
} finally {
    $ratelock->release();
}

if ($ratelimited) {
    http_response_code(429);
    slbridge_reply(false, 'Too many requests. Please try again later.');
}



if ($coursekiosk) {
    $kioskplatform = $coursekiosk->platform;
    $kioskgrid = $coursekiosk->grid;
} else {
try {
    [$kioskplatform, $kioskgrid] =
        local_slbridge_platform::normalize(
            (string)(get_config('local_slbridge', 'kioskplatform') ?: 'secondlife'),
            (string)(get_config('local_slbridge', 'kioskgrid') ?: 'agni')
        );
} catch (\Throwable $e) {
    http_response_code(503);
    slbridge_reply(false, 'Invalid kiosk configuration');
}


}

$action = optional_param('action','',PARAM_ALPHA);
$avatar = strtolower(optional_param('avatar','',PARAM_RAW_TRIMMED));
if (!local_slbridge_uuid_valid($avatar)) { slbridge_reply(false,'Invalid avatar UUID'); }
$now = time();

if ($coursekiosk &&
    !in_array($action, ['status', 'link'], true)) {
    http_response_code(403);
    slbridge_reply(false, 'Unsupported course kiosk action');
}

if ($coursekiosk) {
    parse_str($body, $signedparams);

    foreach (['action', 'avatar', 'code', 'name'] as $key) {
        if (isset($signedparams[$key]) &&
            !is_string($signedparams[$key])) {
            http_response_code(400);
            slbridge_reply(false, 'Invalid signed parameters');
        }
    }

    if (($signedparams['action'] ?? '') !== $action ||
        strtolower($signedparams['avatar'] ?? '') !== $avatar ||
        strtoupper($signedparams['code'] ?? '') !==
            strtoupper(optional_param('code', '', PARAM_ALPHANUM)) ||
        clean_param($signedparams['name'] ?? '', PARAM_TEXT) !==
            optional_param('name', '', PARAM_TEXT)) {
        http_response_code(403);
        slbridge_reply(false, 'Signed parameters mismatch');
    }
}

// Read-only verified avatar status.
// Uses the existing authenticated kiosk request.
if ($action === 'status') {
    $link = $DB->get_record(
        'local_slbridge_link',
        [
            'avataruuid' => $avatar,
            'platform' => $kioskplatform,
            'grid' => $kioskgrid,
            'revokedat' => 0,
        ]
    );

    $verified = $link && !empty($link->verifiedat);

    if ($verified && $coursekiosk) {
        $verified = \local_slbridge\avatar_kiosk::eligible(
            $coursekiosk,
            (int)$link->userid
        );
    }

    slbridge_reply(
        true,
        $verified ? 'AVATAR_VERIFIED' : 'AVATAR_NOT_LINKED'
    );
}

if ($action === 'link') {
 $code = strtoupper(optional_param('code','',PARAM_ALPHANUM));
 if (!preg_match('/^[A-F0-9]{12}$/',$code)) { slbridge_reply(false,'Invalid code'); }
 $codehash = hash('sha256', $code);
 $lockfactory = \core\lock\lock_config::get_lock_factory('local_slbridge');
 $lock = $lockfactory->get_lock('code_' . $codehash, 10);
 if (!$lock) {
  http_response_code(503);
  slbridge_reply(false, 'Linking is busy. Please try again.');
 }
 try {
  $challenge = $DB->get_record('local_slbridge_chal', ['codehash' => $codehash]);
  if (!$challenge || $challenge->usedat || $challenge->expiresat < time() || $challenge->avataruuid) {
   $validclaim = false;
  } else {
   $validclaim = true;
  }
  if ($validclaim && $coursekiosk &&
       !\local_slbridge\avatar_kiosk::eligible(
           $coursekiosk,
           (int)$challenge->userid
       )) {
       $validclaim = false;
   }

   if ($validclaim) {
    $challenge->avataruuid = $avatar;
   $challenge->avatarname = substr(optional_param('name', '', PARAM_TEXT), 0, 255);
   $challenge->objectuuid = strtolower($objectid);
   $challenge->platform = $kioskplatform;
   $challenge->grid = $kioskgrid;
   $DB->update_record('local_slbridge_chal', $challenge);
  }
 } finally {
  $lock->release();
 }
 if (!$validclaim) {
  slbridge_reply(false, 'Invalid or expired code');
 }
 slbridge_reply(true, 'Return to Moodle and confirm your avatar');
}
if ($action === 'attendance') {
 $sessionid = optional_param('session',0,PARAM_INT);
 $session = $DB->get_record('local_slbridge_session',['id'=>$sessionid]);
 if (!$session || $now < $session->starts || $now > $session->ends) { slbridge_reply(false,'Session not active'); }
 $region = optional_param('region','',PARAM_TEXT);
 if ($session->region !== '' && $session->region !== $region) { slbridge_reply(false,'Wrong region'); }
 $link = $DB->get_record('local_slbridge_link',['avataruuid'=>$avatar,'platform'=>$kioskplatform,'grid'=>$kioskgrid,'revokedat'=>0]);
 if (!$link || !is_enrolled(context_course::instance($session->courseid),$link->userid)) { slbridge_reply(false,'No eligible linked Moodle learner'); }
 $existing = $DB->get_record('local_slbridge_attend',['sessionid'=>$sessionid,'userid'=>$link->userid]);
 if ($existing) { $DB->set_field('local_slbridge_attend','lastseen',$now,['id'=>$existing->id]); }
 else { try { $DB->insert_record('local_slbridge_attend',(object)['sessionid'=>$sessionid,'userid'=>$link->userid,'avataruuid'=>$avatar,'firstseen'=>$now,'lastseen'=>$now]); } catch (dml_write_exception $e) { /* Concurrent duplicate; unique index protects record. */ } }
 slbridge_reply(true,'Attendance recorded');
}
slbridge_reply(false,'Unknown action');
