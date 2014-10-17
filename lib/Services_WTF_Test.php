<?php

/*
 * Service_WTF_Test
 *
 * Version 0.4
 * 
 * A PHP REST client for the Web Testing Framework (WTF) Testing Service API
 * Currently only supports GTmetrix. See:
 *
 *     http://gtmetrix.com/api/
 *
 * for more information on the API and how to contribute to the web testing
 * framework!
 *
 * Copyright Gossamer Threads Inc. (http://gt.net/)
 * License: http://opensource.org/licenses/GPL-2.0 GPL 2
 *
 * This software is free software distributed under the terms of the GNU 
 * General Public License 2.0.
 *
 * Changelog:
 *
 *     0.4
 *         - fixed download_resources bug
 *         - added $append parameter to download_resources
 *         - some refactoring for consistency
 * 
 *     0.3
 *         - added download_resources method
 *  
 *     June 27, 2012
 *         - polling frequency in get_results() made less frantic
 *         - version changed to 0.2
 * 
 *     June 5, 2012
 *         - status method added
 *         - user_agent property updated
 * 
 *     January 23, 2012
 *         - Initial release
 */

class Services_WTF_Test {
    const api_url = 'https://gtmetrix.com/api/0.1';
    private $username = '';
    private $password = '';
    private $user_agent = 'Services_WTF_Test_php/0.4 (+http://gtmetrix.com/api/)';
    protected $test_id = '';
    protected $result = array( );
    protected $error = '';

    /**
     * Constructor
     *
     * $username    string  username to log in with
     * $password    string  password/apikey to log in with
     */
    public function __construct( $username = '', $password = '' ) {
        $this->username = $username;
        $this->password = $password;
    }

    public function api_username( $username ) {
        $this->username = $username;
    }

    public function api_password( $password ) {
        $this->password = $password;
    }

    /**
     * user_agent()
     *
     * $user_agent    string   in the form of "product name/version number" used to identify the application to the API
     *
     * Optional, defaults to "Services_WTF_Test_php/0.1 (+http://gtmetrix.com/api/)"
     */
    public function user_agent( $user_agent ) {
        $this->user_agent = $user_agent;
    }

