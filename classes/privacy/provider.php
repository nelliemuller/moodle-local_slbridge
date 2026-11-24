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

namespace local_slbridge\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

class provider implements metadata_provider, request_provider, core_userlist_provider {

    private const TABLES = [
        'local_slbridge_link',
        'local_slbridge_chal',
        'local_slbridge_attend',
        'local_slbridge_visit',
    ];

    public static function get_metadata(collection $collection): collection {
        $fields = [
            'local_slbridge_link' => [
                'platform' => 'privacy:metadata:platform',
                'grid' => 'privacy:metadata:grid',
                'userid' => 'privacy:metadata:userid',
                'avataruuid' => 'privacy:metadata:avataruuid',
                'avatarname' => 'privacy:metadata:avatarname',
                'verifiedat' => 'privacy:metadata:verifiedat',
                'revokedat' => 'privacy:metadata:revokedat',
            ],
            'local_slbridge_chal' => [
                'platform' => 'privacy:metadata:platform',
                'grid' => 'privacy:metadata:grid',
                'expiresat' => 'privacy:metadata:expiresat',
                'usedat' => 'privacy:metadata:usedat',
                'objectuuid' => 'privacy:metadata:objectuuid',
                'userid' => 'privacy:metadata:userid',
                'codehash' => 'privacy:metadata:codehash',
                'avataruuid' => 'privacy:metadata:avataruuid',
                'avatarname' => 'privacy:metadata:avatarname',
            ],
            'local_slbridge_attend' => [
                'sessionid' => 'privacy:metadata:sessionid',
                'userid' => 'privacy:metadata:userid',
                'avataruuid' => 'privacy:metadata:avataruuid',
                'firstseen' => 'privacy:metadata:firstseen',
                'lastseen' => 'privacy:metadata:lastseen',
            ],
            'local_slbridge_visit' => [
                'courseid' => 'privacy:metadata:courseid',
                'stationid' => 'privacy:metadata:stationid',
                'userid' => 'privacy:metadata:userid',
                'avataruuid' => 'privacy:metadata:avataruuid',
                'firstseen' => 'privacy:metadata:firstseen',
                'lastseen' => 'privacy:metadata:lastseen',
            ],
        ];


        $fields['local_slbridge_session'] = [
            'courseid' => 'privacy:metadata:courseid',
            'teacherid' => 'privacy:metadata:teacherid',
            'title' => 'privacy:metadata:sessiontitle',
            'starts' => 'privacy:metadata:sessionstarts',
            'ends' => 'privacy:metadata:sessionends',
            'region' => 'privacy:metadata:region',
        ];

        foreach ($fields as $table => $metadata) {
            $collection->add_database_table(
                $table,
                $metadata,
                'privacy:metadata:' . $table
            );
        }

        return $collection;
    }


    public static function get_contexts_for_userid(
        int $userid
    ): contextlist {
        global $DB;

        $contexts = new contextlist();

        foreach ([
            'local_slbridge_link',
            'local_slbridge_chal'
        ] as $table) {
            if ($DB->record_exists(
                $table,
                ['userid' => $userid]
            )) {
                $contexts->add_user_context($userid);
                break;
            }
        }

        $contexts->add_from_sql(
            "SELECT DISTINCT ctx.id
               FROM {context} ctx
               JOIN {local_slbridge_visit} v
                 ON v.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level
                AND v.userid = :userid",
            [
                'level' => CONTEXT_COURSE,
                'userid' => $userid
            ]
        );

        $contexts->add_from_sql(
            "SELECT DISTINCT ctx.id
               FROM {context} ctx
               JOIN {local_slbridge_session} s
                 ON s.courseid = ctx.instanceid
               JOIN {local_slbridge_attend} a
                 ON a.sessionid = s.id
              WHERE ctx.contextlevel = :level
                AND a.userid = :userid",
            [
                'level' => CONTEXT_COURSE,
                'userid' => $userid
            ]
        );

        $contexts->add_from_sql(
            "SELECT DISTINCT ctx.id
               FROM {context} ctx
               JOIN {local_slbridge_session} s
                 ON s.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level
                AND s.teacherid = :userid",
            [
                'level' => CONTEXT_COURSE,
                'userid' => $userid
            ]
        );

        return $contexts;
    }

