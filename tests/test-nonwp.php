<?php

# low level test of PHP functions & methods w/o WP integration
class LowLevelTest extends PHPUnit_Framework_TestCase {
    function setUp() {
        $this->FFM = new FF_Meta();
    }

    function test_output_ff_state() {
        $ret = $this->FFM->output_ff_state(array("state" => array("nodes" => 429)));
        $this->assertRegExp('/429/', $ret);
    }

    function test_basic_json_parsing() {
        $json = file_get_contents(__DIR__.'/example_ffhh.json');
        $data = json_decode($json, $assoc = true);
        
        $this->assertArrayHasKey('name',     $data);
        $this->assertArrayHasKey('state',    $data);
        $this->assertArrayHasKey('location', $data);
        $this->assertArrayHasKey('services', $data);
    }
    
    function test_externaldata() {
        $json     = file_get_contents(__DIR__.'/example_ffhh.json');
        $stubdata = json_decode($json, $assoc = true);
        
        $stub = $this->getMockBuilder('ff_meta_externaldata')
                     ->disableOriginalConstructor()
                     ->getMock();
        $stub->expects($this->any())
                     ->method('get')
                     ->will($this->returnValue($stubdata));
        
        $data = $stub->get('http://meta.hamburg.freifunk.net/ffhh.json');
        
        $this->assertArrayHasKey('name',     $data);
        $this->assertArrayHasKey('state',    $data);
        $this->assertArrayHasKey('location', $data);
        $this->assertArrayHasKey('services', $data);
    }
    
}