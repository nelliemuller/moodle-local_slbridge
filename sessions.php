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
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/slbridge:manage',$context);
$PAGE->set_url(new moodle_url('/local/slbridge/sessions.php',['courseid'=>$courseid]));
$PAGE->set_context($context);
$PAGE->set_title('Second Life attendance sessions');
$PAGE->set_heading(format_string($course->fullname) . ': Second Life sessions');
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    optional_param('create', 0, PARAM_BOOL)) {
 require_sesskey();
 $title = required_param('title',PARAM_TEXT);
 $starts = required_param('starts',PARAM_INT);
 $ends = required_param('ends',PARAM_INT);
 $region = optional_param('region','',PARAM_TEXT);
 if ($starts >= $ends || $ends - $starts > 86400 || $ends < time()) { throw new moodle_exception('Invalid session times'); }
 $DB->insert_record('local_slbridge_session',(object)['courseid'=>$courseid,'teacherid'=>$USER->id,'title'=>$title,'starts'=>$starts,'ends'=>$ends,'region'=>$region]);
 redirect(new moodle_url('/local/slbridge/sessions.php',['courseid'=>$courseid]));
}
echo $OUTPUT->header();
echo html_writer::tag('p','Create a session with Unix timestamps (UTC seconds). For initial testing, attendance is recorded when a linked avatar touches the trusted kiosk.');
echo '<form method="post"><input type="hidden" name="courseid" value="'.(int)$courseid.'"><input type="hidden" name="sesskey" value="'.s(sesskey()).'"><input type="hidden" name="create" value="1">';
echo 'Title <input name="title" required maxlength="255"> Start (Unix timestamp) <input type="number" name="starts" value="'.(time()-60).'" required> End (Unix timestamp) <input type="number" name="ends" value="'.(time()+3600).'" required> Region (optional) <input name="region"><button type="submit">Create session</button></form>';
foreach ($DB->get_records('local_slbridge_session',['courseid'=>$courseid],'id DESC') as $session) {
 echo html_writer::tag('h3',s($session->title).' (session #'.(int)$session->id.')');
 echo html_writer::tag('p',userdate($session->starts).' to '.userdate($session->ends).' | Region: '.s($session->region));
 $rows = $DB->get_records('local_slbridge_attend',['sessionid'=>$session->id]);
 foreach ($rows as $a) { $user = core_user::get_user($a->userid); echo html_writer::tag('p',s(fullname($user)).' | '.userdate($a->firstseen)); }
}
echo $OUTPUT->footer();