    public static function export_user_data(
        approved_contextlist $contextlist
    ) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {

            $records = [];

            if ($context instanceof \context_user &&
                (int)$context->instanceid === $userid) {

                $records['avatar_connections'] =
                    $DB->get_records(
                        'local_slbridge_link',
                        ['userid' => $userid]
                    );

                $records['linking_challenges'] =
                    $DB->get_records(
                        'local_slbridge_chal',
                        ['userid' => $userid]
                    );

                foreach (
                    $records['linking_challenges'] as $challenge
                ) {
                    unset($challenge->codehash);
                }

            } else if ($context instanceof \context_course) {

                $courseid = (int)$context->instanceid;

                $records['station_visits'] =
                    $DB->get_records(
                        'local_slbridge_visit',
                        [
                            'courseid' => $courseid,
                            'userid' => $userid
                        ]
                    );

                $records['attendance'] =
                    $DB->get_records_sql(
                        "SELECT a.*
                           FROM {local_slbridge_attend} a
                           JOIN {local_slbridge_session} s
                             ON s.id = a.sessionid
                          WHERE s.courseid = :courseid
                            AND a.userid = :userid",
                        [
                            'courseid' => $courseid,
                            'userid' => $userid
                        ]
                    );

                $records['created_sessions'] =
                    $DB->get_records(
                        'local_slbridge_session',
                        [
                            'courseid' => $courseid,
                            'teacherid' => $userid
                        ]
                    );
            }

            foreach ($records as $name => $items) {
                if ($items) {
                    writer::with_context($context)->export_data(
                        ['SLBridge', $name],
                        (object)[
                            'records' => array_values($items)
                        ]
                    );
                }
            }
        }
    }

    public static function get_users_in_context(
        userlist $userlist
    ) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_user) {

            $userid = (int)$context->instanceid;

            foreach ([
                'local_slbridge_link',
                'local_slbridge_chal'
            ] as $table) {
                if ($DB->record_exists(
                    $table,
                    ['userid' => $userid]
                )) {
                    $userlist->add_user($userid);
                    break;
                }
            }

            return;
        }

        if (!($context instanceof \context_course)) {
            return;
        }

        $params = [
            'courseid' => (int)$context->instanceid
        ];

        $userlist->add_from_sql(
            'userid',
            "SELECT DISTINCT userid
               FROM {local_slbridge_visit}
              WHERE courseid = :courseid",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT DISTINCT a.userid
               FROM {local_slbridge_attend} a
               JOIN {local_slbridge_session} s
                 ON s.id = a.sessionid
              WHERE s.courseid = :courseid",
            $params
        );

        $userlist->add_from_sql(
            'teacherid',
            "SELECT DISTINCT teacherid
               FROM {local_slbridge_session}
              WHERE courseid = :courseid
                AND teacherid > 0",
            $params
        );
    }

    public static function delete_data_for_all_users_in_context(
        \context $context
    ) {
        global $DB;

        if ($context instanceof \context_user) {
            self::delete_connections(
                (int)$context->instanceid
            );
            return;
        }

        if (!($context instanceof \context_course)) {
            return;
        }

        $courseid = (int)$context->instanceid;

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records_select(
            'local_slbridge_attend',
            'sessionid IN (
                SELECT id
                  FROM {local_slbridge_session}
                 WHERE courseid = :courseid
            )',
            ['courseid' => $courseid]
        );

        $DB->delete_records(
            'local_slbridge_visit',
            ['courseid' => $courseid]
        );

        $DB->set_field(
            'local_slbridge_session',
            'teacherid',
            0,
            ['courseid' => $courseid]
        );

        $transaction->allow_commit();
    }

    public static function delete_data_for_user(
        approved_contextlist $contextlist
    ) {
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            self::delete_in_context(
                $context,
                $userid
            );
        }
    }

    public static function delete_data_for_users(
        approved_userlist $userlist
    ) {
        $context = $userlist->get_context();

        foreach ($userlist->get_userids() as $userid) {
            self::delete_in_context(
                $context,
                (int)$userid
            );
        }
    }

    private static function delete_connections(
        int $userid
    ): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        foreach ([
            'local_slbridge_link',
            'local_slbridge_chal'
        ] as $table) {
            $DB->delete_records(
                $table,
                ['userid' => $userid]
            );
        }

        $transaction->allow_commit();
    }

    private static function delete_in_context(
        \context $context,
        int $userid
    ): void {
        global $DB;

        if ($context instanceof \context_user) {
            if ((int)$context->instanceid === $userid) {
                self::delete_connections($userid);
            }
            return;
        }

        if (!($context instanceof \context_course)) {
            return;
        }

        $courseid = (int)$context->instanceid;

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records(
            'local_slbridge_visit',
            [
                'courseid' => $courseid,
                'userid' => $userid
            ]
        );

        $DB->delete_records_select(
            'local_slbridge_attend',
            'userid = :userid AND sessionid IN (
                SELECT id
                  FROM {local_slbridge_session}
                 WHERE courseid = :courseid
            )',
            [
                'userid' => $userid,
                'courseid' => $courseid
            ]
        );

        $DB->set_field(
            'local_slbridge_session',
            'teacherid',
            0,
            [
                'courseid' => $courseid,
                'teacherid' => $userid
            ]
        );

        $transaction->allow_commit();
    }
}
