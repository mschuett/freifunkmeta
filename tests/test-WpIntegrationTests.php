<?php

# tests with WP integration, using the loaded plugin
class WpIntegrationTests extends WP_UnitTestCase {
    function setUp() {
        parent::setUp();

        // access to plugin instance
        $this->plugin = $GLOBALS['wp-plugin-ffmeta'];
        $this->plugin->reinit_external_data_service(new MockDataService());
    }

    function test_post_ff_state() {
        $post_content = '[ff_state]';
        $post_attribs = array( 'post_title' => 'Test', 'post_content' => $post_content );
        $post = $this->factory->post->create_and_get( $post_attribs );

        // w/o filter:
        $this->assertEquals($post_content, $post->post_content);

        // with filter:
        $output = apply_filters( 'the_content', $post->post_content );
        $this->assertEquals("<div class=\"ff ff_state\">429</div>\n", $output);
    }

    function test_post_ff_state_othercity() {
        $post_content = '[ff_state ffm]';
        $post_attribs = array( 'post_title' => 'Test', 'post_content' => $post_content );
        $post = $this->factory->post->create_and_get( $post_attribs );
        $output = apply_filters( 'the_content', $post->post_content );

        $this->assertEquals("<div class=\"ff ff_state\"></div>\n", $output);
    }

    function test_post_ff_state_inv_city() {
        $post_content = '[ff_state jena]';
        $post_attribs = array( 'post_title' => 'Test', 'post_content' => $post_content );
        $post = $this->factory->post->create_and_get( $post_attribs );
        $output = apply_filters( 'the_content', $post->post_content );

        $this->assertRegExp('/<!-- FF Meta Error:/', $output);
    }

    function test_post_ff_services() {
        $post_content = '[ff_services]';
        $post_attribs = array( 'post_title' => 'Test', 'post_content' => $post_content );
        $post = $this->factory->post->create_and_get( $post_attribs );

        // w/o filter:
        $this->assertEquals($post_content, $post->post_content);

        // with filter:
        $output = apply_filters( 'the_content', $post->post_content );
        $this->assertRegExp('/radio\.ffhh/', $output);
    }

    function test_post_ff_list() {
        $post_content = '[ff_list]';
        $post_attribs = array( 'post_title' => 'Test', 'post_content' => $post_content );
        $post = $this->factory->post->create_and_get( $post_attribs );

        // w/o filter:
        $this->assertEquals($post_content, $post->post_content);

        // with filter:
        $output = apply_filters( 'the_content', $post->post_content );
        $this->assertRegExp('/Hamburg/', $output);
        $this->assertRegExp('/Frankfurt/', $output);
    }
}