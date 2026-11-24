<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests virtual world location recognition.
 *
 * @covers \local_slbridge\world_location
 */
final class world_location_test extends \basic_testcase {

    public function test_secondlife_location(): void {

        $location = world_location::parse(
            'https://maps.secondlife.com/secondlife/Education/128/128/25'
        );

        $this->assertNotNull($location);
        $this->assertSame('secondlife', $location['platform']);
        $this->assertSame('agni', $location['grid']);
        $this->assertSame('Education', $location['region']);
    }

    public function test_opensim_location(): void {

        $location = world_location::parse(
            'hop://grid.example.org:9000/Welcome%20Center/128/128/25'
        );

        $this->assertNotNull($location);
        $this->assertSame('opensim', $location['platform']);
        $this->assertSame(
            'grid.example.org:9000',
            $location['grid']
        );

        $this->assertSame(
            'Welcome Center',
            $location['region']
        );
    }

    public function test_independent_grids(): void {

        $first = world_location::parse(
            'hop://grid-one.example.org:9000/Education/128/128/25'
        );

        $second = world_location::parse(
            'hop://grid-two.example.org:8002/Education/128/128/25'
        );

        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->assertNotSame(
            $first['grid'],
            $second['grid']
        );
    }

    public function test_invalid_locations(): void {

        $locations = [
            '',
            'not-a-location',
            'javascript:alert(1)',
            'https://example.org/Education/128/128/25',
            'hop://grid.example.org:9000/',
            'hop://user:password@grid.example.org/Education/128/128/25',
        ];

        foreach ($locations as $location) {
            $this->assertNull(
                world_location::parse($location),
                'Invalid location accepted: ' . $location
            );
        }
    }
}
