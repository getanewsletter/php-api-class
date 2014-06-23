<?php

require_once("./GAPI.class.php");

class Test_PHP_API extends PHPUnit_Framework_TestCase {

    protected $ok_user = 'tester';
    protected $ok_pass = '123456789';
    protected $bad_user = 'wrong';
    protected $bad_pass = 'creds';
    protected $empty_list = 'HuYM5MtGCrEKHWEE';
    protected $good_list = '7UhL8k8bfAFh';
    protected $bad_list = 'no list';

    protected function setUp() {
        $this->api = new GAPI($this->ok_user, $this->ok_pass);

        $this->api->attribute_create('foo');
        $this->api->attribute_delete('spam');

        // These contacts shouldn't exist:
        $this->api->contact_delete('non-existent@example.com');
        $this->api->contact_delete('created@example.com');
        $this->api->contact_delete('created2@example.com');
        // These should:
        $this->api->contact_create('existing@example.com', 'firstname', 'lastname', Array('foo'=>'bar'), 4);
        $this->api->contact_create('existing_del@example.com', 'firstname', 'lastname', Array('foo'=>'bar'), 4);

        // Clean up subscriptions:
        if ($this->api->subscriptions_listing($this->good_list)) {
            foreach ($this->api->result as $subscription) {
                $this->api->subscription_delete($subscription['email'], $this->good_list);
            }
        };
    }

    protected function set_up_subscriptions() {
        for ($i = 0; $i < 5; $i++) {
            $this->api->subscription_add('subscriber' . $i . '@example.com', $this->good_list);
        }
    }

    public function test__login() {
        $api = new GAPI($this->bad_user, $this->bad_pass);
        $this->assertFalse($api->login());

        $api = new GAPI($this->ok_user, $this->ok_pass);
        $this->assertTrue($api->login());
    }

    public function test__check_login() {
        $api = new GAPI($this->bad_user, $this->bad_pass);
        $this->assertFalse($api->check_login());

        $api = new GAPI($this->ok_user, $this->ok_pass);
        $this->assertTrue($api->check_login());
    }

    public function test__contact_show() {
        // Try retrieving a non-existent contact:
        $result = $this->api->contact_show('non-existent@example.com');
        $this->assertFalse($result);

        // Get existing contact with the attributes:
        $result = $this->api->contact_show('existing@example.com', True);
        $this->assertTrue($result);
        //$this->assertEquals(count(array_keys($this->api->result[0])), 7);
        $this->assertEquals($this->api->result[0]['email'], 'existing@example.com');
        $this->assertEquals($this->api->result[0]['first_name'], 'firstname');
        $this->assertEquals($this->api->result[0]['last_name'], 'lastname');
        $this->assertTrue(array_key_exists('foo', $this->api->result[0]['attributes']));
        $this->assertEquals($this->api->result[0]['attributes']['foo'], 'bar');
        $this->assertEquals($this->api->result[0]['newsletters'], Array());

        // Get it without the attributes:
        $result = $this->api->contact_show('existing@example.com', False);
        $this->assertTrue($result);
        //$this->assertEquals(count(array_keys($this->api->result[0])), 6);
        $this->assertFalse(array_key_exists('attributes', $this->api->result[0]));
    }

    public function test__contact_create() {
        // Create contact:
        $result = $this->api->contact_create('created@example.com', 'name');
        $this->assertTrue($result);

        // Try to create already existing account:
        $result = $this->api->contact_create('created@example.com', 'name');
        $this->assertFalse($result);

        // Try to create already existing contact in 'quiet' mode:
        $result = $this->api->contact_create('created@example.com', 'new name', null, Array('foo'=>'bar'), 2);
        $this->assertTrue($result);
        // The contact should not be changed:
        $this->api->contact_show('created@example.com');
        $this->assertEquals($this->api->result[0]['first_name'], 'name');

        // Try to create already existing contact in 'update' mode:
        $result = $this->api->contact_create('created@example.com', null, 'last name', Array('foo'=>'fighter'), 3);
        $this->assertTrue($result);

        $this->api->contact_show('created@example.com', True);
        $this->assertEquals($this->api->result[0]['first_name'], 'name');
        $this->assertEquals($this->api->result[0]['last_name'], 'last name');
        $this->assertEquals($this->api->result[0]['attributes']['foo'], 'fighter');

        // Try to create already existing contact in 'overwrite' mode:
        $result = $this->api->contact_create('created@example.com', null, 'new name', Array('foo'=>'bar'), 4);
        $this->assertTrue($result);
        $this->api->contact_show('created@example.com', True);
        $this->assertEquals($this->api->result[0]['first_name'], '<nil/>');
        $this->assertEquals($this->api->result[0]['last_name'], 'new name');
        $this->assertEquals($this->api->result[0]['attributes'], Array('foo'=>'bar'));
    }

