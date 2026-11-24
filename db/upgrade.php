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

function xmldb_local_slbridge_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102900) {
        $table = new xmldb_table('local_slbridge_visit');

        $table->add_field(
            'id', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, XMLDB_SEQUENCE
        );
        $table->add_field(
            'courseid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL
        );
        $table->add_field(
            'stationid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL
        );
        $table->add_field(
            'userid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL
        );
        $table->add_field(
            'avataruuid', XMLDB_TYPE_CHAR, '36',
            null, XMLDB_NOTNULL
        );
        $table->add_field(
            'firstseen', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL
        );
        $table->add_field(
            'lastseen', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL
        );

        $table->add_key(
            'primary', XMLDB_KEY_PRIMARY, ['id']
        );

        $table->add_index(
            'uniquevisit', XMLDB_INDEX_UNIQUE,
            ['courseid', 'stationid', 'userid']
        );

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(
            true, 2026102900, 'local', 'slbridge'
        );
    }


    if ($oldversion < 2026110600) {
        $table = new xmldb_table('local_slbridge_station');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255',
            null, XMLDB_NOTNULL);
        $table->add_field('slurl', XMLDB_TYPE_TEXT, null,
            null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, null, '0');
        $table->add_field('assessment', XMLDB_TYPE_CHAR, '20',
            null, XMLDB_NOTNULL, null, 'attendance');
        $table->add_field('maxgrade', XMLDB_TYPE_NUMBER, '10, 2',
            null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseidx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026110600, 'local', 'slbridge');
    }


    if ($oldversion < 2026110601) {
        $table = new xmldb_table('local_slbridge_object');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('stationid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_field('objectuuid', XMLDB_TYPE_CHAR, '36',
            null, XMLDB_NOTNULL);
        $table->add_field('credentialhash', XMLDB_TYPE_CHAR, '64',
            null, XMLDB_NOTNULL);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('objectuuiduniq', XMLDB_INDEX_UNIQUE,
            ['objectuuid']);
        $table->add_index('stationidx', XMLDB_INDEX_NOTUNIQUE,
            ['stationid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110601,
            'local', 'slbridge');
    }

    if ($oldversion < 2026110602) {
        $table = new xmldb_table('local_slbridge_pairing');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('stationid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_field('codehash', XMLDB_TYPE_CHAR, '64',
            null, XMLDB_NOTNULL);
        $table->add_field('objectuuid', XMLDB_TYPE_CHAR, '36',
            null, XMLDB_NOTNULL, null, '');
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);
        $table->add_field('usedat', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('codehashuniq', XMLDB_INDEX_UNIQUE,
            ['codehash']);
        $table->add_index('stationidx', XMLDB_INDEX_NOTUNIQUE,
            ['stationid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026110602,
            'local', 'slbridge');
    }


    if ($oldversion < 2026110603) {
        $table = new xmldb_table('local_slbridge_object');
        $field = new xmldb_field(
            'credentialenc', XMLDB_TYPE_TEXT
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(
            true, 2026110603, 'local', 'slbridge'
        );
    }


    if ($oldversion < 2026110604) {
        $table = new xmldb_table('local_slbridge_object');
        $field = new xmldb_field(
            'status', XMLDB_TYPE_CHAR, '20',
            null, XMLDB_NOTNULL, null, 'pending'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(
            true, 2026110604, 'local', 'slbridge'
        );
    }


    if ($oldversion < 2026112300) {
        $tables = [
            'local_slbridge_link',
            'local_slbridge_chal',
            'local_slbridge_object',
            'local_slbridge_pairing',
        ];

        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);

            $platform = new xmldb_field(
                'platform',
                XMLDB_TYPE_CHAR,
                '20',
                null,
                XMLDB_NOTNULL,
                null,
                'legacy'
            );

            if (!$dbman->field_exists($table, $platform)) {
                $dbman->add_field($table, $platform);
            }

            $grid = new xmldb_field(
                'grid',
                XMLDB_TYPE_CHAR,
                '255',
                null,
                XMLDB_NOTNULL,
                null,
                ''
            );

            if (!$dbman->field_exists($table, $grid)) {
                $dbman->add_field($table, $grid);
            }
        }

        upgrade_plugin_savepoint(
            true,
            2026112300,
            'local',
            'slbridge'
        );
    }

    return true;
}
