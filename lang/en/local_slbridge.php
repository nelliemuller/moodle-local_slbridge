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

$string['pluginname'] = 'SLBridge';
$string['slbridge:manage'] = 'Manage SLBridge course sessions';
$string['slbridge:view'] = 'View SLBridge course activities';
$string['privacy:metadata'] = 'SLBridge stores verified Second Life avatar identifiers and attendance records linked to Moodle accounts.';

$string['privacy:metadata:userid'] = 'The Moodle user associated with the record.';
$string['privacy:metadata:avataruuid'] = 'The Second Life avatar identifier.';
$string['privacy:metadata:avatarname'] = 'The Second Life avatar name.';
$string['privacy:metadata:verifiedat'] = 'When the avatar was linked.';
$string['privacy:metadata:revokedat'] = 'When the avatar link was revoked.';
$string['privacy:metadata:codehash'] = 'A hashed temporary avatar linking code.';
$string['privacy:metadata:firstseen'] = 'When attendance was first recorded.';
$string['privacy:metadata:lastseen'] = 'When attendance was last recorded.';
$string['privacy:metadata:local_slbridge_link'] = 'Stores Moodle user and Second Life avatar links.';
$string['privacy:metadata:local_slbridge_chal'] = 'Stores temporary avatar linking challenges.';
$string['privacy:metadata:local_slbridge_attend'] = 'Stores Second Life attendance records.';

$string['stationname'] = 'Station name';
$string['stationslurl'] = 'Second Life location (SLURL)';
$string['stationassessment'] = 'Assessment method';
$string['stationmaxgrade'] = 'Maximum participation grade';
$string['stationactivity'] = 'Associated Moodle activity';
$string['stations'] = 'Second Life learning stations';
$string['addstation'] = 'Add learning station';
$string['nostations'] = 'No learning stations have been configured.';

$string['privacy:metadata:local_slbridge_visit'] = 'Stores student visits to Second Life learning stations.';

$string['virtualworldconnections'] = 'SLBridge Virtual World Connections';
$string['connectionsintro'] = 'Connect your Second Life or OpenSimulator avatar once. Your verified connection remains available for future visits.';
$string['noconnections'] = 'No active avatar connections found.';
$string['avatar'] = 'Avatar UUID';
$string['platform'] = 'Virtual world';
$string['status'] = 'Connection status';
$string['verified'] = 'Verified';
$string['pending'] = 'Pending confirmation';
$string['notavailable'] = 'Not specified';
$string['manageavatarlink'] = 'Manage avatar linking';
$string['revokeconnection'] = 'Revoke';

$string['worldlocation'] = 'Virtual world location';
$string['worldlocation_help'] = 'Enter a Second Life SLURL or an OpenSimulator hop link. Each learning station can use a different virtual world or grid.';
$string['invalidworldlocation'] = 'Enter a valid Second Life SLURL or OpenSimulator hop link.';
$string['worldstations'] = 'Virtual World Learning Stations';
$string['worldstationsintro'] = 'Create learning stations in Second Life, OpenSimulator or both within this course.';
$string['worldplatform'] = 'Virtual world';
$string['worldgrid'] = 'Grid';
$string['worldsecondlife'] = 'Second Life';
$string['worldopensim'] = 'OpenSimulator';
$string['worldunknown'] = 'Unknown';
$string['visitworldstation'] = 'Visit Station';

$string['courseworldsettings'] = 'Virtual World Settings';
$string['courseworldsettingsintro'] = 'Choose the virtual worlds used in this course. Each OpenSimulator station can use a different grid.';
$string['worldboth'] = 'Second Life and OpenSimulator';
$string['worldnotenabled'] = 'This virtual world is not enabled in the course settings.';
$string['courseworldconflict'] = 'Unable to save the selection. Existing enabled stations must remain supported.';

$string['coursekioskscript'] = 'Learning Station Kiosk Script';
$string['downloadworldkiosk'] = 'Download {$a} Course Kiosk';
$string['kioskdownloadinfo'] = 'The downloaded script contains your Moodle site and course URLs. It does not contain your secret credential.';
$string['kiosksetupinstructions'] = 'Create an object in {$a} and add the downloaded script. Generate a pairing code in Moodle. Touch the object and select Pair Object. Confirm the object in Moodle, generate its individual credential, insert the credential into the script, save it and select Activate.';
$string['opensimrequirements'] = 'Your OpenSimulator grid must support llHMAC, llHTTPRequest, llGenerateKey and llJsonGetValue, and permit outbound HTTPS requests to Moodle.';

$string['privacy:metadata:platform'] = 'The virtual world platform associated with an avatar.';
$string['privacy:metadata:grid'] = 'The virtual world grid associated with an avatar.';
$string['privacy:metadata:expiresat'] = 'The expiration time of an avatar linking challenge.';
$string['privacy:metadata:usedat'] = 'The time an avatar linking challenge was confirmed.';
$string['privacy:metadata:objectuuid'] = 'The identifier of the kiosk used for avatar verification.';
$string['privacy:metadata:sessionid'] = 'The attendance session identifier.';
$string['privacy:metadata:courseid'] = 'The Moodle course identifier.';
$string['privacy:metadata:stationid'] = 'The virtual world learning station identifier.';
$string['privacy:metadata:teacherid'] = 'The Moodle user who created the attendance session.';
$string['privacy:metadata:sessiontitle'] = 'The title of the attendance session.';
$string['privacy:metadata:sessionstarts'] = 'The starting time of the attendance session.';
$string['privacy:metadata:sessionends'] = 'The ending time of the attendance session.';
$string['privacy:metadata:region'] = 'The virtual world region associated with the session.';
$string['privacy:metadata:local_slbridge_session'] = 'Attendance sessions configured by Moodle teachers.';
