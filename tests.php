<?php

require_once('./GAPI.class.php');
require_once('./local_settings.php');

class Test_PHP_API extends PHPUnit_Framework_TestCase
{

    protected $ok_user;
    protected $ok_pass;
    protected $bad_user;
    protected $bad_pass;
    protected $empty_list;
    protected $first_list;
    protected $second_list;
    protected $bad_list;

    function __construct()
    {
        $this->ok_user = $GLOBALS['OK_USER'];
        $this->ok_user = $GLOBALS['OK_USER'];
        $this->ok_pass = $GLOBALS['OK_PASS'];
        $this->bad_user = $GLOBALS['BAD_USER'];
        $this->bad_pass = $GLOBALS['BAD_PASS'];
        $this->empty_list = $GLOBALS['EMPTY_LIST'];
        $this->first_list = $GLOBALS['FIRST_LIST'];
        $this->second_list = $GLOBALS['SECOND_LIST'];
        $this->bad_list = $GLOBALS['BAD_LIST'];
    }

    protected function setUp()
    {
        $this->api = new GAPI($this->ok_user, $this->ok_pass);

        // Clean up subscriptions:
        foreach (array($this->first_list, $this->second_list) as $list) {
            if ($this->api->subscriptions_listing($list)) {
                foreach ($this->api->result as $subscription) {
                    $this->api->subscription_delete($subscription['email'], $list);
                }
            }
        }

        // These contacts shouldn't exist:
        $this->api->contact_delete('non-existent@example.com');
        $this->api->contact_delete('created@example.com');
        $this->api->contact_delete('created2@example.com');
        // These should be cleaned up:
        $this->api->contact_delete('existing@example.com');
        $this->api->contact_delete('existing_del@example.com');

        $this->api->attribute_create('foo');
        $this->api->attribute_create('baz');
        $this->api->attribute_delete('spam');

        // These should exist:
        $this->api->contact_create('existing@example.com', 'firstname', 'lastname', array('foo'=>'bar'), 4);
        $this->api->contact_create('existing_del@example.com', 'firstname', 'lastname', array('foo'=>'bar'), 4);
    }

