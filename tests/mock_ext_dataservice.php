<?php

/*
 * Mock for FF_Meta_Externaldata
 * in order to run all unit tests offline with predefined content
 */
class MockDataService {
    function get($url) {
        // translate file "http://example.org/%s.json" to "./example_%s.json"
        $url_filename   = basename(parse_url($url, PHP_URL_PATH));
        $local_filename = __DIR__.'/example_'.$url_filename;
        if (file_exists($local_filename)) {
            $json     = file_get_contents($local_filename);
            $stubdata = json_decode($json, $assoc = true);
            //error_log("MockDataService: fetch $url from $local_filename", 4);
            return $stubdata;
        } else {
            //error_log("MockDataService: cannot fetch $url", 4);
            return array();
        }
    }
}
