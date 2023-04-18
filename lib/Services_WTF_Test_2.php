<?php

/*
 * Service_WTF_Test
 *
 * Version 2.0
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
 *  2.0
 */

class Services_WTF_Test_v2 {
    const api_url = 'https://gtmetrix.com/api/2.0';
    private $username = '';
    private $password = '';
    private $user_agent = 'Services_WTF_Test_php/2.0 (+https://gtmetrix.com/api/docs/2.0/)';
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
        error_log('COMMAND ' . $command );
        $ch = curl_init();

        if ( substr( $command, 0, strlen( self::api_url ) - 1 ) == self::api_url ) {
            $URL = $command;
        } else {
            $URL = self::api_url . '/' . $command;
        }
        //error_log('URL ' . $URL );
        
        curl_setopt( $ch, CURLOPT_URL, $URL );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
        curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
        curl_setopt( $ch, CURLOPT_USERPWD, $this->password . ":");
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $req );
        // CURLOPT_SSL_VERIFYPEER turned off to avoid failure when cURL has no CA cert bundle: see http://curl.haxx.se/docs/sslcerts.html
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        if ( $req == 'POST' ) {
            $params = json_encode( $params );
            //error_log('PARAMS ' . print_r($params, TRUE));
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, ['Content-Type:application/vnd.api+json'] );
        }
        $streamVerboseHandle = fopen('php://temp', 'w+');
        curl_setopt( $ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_STDERR, $streamVerboseHandle);
        $results = curl_exec( $ch );
        if ( $results === false ) {
            $this->error = curl_error( $ch );
        } else {
            error_log(curl_getinfo( $ch, CURLINFO_HEADER_OUT));
        }
        curl_close( $ch );

        return $results;
    }

    protected function checkid() {
        error_log( 'checkid' );
        if ( empty( $this->test_id ) ) {
            error_log( 'current error message: ' . $this->error );
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
        error_log('V2.0 test: ' . print_r($data, TRUE ) );
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
        if( isset( $data['cookies'] ) && $data['cookies'] ) {
            $cookies = explode(PHP_EOL, $data['cookies'] );
            $data['cookies'] = $cookies;
        }
        $post_data = array(
            'data' => array(
                'type' => 'test',
                'attributes' => $data
            )
        );
        //$post_data = http_build_query( $post_data );
        //error_log('V2.0 test string data: ' . $post_data );
        //$headers = array(
        //    'Content-Type' => 'application/vnd.api+json'
        //);
        $result = $this->query( 'tests', 'POST', $post_data );
        if ( $result != false ) {
            $result = json_decode( $result, true );
            error_log( "RESULT " . print_r( $result, TRUE ) );
            if ( empty( $result['errors'] ) ) {
                $this->test_id = $result['data']['id'];

                if ( isset( $result['data']['attributes']['state'] ) AND !empty( $result['data']['attributes']['state'] ) )
                    $this->result = $result;

                return $this->test_id;
            } else {
                //there could conceivably be more than one error message
                foreach( $result['errors'] as $i => $error_data ) {
                    $this->error .= $result['errors'][$i]['detail'] . "\n";
                }
                //$this->error = $result['errors'][0]['detail'];
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
            if ( $this->result['data']['attributes']['state'] == "completed" )
                return true;
        }

        $command = "tests/" . $this->test_id;
        //error_log( $command );
        $result = $this->query( $command );
        if ( $result != false ) {
            $result = json_decode( $result, true );
            //error_log('poll_state RESULT ' . print_r($result, TRUE ) );
            if ( !empty( $result['error'] ) AND !isset( $result['data']['attributes']['state'] ) ) {
                $this->error = $result['error'];
                return false;
            }
            // Docs say there should be a redirect to the "report" call, but that doesn't seem to be the case: even if completed, the response is type "test"
            if( $result['data']['attributes']['state'] == "completed" && isset( $result['data']['attributes']['report'] ) ) {
                $command = "reports/" . $result['data']['attributes']['report'];
                $report_result = $this->query( $command );
                if ( $report_result != false ) {
                    $report_result = json_decode( $report_result, true );
                    if ( !empty( $result['error'] ) ) {
                        $this->error = $report_result['error'];
                        return false;
                    } else {
                        $report_result['data']['attributes']['state'] = "completed";
                        $this->result = $report_result;
                    }
                }
            } else {
                $this->result = $result;
                if ( $result['data']['attributes']['state'] == 'error' )
                    $this->error = $result['error'];
            }
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

        return $this->result['data']['attributes']['state'];
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
        $this->poll_state();
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
     * browsers()
     *
     * Returns a list of GTMetrix browsers accompanied by their  IDs
     * that can be used in newTest() to select a different server location for testing
     *
     * returns the browser list in array format, the error message if an error occured,
     * or false if a query error occured.
     */
    public function browsers() {
        $result = $this->query( 'browsers' );
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
        $results = $this->result['data']['attributes'];
        $results['report_url'] = $this->result['data']['links']['report_url'];
        $results['report_id'] = $this->result['data']['id'];
        return $results;
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