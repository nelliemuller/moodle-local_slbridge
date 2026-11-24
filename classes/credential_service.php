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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/credential_crypto.php');
require_once(__DIR__ . '/credential_key.php');

final class local_slbridge_credential_service {

    public static function provision(int $objectid): string {
        global $DB;

        $object = $DB->get_record(
            'local_slbridge_object',
            ['id' => $objectid, 'enabled' => 0, 'status' => 'pending'],
            '*',
            MUST_EXIST
        );

        $station = $DB->get_record(
            'local_slbridge_station',
            ['id' => $object->stationid],
            'id,courseid',
            MUST_EXIST
        );

        require_capability(
            'local/slbridge:manage',
            \context_course::instance($station->courseid)
        );


        if (!empty($object->credentialenc)) {
            throw new \RuntimeException(
                'Object already has a credential'
            );
        }

        $factory = \core\lock\lock_config::get_lock_factory(
            'local_slbridge'
        );
        $lock = $factory->get_lock(
            'credential_' . $objectid, 10
        );

        if (!$lock) {
            throw new \RuntimeException(
                'Credential provisioning is busy'
            );
        }

        try {
            $current = $DB->get_record(
                'local_slbridge_object',
                ['id' => $objectid, 'enabled' => 0, 'status' => 'pending'],
                '*',
                MUST_EXIST
            );

            if (!empty($current->credentialenc)) {
                throw new \RuntimeException(
                    'Object already has a credential'
                );
            }

            $secret = bin2hex(random_bytes(32));
            $encrypted = local_slbridge_credential_crypto::encrypt(
                $secret,
                local_slbridge_credential_key::get()
            );

            $DB->set_field(
                'local_slbridge_object',
                'credentialenc',
                $encrypted,
                ['id' => $objectid]
            );

            return $secret;
        } finally {
            $lock->release();
        }
    }
}
