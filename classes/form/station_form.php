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

namespace local_slbridge\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class station_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'text', 'name', get_string('stationname', 'local_slbridge')
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement(
            'text', 'slurl', get_string('worldlocation', 'local_slbridge'),
            ['size' => 70]
        );
        $mform->setType('slurl', PARAM_RAW_TRIMMED);

        $mform->addHelpButton(
            'slurl', 'worldlocation', 'local_slbridge'
        );
        $mform->addRule('slurl', null, 'required', null, 'client');

        $mform->addElement(
            'select', 'assessment',
            get_string('stationassessment', 'local_slbridge'),
            [
                'attendance' => 'Attendance only',
                'automatic' => 'Automatic participation grade',
                'activity' => 'Moodle activity assessment'
            ]
        );

        $mform->addElement(
            'text', 'maxgrade',
            get_string('stationmaxgrade', 'local_slbridge')
        );
        $mform->setType('maxgrade', PARAM_FLOAT);
        $mform->setDefault('maxgrade', 0);


        $courseid = (int)($this->_customdata['courseid'] ?? 0);
        $activities = [0 => get_string('none')];

        if ($courseid > 0) {
            $modinfo = get_fast_modinfo($courseid);

            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->deletioninprogress) {
                    continue;
                }

                $activities[$cm->id] =
                    $cm->modname . ': ' . $cm->name;
            }
        }

        $mform->addElement(
            'select',
            'cmid',
            get_string('stationactivity', 'local_slbridge'),
            $activities
        );
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!\local_slbridge\world_location::parse(
            $data['slurl'] ?? ''
        )) {
            $errors['slurl'] = get_string(
                'invalidworldlocation', 'local_slbridge'
            );
        }

        $modes = ['attendance', 'automatic', 'activity'];
        if (!in_array($data['assessment'] ?? '', $modes, true)) {
            $errors['assessment'] = 'Invalid assessment method.';
        }

        $grade = (float)($data['maxgrade'] ?? 0);
        if ($grade < 0 || $grade > 1000) {
            $errors['maxgrade'] = 'Enter a grade between 0 and 1000.';
        }

        $courseid = (int)($this->_customdata['courseid'] ?? 0);
        $cmid = (int)($data['cmid'] ?? 0);

        if ($cmid > 0) {
            try {
                $cm = get_coursemodule_from_id(
                    '', $cmid, $courseid, false, MUST_EXIST
                );
                if ((int)$cm->course !== $courseid) {
                    $errors['cmid'] = 'Activity belongs to another course.';
                }
            } catch (\Exception $e) {
                $errors['cmid'] = 'Invalid Moodle activity.';
            }
        }

        if (($data['assessment'] ?? '') === 'activity' && !$cmid) {
            $errors['cmid'] = 'Select a Moodle activity.';
        }

        return $errors;
    }

}
