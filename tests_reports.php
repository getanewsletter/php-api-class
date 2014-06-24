<?php

require_once("./GAPI.class.php");

class Test_PHP_API extends PHPUnit_Framework_TestCase {

    protected $ok_user = 'tester2';
    protected $ok_pass = '123456789';

    protected function setUp() {
        $this->api = new GAPI($this->ok_user, $this->ok_pass);

        // A report is set up on the tester2 user by running the report generator
        // with default options.
        $this->api->reports_listing();
        $this->report_id = $this->api->result[0]['id'];
        $this->bad_report_id = 'xxx';
    }

    public function test__reports_listing() {
        // WARNING: The old API returns error if there are no reports:
        // 500: <class 'getanewsletter.reports.models.DoesNotExist'>:Report matching query does not exist.
        // This behaviour will not be tested (nor simulated) in the new API.

        $result = $this->api->reports_listing();
        $this->assertTrue($result);
        $this->assertCount(1, $this->api->result);

        // WARNING: The old tool returns a key with typo: 'unsubsribe' instead of 'unsubscribe':
        $keys = Array('lists', 'date' , 'sent_to', 'unsubsribe', 'unique_opens', 'url', 'bounces', 'id', 'link_click',
                      'subject', 'opens');

        foreach($keys as $key) {
            $this->assertArrayHasKey($key, $this->api->result[0]);
        }
    }

    public function test__reports_bounces() {
        $result = $this->api->reports_bounces($this->bad_report_id);
        $this->assertFalse($result);

        // List all bounces:
        $result = $this->api->reports_bounces($this->report_id);
        $this->assertTrue($result);
        $this->assertCount(30, $this->api->result);
        foreach(Array('status', 'email') as $key) {
            $this->assertArrayHasKey($key, $this->api->result[0]);
        }

        // List some bounces:
        $result = $this->api->reports_bounces($this->report_id, null, 10, 20);
        $this->assertTrue($result);
        $this->assertCount(10, $this->api->result);

        // Filter bounces by type. Soft:
        $result = $this->api->reports_bounces($this->report_id, 'soft');
        $this->assertTrue($result);
        $this->assertCount(24, $this->api->result);

        // Filter bounces by type. Hard:
        $result = $this->api->reports_bounces($this->report_id, 'hard');
        $this->assertTrue($result);
        $this->assertCount(6, $this->api->result);

        // Combine filter with limit:
        $result = $this->api->reports_bounces($this->report_id, 'soft', 10, 20);
        $this->assertTrue($result);
        $this->assertCount(10, $this->api->result);

        // Giving other that 'soft' or 'hard' filter returns all the results:
        $result = $this->api->reports_bounces($this->report_id, 'something else');
        $this->assertTrue($result);
        $this->assertCount(30, $this->api->result);
    }

    public function test__reports_link_clicks() {
        $result = $this->api->reports_link_clicks($this->bad_report_id);
        $this->assertFalse($result);

        $result = $this->api->reports_link_clicks($this->report_id);
        $this->assertTrue($result);
        $this->assertCount(18, $this->api->result);

        // ...
    }
}

?>