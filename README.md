# SLBridge: Simulator Learning Bridge

Developed by Dr. Nellie Deutsch.

SLBridge connects Moodle learning activities with Second Life
and OpenSimulator virtual worlds.

## Features

* Second Life and OpenSimulator integration.
* Support for multiple independent OpenSimulator grids.
* Course-level virtual world selection.
* Persistent verified avatar connections.
* Multiple avatar identities per Moodle account.
* Teacher-managed learning stations.
* Individual secure kiosk credentials.
* Automatic participation grading.
* Moodle activity assessment.
* Attendance recording.
* Native Moodle Privacy API integration.
* Course configuration backup and restore.
* Shared virtual world kiosk script.

## Requirements

* Moodle 5.3.1 development environment.
* HTTPS-enabled Moodle installation.
* Compatible Second Life or OpenSimulator scripting engine.
* Outbound HTTPS communication from virtual world objects.
* Support for llHMAC and llJsonGetValue.

Compatibility with earlier Moodle releases has not been verified.

## Installation

1. Install the local_slbridge plugin through Moodle administration.
2. Complete the Moodle plugin upgrade.
3. Open the SLBridge administration settings.
4. Configure credential encryption as described in
   docs/SECURITY-KEY-SETUP.md.
5. Open a Moodle course and select SLBridge.

## Teacher setup

1. Select Second Life, OpenSimulator, or both.
2. Create a learning station.
3. Enter the virtual world location.
4. Select an assessment method.
5. Open the object registration interface.
6. Download the course kiosk script.
7. Create an object in the selected virtual world.
8. Add the downloaded script to the object.
9. Generate a pairing code in Moodle.
10. Touch the object and select Pair Object.
11. Enter the pairing code.
12. Confirm the object registration in Moodle.
13. Generate the individual object credential.
14. Insert the credential into the private object script.
15. Save the script and select Activate.

## Supported location formats

Second Life:

https://maps.secondlife.com/secondlife/Education/128/128/25

OpenSimulator:

hop://grid.example.org:9000/Education/128/128/25

Each learning station determines its platform and grid
from its configured location.

## Assessment methods

### Attendance only

Record verified learning station visits.

### Automatic participation grade

Award the configured participation grade through Moodle.

### Moodle activity assessment

Associate a learning station with an existing Moodle activity.
The activity retains its own assessment workflow.

## Learner instructions

1. Open your SLBridge avatar preferences in Moodle.
2. Generate a temporary avatar linking code.
3. Visit the virtual world learning station.
4. Touch the kiosk and select Link Avatar.
5. Enter the linking code.
6. Return to Moodle and confirm the avatar connection.
7. Touch the kiosk again and select Visit Station.

Avatar connections can be reused across eligible courses.

## Security

SLBridge uses individual object credentials, HMAC-SHA256
request authentication, timestamps and replay protection.

Keep kiosk scripts containing credentials private.
Do not distribute configured scripts containing secrets.

## Privacy

SLBridge stores avatar identifiers, avatar names,
platform and grid identifiers, linking information,
attendance and station participation records.

The Moodle Privacy API supports personal and course contexts.

## Backup and restore

Course configuration backup and restore support includes
platform preferences and learning station definitions.

Restored stations remain disabled pending teacher review.

Private credentials, registered objects, temporary pairing
codes and global avatar connections are not transferred.

Historical SLBridge attendance and participation grades
are not currently included in course restoration.

## Release status

0.1.2 Alpha development candidate.

PHP syntax validation and independent HMAC verification
have passed.

Full PHPUnit, course restoration and inworld integration
verification remain outstanding.

## License

GNU General Public License version 3 or later.

See LICENSE for the complete license text.

## Developer

Dr. Nellie Deutsch

Integrating Technology and AI in Education

https://integrating-technology.org/

Plugin downloads:

https://integrating-technology.org/plugins/