    public function test__contact_delete() {
        // Try deleting a non-existing account:
        $result = $this->api->contact_delete('non-existent@example.com');
        $this->assertFalse($result);

        // Delete existing account:
        $result = $this->api->contact_delete('existing_del@example.com');
        $this->assertTrue($result);
        $this->assertFalse($this->api->contact_show('existing_del@example.com'));
    }

    public function test__subscriptions_listing() {
        $this->set_up_subscriptions();

        // Try listing a non-existent subscription list:
        $result = $this->api->subscriptions_listing($this->bad_list, 0, 2);
        $this->assertFalse($result);

        // Try listsing an empty subscription list:
        $result = $this->api->subscriptions_listing($this->empty_list, 0, 2);
        $this->assertFalse($result);

        // Listing non-empty subscription list:
        $result = $this->api->subscriptions_listing($this->good_list, 0, 2);
        $this->assertTrue($result);
        $this->assertEquals(count($this->api->result), 2);

        $result = $this->api->subscriptions_listing($this->good_list);
        $this->assertTrue($result);
        $this->assertEquals(count($this->api->result), 5);

        //$this->assertEquals(count(array_keys($this->api->result[0])), 6);

        $keys = Array('confirmed', 'created', 'api-key', 'active', 'cancelled', 'email');
        foreach ($keys as $key) {
            $this->assertTrue(array_key_exists($key, $this->api->result[0]), 'Missing key: ' . $key);
        }

        // The empty fields must return the string '<nil/>':
        $this->assertEquals($this->api->result[0]['api-key'], '<nil/>');
    }

    public function test__subscription_delete() {
        $this->set_up_subscriptions();

        // Deleting non-existent subscription or non-existent list:
        $result = $this->api->subscription_delete('non-existent@example.com', $this->good_list);
        $this->assertFalse($result);

        $result = $this->api->subscription_delete('subscriber1@example.com', $this->bad_list);
        $this->assertFalse($result);

        // Deleting a subscription:
        $result = $this->api->subscription_delete('subscriber1@example.com', $this->good_list);
        $this->assertTrue($result);

        $this->api->subscriptions_listing($this->good_list);
        foreach ($this->api->result as $subscription) {
            $this->assertNotEquals($subscription['email'], 'subscriber1@example.com');
        }
    }

    public function test__subscription_add() {
        // Adding a subscription:
        $result = $this->api->subscription_add('existing@exmple.com', $this->good_list);
        $this->assertTrue($result);

        // Adding a new contact with subscription:
        $result = $this->api->subscription_add('created@example.com', $this->good_list, 'firstname', 'lastname', true,
            null, true, Array('foo'=>'bar'));
        $this->assertTrue($result);

        $this->api->contact_show('created@example.com', true);
        $this->assertEquals($this->api->result[0]['first_name'], 'firstname');
        $this->assertEquals($this->api->result[0]['last_name'], 'lastname');
        $this->assertEquals($this->api->result[0]['newsletters'][0]['list_id'], $this->good_list);

        // Trying to create existing subscription again or in non-existent list:
        $result = $this->api->subscription_add('created@example.com', $this->good_list);
        $this->assertFalse($result);
        $result = $this->api->subscription_add('created_new@example.com', $this->bad_list);
        $this->assertFalse($result);
    }

    public function test__newsletters_show() {
        $this->set_up_subscriptions();

        $result = $this->api->newsletters_show();
        $this->assertTrue($result);

        $this->assertEquals(count($this->api->result), 3);

        //$this->assertEquals(count(array_keys($this->api->result[0])), 5);
        foreach(Array('newsletter', 'sender', 'description', 'subscribers', 'list_id') as $key) {
            $this->assertTrue(array_key_exists($key, $this->api->result[0]), 'Missing key: ' . $key);
        }
    }

    public function test__attribute_listing() {
        $result = $this->api->attribute_listing();
        $this->assertTrue($result);

        $this->assertEquals(count($this->api->result), 1);

        //$this->assertEquals(count(array_keys($this->api->result[0])), 3);
        foreach(Array('usage', 'code', 'name') as $key) {
            $this->assertTrue(array_key_exists($key, $this->api->result[0]), 'Missing key: ' . $key);
        }

        // When there are no attributes, the method returns boolean true:
        $result = $this->api->attribute_delete('foo');
        $this->api->attribute_listing();
        $this->assertEquals(gettype($this->api->result), 'boolean');
        $this->assertEquals($this->api->result, true);
    }

    public function test__attribute_create() {
        $result = $this->api->attribute_create('spam');
        $this->assertTrue($result);

        $this->api->attribute_listing();
        $this->assertEquals(count($this->api->result), 2);

        $attrs = array_filter($this->api->result, function ($o) { return $o['name'] == 'spam'; });
        $this->assertEquals(count($attrs), 1);
    }

    public function test__attribute_delete() {
        $result = $this->api->attribute_delete('not existing');
        $this->assertFalse($result);

        $result = $this->api->attribute_delete('foo');
        $this->assertTrue($result);
        $this->api->attribute_listing();
        $this->assertEquals(gettype($this->api->result), 'boolean');
        $this->assertEquals($this->api->result, true);
    }
}

?>