<?php

namespace DTApi\Helpers;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TeHelperTest extends TestCase
{
    public function testWillExpireAt()
    {
        // Create some Carbon objects to use in the test
        $now = Carbon::now();
        $due_time = $now->copy()->addHours(2);
        $created_at = $now->copy()->subHours(1);

        // Call the method being tested
        $result = TeHelperTest::willExpireAt($due_time, $created_at);

        // Assert that the result is a string in the correct format
        $this->assertIsString($result);
        $this->assertEquals($result, $due_time->format('Y-m-d H:i:s'));

        // Update the values to test the other conditions
        $due_time = $now->copy()->addHours(25);
        $created_at = $now->copy()->subHours(26);

        $result = TeHelperTest::willExpireAt($due_time, $created_at);

        $this->assertIsString($result);
        $this->assertEquals($result, $created_at->addMinutes(90)->format('Y-m-d H:i:s'));

        $due_time = $now->copy()->addHours(73);
        $created_at = $now->copy()->subDays(2);

        $result = TeHelperTest::willExpireAt($due_time, $created_at);

        $this->assertIsString($result);
        $this->assertEquals($result, $created_at->addHours(16)->format('Y-m-d H:i:s'));

        $due_time = $now->copy()->addDays(10);
        $created_at = $now->copy()->subDays(5);

        $result = TeHelperTest::willExpireAt($due_time, $created_at);

        $this->assertIsString($result);
        $this->assertEquals($result, $due_time->subHours(48)->format('Y-m-d H:i:s'));
    }
}