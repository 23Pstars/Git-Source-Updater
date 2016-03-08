<?php

/**
 * LRsoft Corp.
 * http://lrsoft.co.id
 *
 * Author: Zaf
 */

class cURL {

    private $_curlopt_userpwd           = "";
    private $_curlopt_returntransfer    = true;
    private $_curlopt_followlocation    = true;
    private $_curlopt_encoding          = "";
    private $_curlopt_maxredirs         = 10;
    private $_curlopt_timeout           = 30;
    private $_curlopt_http_version      = CURL_HTTP_VERSION_1_1;
    private $_curlopt_customrequest     = 'GET';
    private $_curlopt_httpheader        = array(
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36',
        'Content-Type: application/json'
    );

    function _init_auth( $username, $password ) {
        $this->_curlopt_userpwd = $username . ':' . $password;
        return $this;
    }

    function _get_curlopt( $url ) {
        $out = array(
            CURLOPT_URL                 => $url,
            CURLOPT_RETURNTRANSFER      => $this->_curlopt_returntransfer,
            CURLOPT_FOLLOWLOCATION      => $this->_curlopt_followlocation,
            CURLOPT_ENCODING            => $this->_curlopt_encoding,
            CURLOPT_MAXREDIRS           => $this->_curlopt_maxredirs,
            CURLOPT_TIMEOUT             => $this->_curlopt_timeout,
            CURLOPT_HTTP_VERSION        => $this->_curlopt_http_version,
            CURLOPT_CUSTOMREQUEST       => $this->_curlopt_customrequest,
            CURLOPT_HTTPHEADER          => $this->_curlopt_httpheader
        );
        if( !empty( $this->_curlopt_userpwd ) ) {
            $out[ CURLOPT_HTTPAUTH ]    = CURLAUTH_BASIC;
            $out[ CURLOPT_USERPWD ]     = $this->_curlopt_userpwd;
        }
        return $out;
    }

    function _fetch( $url, $out = 'raw' ) {
        $curl = curl_init(); curl_setopt_array( $curl, $this->_get_curlopt( $url ) );
        $curl_exec = curl_exec( $curl );
        $response = 'json' == $out ? json_decode( $curl_exec, true ) : $curl_exec;
        curl_close( $curl ); return $response;

    }

    function _fetch_multi( $urls = array() ) {

        /** multi curl handler */
        $curl_handler = array();
        $multi_curl_handler = curl_multi_init();
        foreach( $urls as $sha => $url ) {
            $curl_handler[ $sha ] = curl_init(); curl_setopt_array( $curl_handler[ $sha ], $this->_get_curlopt( $url ) );
            curl_multi_add_handle( $multi_curl_handler, $curl_handler[ $sha ] );
        }

        do {
            $execReturnValue = curl_multi_exec( $multi_curl_handler, $runningHandles );
        } while( $execReturnValue == CURLM_CALL_MULTI_PERFORM );

        while( $runningHandles && $execReturnValue == CURLM_OK ) {
            $numberReady = curl_multi_select( $multi_curl_handler );
            if ($numberReady != -1) {
                do {
                    $execReturnValue = curl_multi_exec( $multi_curl_handler, $runningHandles );
                } while( $execReturnValue == CURLM_CALL_MULTI_PERFORM );
            }
        }

        $response_content = array();
        foreach( $urls as $sha => $url ) {
            if( curl_error( $curl_handler[ $sha ] ) == '' )
                $response_content[ $sha ] = curl_multi_getcontent( $curl_handler[ $sha ] );

            curl_multi_remove_handle( $multi_curl_handler, $curl_handler[ $sha ] );
            curl_close( $curl_handler[ $sha ] );

        }

        curl_multi_close( $multi_curl_handler );

        return $response_content;
    }

}