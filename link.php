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
require_once(__DIR__ . '/classes/platform.php');
require_once(__DIR__ . '/classes/avatar_kiosk.php');
if (!isloggedin() || isguestuser()) {
    $SESSION->wantsurl =
        (new moodle_url('/local/slbridge/link.php'))->out(false);

    redirect(new moodle_url('/login/index.php'));
}

require_login();

$PAGE->set_url(new moodle_url('/local/slbridge/link.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_title('SLBridge Virtual World Avatar Linking');
$PAGE->set_heading('SLBridge Virtual World Avatar Linking');
$now = time();
$notice = '';
$code = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    optional_param('generate', 0, PARAM_BOOL)) {
 require_sesskey();
 $DB->delete_records('local_slbridge_chal', ['userid'=>$USER->id]);
 $code = strtoupper(bin2hex(random_bytes(6)));
 $DB->insert_record('local_slbridge_chal', (object)['userid'=>$USER->id,'codehash'=>hash('sha256',$code),'expiresat'=>$now+600,'usedat'=>0,'avataruuid'=>'','avatarname'=>'','objectuuid'=>'']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    optional_param('confirm', 0, PARAM_BOOL)) {
 require_sesskey();
 $challengeid = required_param('challengeid', PARAM_INT);
 $lockfactory = \core\lock\lock_config::get_lock_factory('local_slbridge');
 $lock = $lockfactory->get_lock('avatar_link_confirmation', 10);
 if (!$lock) {
  throw new moodle_exception('Avatar linking is busy. Please try again.');
 }
 try {
 $transaction = $DB->start_delegated_transaction();
 $challenge = $DB->get_record('local_slbridge_chal', ['id'=>$challengeid,'userid'=>$USER->id], '*', MUST_EXIST);
 if ($challenge->expiresat < time() || $challenge->usedat || !local_slbridge_uuid_valid($challenge->avataruuid)) {
  throw new moodle_exception('Invalid or expired challenge');
 }
 try {
  [$validplatform, $validgrid] =
      local_slbridge_platform::normalize(
          (string)$challenge->platform,
          (string)$challenge->grid
      );
 } catch (\Throwable $e) {
  throw new moodle_exception('Invalid avatar platform or grid');
 }
 $challenge->platform = $validplatform;
 $challenge->grid = $validgrid;

 // Revalidate the trusted kiosk before confirmation.

 $legacy = \local_slbridge\avatar_kiosk::legacy_matches(
     $challenge->objectuuid,
     $validplatform,
     $validgrid
 );

 if (!$legacy) {
     $kiosk = \local_slbridge\avatar_kiosk::registered(
         $challenge->objectuuid,
         $validplatform,
         $validgrid
     );

     if (!$kiosk ||
         !\local_slbridge\avatar_kiosk::eligible(
             $kiosk,
             (int)$USER->id
         )) {
         throw new moodle_exception(
             'Avatar verification kiosk is no longer authorized.'
         );
     }
 }

if ($DB->record_exists('local_slbridge_link', ['avataruuid'=>$challenge->avataruuid,'platform'=>$challenge->platform,'grid'=>$challenge->grid,'revokedat'=>0])) {
  throw new moodle_exception('This avatar is already linked');
 }
 // Preserve existing verified avatar connections.
 $DB->insert_record('local_slbridge_link',(object)['userid'=>$USER->id,'avataruuid'=>$challenge->avataruuid,'avatarname'=>$challenge->avatarname,'platform'=>$challenge->platform,'grid'=>$challenge->grid,'verifiedat'=>time(),'revokedat'=>0]);
 $DB->set_field('local_slbridge_chal','usedat',time(),['id'=>$challengeid]);
 $transaction->allow_commit();
 $notice = 'Avatar linked successfully.';
 } finally {
  $lock->release();
 }
}

echo $OUTPUT->header();
echo html_writer::tag('p', 'Generate a one-time code in Moodle Preferences. Enter it once in your Second Life or OpenSimulator kiosk, then return to Moodle to confirm your avatar. Codes expire after 10 minutes. Once confirmed, your avatar remains linked for future visits. You do not need another code unless the connection is revoked.');
if ($notice) { echo $OUTPUT->notification($notice, 'success'); }
$links = $DB->get_records(
    'local_slbridge_link',
    [
        'userid' => $USER->id,
        'revokedat' => 0
    ],
    'id DESC'
);

if ($links) {
    echo html_writer::tag('h3', 'My linked avatars');

    foreach ($links as $link) {
        $platform = $link->platform === 'secondlife'
            ? 'Second Life'
            : ($link->platform === 'opensim'
                ? 'OpenSimulator'
                : 'Legacy connection');

        $description = $link->avatarname .
            ' (' . $link->avataruuid . ')' .
            ' | ' . $platform .
            ' | Grid: ' . $link->grid;

        echo html_writer::tag('p', s($description));
    }
}
if ($code) { echo html_writer::tag('p', 'One-time linking code: ' . s($code)); }
echo '<form method="post"><input type="hidden" name="sesskey" value="'.s(sesskey()).'"><button type="submit" name="generate" value="1">Generate new linking code</button></form>';
$pending = $DB->get_records_select('local_slbridge_chal','userid = :uid AND usedat = 0 AND expiresat >= :now AND avataruuid <> :empty',['uid'=>$USER->id,'now'=>time(),'empty'=>''], 'id DESC');
foreach ($pending as $ch) {
 $pendingdescription = $ch->avatarname .
     ' (' . $ch->avataruuid . ')' .
     ' | Platform: ' . $ch->platform .
     ' | Grid: ' . $ch->grid;

 echo html_writer::tag(
     'p',
     'Pending avatar: ' . s($pendingdescription)
 );
 echo '<form method="post"><input type="hidden" name="sesskey" value="'.s(sesskey()).'"><input type="hidden" name="challengeid" value="'.(int)$ch->id.'"><button type="submit" name="confirm" value="1">Confirm this avatar</button></form>';
}
echo $OUTPUT->footer();