    protected function set_up_subscriptions()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->api->subscription_add('subscriber' . $i . '@example.com', $this->first_list);
        }
    }

    // TODO: Add test for connection failure.

    public function test__login()
    {
        $api = new GAPI($this->bad_user, $this->bad_pass);
        $this->assertFalse($api->login());

        $api = new GAPI($this->ok_user, $this->ok_pass);
        $this->assertTrue($api->login());
    }

    public function test__check_login()
    {
        $api = new GAPI($this->bad_user, $this->bad_pass);
        $this->assertFalse($api->check_login());

        $api = new GAPI($this->ok_user, $this->ok_pass);
        $this->assertTrue($api->check_login());
    }

    public function test__contact_show()
    {
        // Try retrieving a non-existent contact:
        $result = $this->api->contact_show('non-existent@example.com');
        $this->assertFalse($result);

        // Get existing contact with the attributes:
        $result = $this->api->contact_show('existing@example.com', True);
        $this->assertTrue($result);

        $this->assertEquals('existing@example.com', $this->api->result[0]['email']);
        $this->assertEquals('firstname', $this->api->result[0]['first_name']);
        $this->assertEquals('lastname', $this->api->result[0]['last_name']);
        $this->assertArrayHasKey('foo', $this->api->result[0]['attributes']);
        $this->assertEquals('bar', $this->api->result[0]['attributes']['foo']);
        $this->assertEquals(array(), $this->api->result[0]['newsletters']);

        // Get it without the attributes:
        $result = $this->api->contact_show('existing@example.com', False);
        $this->assertTrue($result);

        $this->assertArrayNotHasKey('attributes', $this->api->result[0]);
    }

    public function test__contact_create()
    {
        // Create contact:
        $result = $this->api->contact_create('created@example.com', 'name', '', array('foo'=>''));
        $this->assertTrue($result);

        // Try to create already existing account:
        $result = $this->api->contact_create('created@example.com', 'name');
        $this->assertFalse($result);

        // Try to create already existing contact in 'quiet' mode:
        $result = $this->api->contact_create('created@example.com', 'new name', null, array('foo'=>'bar'), 2);
        $this->assertTrue($result);
        // The contact should not be changed:
        $this->api->contact_show('created@example.com', True);
        $this->assertEquals('name', $this->api->result[0]['first_name']);
        $this->assertEquals('<nil/>', $this->api->result[0]['last_name']);
        $this->assertEquals(array('foo'=>'', 'baz'=>'<nil/>'), $this->api->result[0]['attributes']);

        // Create second contact:
        $result = $this->api->contact_create('created2@example.com', 'name', null, array('baz' => 'qux'));
        $this->assertTrue($result);

        // Try to create already existing contact in 'update' mode:
        $result = $this->api->contact_create('created2@example.com', null, 'last name', array('foo'=>'fighter'), 3);
        $this->assertTrue($result);

        $this->api->contact_show('created2@example.com', True);
        $this->assertEquals('name', $this->api->result[0]['first_name']);
        $this->assertEquals('last name', $this->api->result[0]['last_name']);
        $this->assertEquals('fighter', $this->api->result[0]['attributes']['foo']);
        $this->assertEquals('qux', $this->api->result[0]['attributes']['baz']);

        // In 'update' mode empty values are the same as null values:
        $result = $this->api->contact_create('created2@example.com', '', null, array(), 3);
        $this->assertTrue($result);
        $this->api->contact_show('created2@example.com', True);
        $this->assertEquals('name', $this->api->result[0]['first_name']);

        // Try to create already existing contact in 'overwrite' mode:
        // Note: All attributes from the array are overwritten. All other will be cleared (i.e. = <nil/>).
        // In this case ['foo' => 'fighter', 'baz' => 'qux'] becomes ['foo'=>'bar', 'baz'=>'<nil/>'].
        $result = $this->api->contact_create('created2@example.com', null, 'new name', array('foo' => 'bar'), 4);
        $this->assertTrue($result);
        $this->api->contact_show('created2@example.com', True);
        $this->assertEquals('<nil/>', $this->api->result[0]['first_name']);
        $this->assertEquals('new name', $this->api->result[0]['last_name']);
        $this->assertEquals(array('foo' => 'bar', 'baz' => '<nil/>'), $this->api->result[0]['attributes']);
    }

    public function test__contact_delete()
    {
        // Try deleting a non-existing account:
        $result = $this->api->contact_delete('non-existent@example.com');
        $this->assertFalse($result);

        // Delete existing account:
        $result = $this->api->contact_delete('existing_del@example.com');
        $this->assertTrue($result);
        $this->assertFalse($this->api->contact_show('existing_del@example.com'));
    }

    public function test__subscriptions_listing()
    {
        $this->set_up_subscriptions();

        // Try listing a non-existent subscription list:
        $result = $this->api->subscriptions_listing($this->bad_list, 0, 2);
        $this->assertFalse($result);

        // Try listsing an empty subscription list:
        $result = $this->api->subscriptions_listing($this->empty_list, 0, 2);
        $this->assertFalse($result);

        // // TODO: See how to implement $start and $end here.
        // // Listing non-empty subscription list:
        // $result = $this->api->subscriptions_listing($this->first_list, 0, 2);
        // $this->assertTrue($result);
        // $this->assertCount(2, $this->api->result);

        $result = $this->api->subscriptions_listing($this->first_list);
        $this->assertTrue($result);
        $this->assertCount(5, $this->api->result);

        // TODO: Fields 'api-key', 'active' and 'confirmed' are not supported by APIv3.
        // $keys = array('confirmed', 'created', 'api-key', 'active', 'cancelled', 'email');
        $keys = array('created', 'cancelled', 'email');
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $this->api->result[0]);
        }

        // // The empty fields must return the string '<nil/>':
        // $this->assertEquals('<nil/>', $this->api->result[0]['api-key']);
    }

    public function test__subscription_delete()
    {
        $this->set_up_subscriptions();

        // Deleting non-existent subscription or non-existent list:
        $result = $this->api->subscription_delete('non-existent@example.com', $this->first_list);
        $this->assertFalse($result);

        $result = $this->api->subscription_delete('subscriber1@example.com', $this->bad_list);
        $this->assertFalse($result);

        // Deleting a subscription:
        $result = $this->api->subscription_delete('subscriber1@example.com', $this->first_list);
        $this->assertTrue($result);

        $this->api->subscriptions_listing($this->first_list);
        foreach ($this->api->result as $subscription) {
            $this->assertNotEquals($subscription['email'], 'subscriber1@example.com');
        }
    }

    public function test__subscription_add()
    {
        // Adding a subscription:
        // Note: if the contact exists, it will work like contact_create in 'overwrite' mode (4).
        $result = $this->api->subscription_add('existing@example.com', $this->first_list, 'new firstname');
        $this->assertTrue($result);

        $this->api->contact_show('existing@example.com', true);
        $this->assertEquals('new firstname', $this->api->result[0]['first_name']);
        $this->assertEquals('<nil/>', $this->api->result[0]['last_name']);

        // Adding a new contact with subscription:
        $result = $this->api->subscription_add('created@example.com', $this->first_list, 'firstname', 'lastname', true,
            null, true, array('foo'=>'bar'));
        $this->assertTrue($result);

        $this->api->contact_show('created@example.com', true);
        $this->assertEquals('firstname', $this->api->result[0]['first_name']);
        $this->assertEquals('lastname', $this->api->result[0]['last_name']);
        $this->assertEquals($this->first_list, $this->api->result[0]['newsletters'][0]['list_id']);

        // Subscribe the same contact for another list:
        $result = $this->api->subscription_add('created@example.com', $this->second_list);
        $this->assertTrue($result);
        $this->api->contact_show('created@example.com', true);
        $this->assertCount(2, $this->api->result[0]['newsletters']);

        // Trying to create existing subscription again or in non-existent list:
        $result = $this->api->subscription_add('created@example.com', $this->first_list);
        $this->assertFalse($result);
        $result = $this->api->subscription_add('created_new@example.com', $this->bad_list);
        $this->assertFalse($result);
    }

    public function test__contact_create_with_subscriptions()
    {
        $result = $this->api->subscription_add('existing@example.com', $this->first_list, 'new firstname');
        $this->api->contact_show('existing@example.com', true);
        $this->assertCount(1, $this->api->result[0]['newsletters']);

        $result = $this->api->contact_create('existing@example.com', null, 'new lastname', array('foo' => 'bar'), 4);
        $this->assertTrue($result);
        $this->api->contact_show('existing@example.com', True);
        $this->assertCount(1, $this->api->result[0]['newsletters']);
    }

    public function test__newsletters_show()
    {
        // Important! You need to have 3 lists in the test account:
        // the default ones: Standard list, Test list,
        // and one that you need to create manually - Empty list.

        $result = $this->api->newsletters_show();
        $this->assertTrue($result);

        $this->assertCount(3, $this->api->result);

        foreach (array('newsletter', 'sender', 'description', 'subscribers', 'list_id') as $key) {
            $this->assertArrayHasKey($key, $this->api->result[0]);
        }
    }

    public function test__attribute_listing()
    {
        $result = $this->api->attribute_listing();
        $this->assertTrue($result);

        $this->assertCount(2, $this->api->result);

        foreach (array('usage', 'code', 'name') as $key) {
            $this->assertArrayHasKey($key, $this->api->result[0]);
        }

        if ($this->api->version != 'v0.1') {
            $result = $this->api->attribute_delete('foo');
            $result = $this->api->attribute_delete('baz');
            $this->api->attribute_listing();
            $this->assertEquals(array(), $this->api->result);
        }
    }

    public function test__attribute_create()
    {
        $result = $this->api->attribute_create('spam');
        $this->assertTrue($result);

        $this->api->attribute_listing();
        $this->assertCount(3, $this->api->result);

        $attrs = array_filter($this->api->result, function ($o) { return $o['name'] == 'spam'; });
        $this->assertCount(1, $attrs);
    }

    public function test__attribute_delete()
    {
        $result = $this->api->attribute_listing();
        $this->assertTrue($result);

        $this->assertCount(2, $this->api->result);

        $result = $this->api->attribute_delete('not existing');
        $this->assertFalse($result);

        $result = $this->api->attribute_delete('foo');
        $this->assertTrue($result);

        $this->api->attribute_listing();
        $this->assertCount(1, $this->api->result);
    }
}