    /**
     * query()
     *
     * Makes curl connection to API
     *
     * $command string                          command to send
     * $req     string  GET|POST|DELETE         request to send API
     * $params  array                           POST data if request is POST
     *
     * returns raw http data (JSON object in most API cases) on success, false otherwise
     */
    protected function query( $command, $req = 'GET', $params = '' ) {
        $ch = curl_init();

        if ( substr( $command, 0, strlen( self::api_url ) - 1 ) == self::api_url ) {
            $URL = $command;
        } else {
            $URL = self::api_url . '/' . $command;
        }

        curl_setopt( $ch, CURLOPT_URL, $URL );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
        curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
        curl_setopt( $ch, CURLOPT_USERPWD, $this->username . ":" . $this->password );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $req );
        // CURLOPT_SSL_VERIFYPEER turned off to avoid failure when cURL has no CA cert bundle: see http://curl.haxx.se/docs/sslcerts.html
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        if ( $req == 'POST' )
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );

        $results = curl_exec( $ch );
        if ( $results === false )
            $this->error = curl_error( $ch );

        curl_close( $ch );

        return $results;
    }

    protected function checkid() {
        if ( empty( $this->test_id ) ) {
            $this->error = 'No test_id! Please start a new test or load an existing test first.';
            return false;
        }

        return true;
    }

    /**
     * error()
     *
     * Returns error message
     */
    public function error() {
        return $this->error;
    }

    /**
     * test()
     *
     * Sends new test to GTMetrix API
     *
     * $data    array   array containing parameters to send API
     *
     * returns the test_id on success, false otherwise;
     */
    public function test( $data ) {

        if ( empty( $data ) ) {
            $this->error = 'Parameters need to be set to start a new test!';
            return false;
        }

        if ( !isset( $data['url'] ) OR empty( $data['url'] ) ) {
            $this->error = 'No URL given!';
            return false;
        }

        // check URL
        if ( !preg_match( '@^https?://@', $data['url'] ) ) {
            $this->error = 'Bad URL.';
            return false;
        }

        if ( !empty( $this->result ) )
            $this->result = array( );

        $data = http_build_query( $data );

        $result = $this->query( 'test', 'POST', $data );

        if ( $result != false ) {
            $result = json_decode( $result, true );
            if ( empty( $result['error'] ) ) {
                $this->test_id = $result['test_id'];

                if ( isset( $result['state'] ) AND !empty( $result['state'] ) )
                    $this->result = $result;

                return $this->test_id;
            } else {
                $this->error = $result['error'];
            }
        }

        return false;
    }

    /**
     * load()
     *
     * Query an existing test from GTMetrix API
     *
     * $test_id  string  The existing test's test ID
     *
     * test_id must be valid, or else all query methods will fail
     */
    public function load( $test_id ) {
        $this->test_id = $test_id;

        if ( !empty( $this->result ) )
            $this->result = array( );
    }

    /**
     * delete()
     *
     * Delete the test from the GTMetrix database
     *
     * Precondition: member test_id is not empty
     *
     * returns message on success, false otherwise
     */
    public function delete() {
        if ( !$this->checkid() )
            return false;

        $command = "test/" . $this->test_id;

        $result = $this->query( $command, "DELETE" );
        if ( $result != false ) {
            $result = json_decode( $result, true );
            return ($result['message']) ? true : false;
        }

        return false;
    }

    /**
     * get_test_id()
     *
     * Returns the test_id, false if test_id is not set
     */
    public function get_test_id() {
        return ($this->test_id) ? $this->test_id : false;
    }

    /**
     * poll_state()
     *
     * polls the state of the test
     *
     * Precondition: member test_id is not empty
     *
     * The class will save a copy of the state object, 
     * which contains information such as the test results and resource urls (or nothing if an error occured)
     * so that additional queries to the API is not required.
     *
     * returns true on successful poll, or false on network error or no test_id
     */
    public function poll_state() {
        if ( !$this->checkid() )
            return false;

        if ( !empty( $this->result ) ) {
            if ( $this->result['state'] == "completed" )
                return true;
        }

        $command = "test/" . $this->test_id;

        $result = $this->query( $command );
        if ( $result != false ) {
            $result = json_decode( $result, true );

            if ( !empty( $result['error'] ) AND !isset( $result['state'] ) ) {
                $this->error = $result['error'];
                return false;
            }

            $this->result = $result;
            if ( $result['state'] == 'error' )
                $this->error = $result['error'];

            return true;
        }

        return false;
    }

    /**
     * state()
     *
     * Returns the state of the test (queued, started, completed, error)
     *
     * Precondition: member test_id is not empty
     *
     * returns the state of the test, or false on networking error
     */
    public function state() {
        if ( !$this->checkid() )
            return false;

        if ( empty( $this->result ) )
            return false;

        return $this->result['state'];
    }

    /**
     * completed()
     *
     * returns true if the test is complete, false otherwise
     */
    public function completed() {
        return ($this->state() == 'completed') ? true : false;
    }

    /*
     * get_results()
     *
     * locks and polls API until test results are received
     * waits for 6 seconds before first check, then polls every 2 seconds
     * at the 30 second mark it reduces frequency to 5 seconds
     */

    public function get_results() {
        sleep( 6 );
        $i = 1;
        while ( $this->poll_state() ) {
            if ( $this->state() == 'completed' OR $this->state() == 'error' )
                break;
            sleep( $i++ <= 13 ? 2 : 5  );
        }
    }

    /**
     * locations()
     *
     * Returns a list of GTMetrix server locations accompanied by their location IDs
     * that can be used in newTest() to select a different server location for testing
     *
     * returns the location list in array format, the error message if an error occured,
     * or false if a query error occured.
     */
    public function locations() {
        $result = $this->query( 'locations' );
        if ( $result != false ) {
            $result = json_decode( $result, true );
            if ( empty( $result['error'] ) ) {
                return $result;
            } else {
                $this->error = $result['error'];
            }
        }

        return false;
    }

    /**
     * results()
     *
     * Get test results
     *
     * returns the test results, or false if the test hasn't completed yet
     */
    public function results() {
        if ( !$this->completed() )
            return false;

        return $this->result['results'];
    }

    /**
     * resources()
     *
     * Get test resource URLs
     *
     * returns the test resources, or false if the test hasn't completed yet
     */
    public function resources( $item = 'all' ) {
        if ( !$this->completed() )
            return false;

        return $this->result['resources'];
    }

    /**
     * fetch_resources()
     *
     * Downloads test resources to a specified location
     *
     * $items     string/array        item(s) to download (empty or null will result in all resources downloading)
     * $location string                location to download to
     * 
     * returns true if successful, the error message if an error occured
     */
    public function download_resources( $items = null, $location = './', $append_test_id = false ) {

        if ( !$this->completed() )
            return false;

        $resources = $this->result['resources'];
        $resource_types = array(
            'report_pdf' => 'pdf',
            'pagespeed' => 'txt',
            'har' => 'txt',
            'pagespeed_files' => 'tar',
            'yslow' => 'txt',
            'screenshot' => 'jpg',
        );

        if ( !$items or $items == '' ) {
            $items = array_keys( $resource_types );
        }

        if ( !is_array( $items ) ) {
            $items = array( $items );
        }

        if ( !is_writable( $location ) ) {
            $this->error = 'Permission denied in ' . $location;
            return false;
        }

        foreach ( $items as $item ) {

            if ( !array_key_exists( $item, $resources ) ) {
                $this->error = $item . ' does not exist';
                return false;
            }

            $file = fopen( $location . $item . ($append_test_id ? '-' . $this->test_id : '') . '.' . $resource_types[$item], "w" );

            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $resources[$item] );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_FILE, $file );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
            curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
            curl_setopt( $ch, CURLOPT_USERPWD, $this->username . ":" . $this->password );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

            $results = curl_exec( $ch );
            if ( $results === false )
                $this->error = curl_error( $ch );

            curl_close( $ch );
        }
        return true;
    }

    /**
     * status()
     *
     * Get account status
     *
     * returns credits remaining, and timestamp of next top-up
     */
    public function status() {
        $result = $this->query( 'status' );
        if ( $result != false ) {
            $result = json_decode( $result, true );
            if ( empty( $result['error'] ) ) {
                return $result;
            } else {
                $this->error = $result['error'];
            }
        }
        return false;
    }

}

?>