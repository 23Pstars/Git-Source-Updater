<?php

/**
 * LRsoft Corp.
 * http://lrsoft.co.id
 *
 * Author: Zaf
 * Date: 12/16/15
 * Time: 12:26 PM
 */

define( 'DS', DIRECTORY_SEPARATOR );
define( 'ABS_PATH', dirname( __FILE__ ) );

/**
 * Class Bitbucket
 */
class Bitbucket {

    /**
     * max limit = 50
     */
    const default_limit = 50;
    /**
     *
     */
    const default_branch = 'master';
    /**
     *
     */
    const version_file = 'VERSION.md';
    /**
     *
     */
    const type_git_remove = 'remove';

    /**
     * bitbucket API
     */
    const BITBUCKET_API_REPOSITORY = 'https://bitbucket.org/api/1.0/repositories';

    /**
     * credentials
     */
    private $_username;
    /**
     * @var
     */
    private $_password;

    /**
     * repository
     */
    private $_account_slug;
    /**
     * @var
     */
    private $_repo_slug;
    /**
     * @var
     */
    private $_branch;

    /**
     * @var int
     */
    private $_limit;

    /**
     * @var array
     */
    private $ignored_files;
    /**
     * @var
     */
    private $changesets_url;
    /**
     * @var
     */
    private $raw_url;
    /**
     * @var
     */
    private $last_update_timestamp;
    /**
     * @var
     */
    private $new_last_update_timestamp;
    /**
     * @var array
     */
    private $updated_lists;
    /**
     * @var array
     */
    private $removed_lists;
    /**
     * @var array
     */
    private $status_lists;

    private $cURL;

    /**
     * Bitbucket constructor.
     * @param $_username
     * @param $_password
     * @param $_account_slug
     * @param $_repo_slug
     */
    public function __construct( $_username, $_password, $_account_slug, $_repo_slug ) {
        $this->_username = $_username;
        $this->_password = $_password;
        $this->_account_slug = $_account_slug;
        $this->_repo_slug = $_repo_slug;

        $this->_branch = self::default_branch;
        $this->_limit = self::default_limit;

        $this->updated_lists = array();
        $this->removed_lists = array();
        $this->status_lists = array();

        $this->ignored_files = array( self::version_file );

        $this->cURL = new cURL();
    }

    /**
     * @return $this
     */
    public function _init() {

        $this->cURL->_init_auth( $this->_username, $this->_password );

        $this->_init_update_timestamp()
            ->_init_urls()
            ->_init_changesets()
            ->_exec_lists()
            ->_update_version_file();

        return $this;

    }

    /**
     * @return $this
     */
    private function _exec_lists() {

        /** exec daftar file yang dihapus */
        if( !empty( $this->removed_lists ) )
            foreach( $this->removed_lists as $_path => $_raw_node )
                $this->status_lists[ $_path ] = file_exists( ABS_PATH . DS . $_path ) && unlink( ABS_PATH . DS . $_path ) ? 'removed' : 'not found';

        $response_content = $this->cURL->_fetch_multi( $this->updated_lists );

        foreach( $response_content as $path => $content ) {
            if( !is_dir( dirname( $path ) ) ) mkdir( dirname( $path ), 0777, true );
            $this->status_lists[ $path ] = file_put_contents( ABS_PATH . DS . $path, $content ) ? 'updated' : 'fail to update';
        }

        return $this;

    }

    /**
     * @return $this
     */
    private function _init_changesets() {

        $branch = $this->_branch;

        $changesets_response_arr = $this->cURL->_fetch( $this->changesets_url, 'json' );
        $changesets_lists = $changesets_response_arr[ 'changesets' ];
        $changesets_lists = array_reverse( $changesets_lists );
        $changesets_lists = array_filter( $changesets_lists, function( $el ) use ( $branch ) {
            /** hanya branch yang dipilih */
            return empty( $el[ 'branch' ] ) || $el[ 'branch' ] == $branch ? $el : false;
        } );

        foreach( $changesets_lists as $k => $resp ) {

            0 != $k || $this->new_last_update_timestamp = $resp['utctimestamp'];

            if ( strtotime( $resp['utctimestamp'] ) <= strtotime( $this->last_update_timestamp ) ) break;

            foreach( $resp[ 'files' ] as $file ) {

                $_file = $file[ 'file' ];
                $_type = $file[ 'type' ];
                $_raw_node = $resp[ 'raw_node' ];

                /** skip jika VERSION.md */
                if( $_file == self::version_file ) continue;

                switch( $_type ) {

                    /** jika remove */
                    case self::type_git_remove  :
                        if( !array_key_exists( $_file, $this->removed_lists ) && !in_array( $_file, $this->ignored_files ) )
                            $removed_lists[ $_file ] = $this->raw_url . DS . $_raw_node . DS . $_file;
                        break;

                    /** added, modified */
                    default :
                        if( !array_key_exists( $_file, $this->updated_lists ) && !in_array( $_file, $this->ignored_files ) )
                            $this->updated_lists[ $_file ] = $this->raw_url . DS . $_raw_node . DS . $_file;
                        break;

                }
            }
        }

        return $this;

    }

    /**
     * @return $this
     */
    private function _init_urls() {
        $this->changesets_url = self::BITBUCKET_API_REPOSITORY . DS . $this->_account_slug . DS . $this->_repo_slug . DS . 'changesets?branch=' . $this->_branch . '&limit=' . $this->_limit;
        $this->raw_url = self::BITBUCKET_API_REPOSITORY . DS . $this->_account_slug . DS . $this->_repo_slug . DS . 'raw';
        return $this;
    }

    /**
     * @return $this
     */
    private function _init_update_timestamp() {
        $_load_from_file = preg_match( '/@last-update : (.*)/', file_get_contents( self::version_file ), $matches );
        $this->last_update_timestamp = date( 'Y-m-d H:i:s', ( $_load_from_file ? strtotime( $matches[ 1 ] ) : time() ) );
        return $this;
    }

    /**
     * @return $this
     */
    private function _update_version_file() {
        if( !empty( $this->new_last_update_timestamp ) )
            $this->status_lists[ self::version_file ] = file_put_contents( ABS_PATH . DS . self::version_file, '@last-update : ' . date( 'c', strtotime( $this->new_last_update_timestamp ) ) ) ? 'updated' : 'fail to update';
        return $this;
    }

    /**
     * @param $branch
     * @return $this
     */
    public function set_branch($branch ) {
        $this->_branch = $branch;
        return $this;
    }

    /**
     * @param $limit
     * @return $this
     */
    public function set_limit($limit ) {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * @param $ignored_file
     * @return $this
     */
    public function append_ignored_file($ignored_file ) {
        $this->ignored_files[] = $ignored_file;
        return $this;
    }

    /**
     * @return array
     */
    public function get_status() {
        return $this->status_lists;
    }

}