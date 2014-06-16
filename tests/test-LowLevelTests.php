<?php

# low level test of PHP functions & methods w/o WP integration
class LowLevelTests extends PHPUnit_Framework_TestCase {
    function setUp() {
        $this->FFM = new FF_Meta(new MockDataService());
        $this->FFM->reinit_external_data_service(new MockDataService());
    }

    /* some very basic things */
    function test_basic_json_parsing() {
        $json = file_get_contents(__DIR__.'/example_ffhh.json');
        $data = json_decode($json, $assoc = true);

        $this->assertArrayHasKey('name',     $data);
        $this->assertArrayHasKey('state',    $data);
        $this->assertArrayHasKey('location', $data);
        $this->assertArrayHasKey('services', $data);
    }

    function test_externaldata_mock() {
        $ed      = new MockDataService();
        $url_dir = 'https://raw.githubusercontent.com/freifunk/directory.api.freifunk.net/master/directory.json';
        $url_ff  = 'http://meta.hamburg.freifunk.net/ffhh.json';
        $url_inv = 'http://meta.hamburg.freifunk.net/invalid.txt';

        // verify that $ed->get does not read the URLs above, but local example_*.json files
        $data_ff = $ed->get($url_ff);
        $this->assertArrayHasKey('name',     $data_ff);
        $this->assertArrayHasKey('state',    $data_ff);
        $this->assertArrayHasKey('location', $data_ff);
        $this->assertArrayHasKey('services', $data_ff);

        $data_dir = $ed->get($url_dir);
        $this->assertArrayHasKey('hamburg', $data_dir);
        $this->assertEquals(2, count($data_dir));

        $data_inv = $ed->get($url_inv);
        $this->assertEquals(0, count($data_inv));
    }

    /* the aux. classes */
    function test_ff_directory() {
        $dir     = new FF_Directory(new MockDataService());
        $valid   = $dir->get_url_by_city('hamburg');
        $invalid = $dir->get_url_by_city('jena');

        $this->assertTrue(!!$valid);
        $this->assertTrue(!$invalid);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    function test_ff_community_invalid() {
        $data = array();
        $comm = new FF_Community($data);
    }

    function test_ff_community_empty() {
        $data = array('location' => array());
        $comm = new FF_Community($data);
        $this->assertEmpty($comm->street);
        $this->assertEmpty($comm->name);

        $string = $comm->format_address();
        $this->assertEquals('', $string);
    }

    function test_ff_community_filled() {
        $data = array('location' => array(
            'address' => array(
                'Name'    => 'some_name',
                'Street'  => 'some_street',
                'Zipcode' => 'some_zip'
            ),
            'city' => 'some_city',
            'lon'  => 'some_lon',
            'lat'  => 'some_lat',
        ));
        $comm = new FF_Community($data);
        $this->assertEquals('some_name',   $comm->name);
        $this->assertEquals('some_street', $comm->street);
        $this->assertEquals('some_zip',    $comm->zip);
        $this->assertEquals('some_city',   $comm->city);
        $this->assertEquals('some_lon',    $comm->lon);
        $this->assertEquals('some_lat',    $comm->lat);

        $string = $comm->format_address();
        $this->assertEquals('<p>some_name<br/>some_street<br/>some_zip some_city</p>', $string);
    }

    function test_ff_community_make_from_city() {
        $comm = FF_Community::make_from_city('hamburg', new MockDataService());
        $this->assertEquals('Chaos Computer Club Hansestadt Hamburg', $comm->name);
        $this->assertEquals('Humboldtstr. 53', $comm->street);
        $this->assertEquals('22083',   $comm->zip);
        $this->assertEquals('Hamburg', $comm->city);
        $this->assertEquals(10.024418, $comm->lon);
        $this->assertEquals(53.574267, $comm->lat);
    }

    /* the output methods */
    function test_output_ff_state_null() {
        $data = array("state" => array("nodes" => null));
        $ret  = $this->FFM->output_ff_state($data);
        $this->assertEmpty($ret);
    }

    function test_output_ff_state() {
        $data = array("state" => array("nodes" => 429));
        $ret  = $this->FFM->output_ff_state($data);
        $this->assertRegExp('/429/', $ret);
    }

    function test_output_ff_services_null() {
        $data = array();
        $ret  = $this->FFM->output_ff_services($data);
        $this->assertEmpty($ret);
        $this->assertEquals('', $ret);
    }

    function test_output_ff_services() {
        $data = array(
            'services' => array(array(
                'serviceName'        => 'jabber',
                'serviceDescription' => 'chat',
                'internalUri'        => 'xmpp://jabber.local',
            )));
        $ret = $this->FFM->output_ff_services($data);
        $this->assertEquals('<ul><li>jabber (chat): <a href="xmpp://jabber.local">xmpp://jabber.local</a></li></ul>', $ret);
    }

    function test_output_ff_contact_null() {
        $data = array();
        $ret  = $this->FFM->output_ff_contact($data);
        $this->assertEquals('', $ret);
    }

    function test_output_ff_contact_filled() {
        $data = array('contact' => array(
            'email'   => 'mail@example.com',
            'jabber'  => 'example@freifunk.net'
        ));
        $ret  = $this->FFM->output_ff_contact($data);
        $this->assertRegExp('/E-Mail/', $ret);
        $this->assertRegExp('/mailto:mail@example\.com/', $ret);
        $this->assertRegExp('/XMPP/', $ret);
        $this->assertRegExp('/xmpp:example/', $ret);

        $data = array('contact' => array(
            'twitter' => 'http://twitter.com/freifunk'
        ));
        $ret  = $this->FFM->output_ff_contact($data);
        $this->assertRegExp('/twitter\.com\/freifunk/', $ret);

        $data = array('contact' => array(
            'twitter' => '@freifunk'
        ));
        $ret  = $this->FFM->output_ff_contact($data);
        $this->assertRegExp('/Twitter/', $ret);
        $this->assertRegExp('/twitter\.com\/freifunk/', $ret);

        $data = array('contact' => array(
            'ml'  => 'mail@example.com',
            'irc' => 'irc://irc.hackint.net/example',
            'facebook' => 'freifunk',
        ));
        $ret  = $this->FFM->output_ff_contact($data);
        $this->assertRegExp('/mailto:mail@example\.com/', $ret);
        $this->assertRegExp('/irc\.hackint\.net\/example/', $ret);
        $this->assertRegExp('/Facebook:/', $ret);
    }
    // function test_aux_get_all_locations() {
    //     $this->markTestIncomplete(
    //       'This test has not been implemented yet.'
    //     );
    // }

}
