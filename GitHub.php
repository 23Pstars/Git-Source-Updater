<?php

/**
 * LRsoft Corp.
 * http://lrsoft.co.id
 *
 * Author: Zaf
 * Date: 12/17/15
 * Time: 5:27 PM
 *
 * https://api.github.com/repos/23Pstars/Git-Source-Updater/commits?since=YYYY-MM-DDTHH:MM:SSZ&sha=master
 *
 */

define( 'DS', DIRECTORY_SEPARATOR );
define( 'ABS_PATH', dirname( __FILE__ ) );

/**
 * Class GitHub
 */
class GitHub {

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
    const type_git_removed = 'removed';

    /**
     * https://developer.github.com/v3/#rate-limiting
     */
    const limit_request = 55;

    /**
     * GitHub API
     */
    const GITHUB_API_REPOSITORY = 'https://api.github.com/repos';

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
     * @var array
     */
    private $ignored_files;

    /**
     * @var
     */
    private $commits_url;

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
    private $commit_lists;

    /**
     * @var array
     */
    private $updated_lists;

    /**
     * @var array
     */
    private $removed_lists;

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

        $this->commit_lists = array();
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
            ->_init_commits()
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
            foreach( $this->removed_lists as $_filename => $_raw_url )
                $this->status_lists[ $_filename ] = file_exists( ABS_PATH . DS . $_filename ) && unlink( ABS_PATH . DS . $_filename ) ? 'removed' : 'not found';

        $response_contents = array();
        foreach( $this->updated_lists as $path => $url )
            $response_contents[ $path ] = $this->cURL->_fetch( $url );

        foreach( $response_contents as $_filename => $content ) {
            if( !is_dir( dirname( $_filename ) ) ) mkdir( dirname( $_filename ), 0777, true );
            $this->status_lists[ $_filename ] = file_put_contents( ABS_PATH . DS . $_filename, $content ) ? 'updated' : 'fail to update';
        }

        return $this;

    }

    /**
     * return $this
     */
    private function _init_changesets() {

        $response_contents = array();
        foreach( $this->commit_lists as $sha => $url )
            $response_contents[] = $this->cURL->_fetch( $url, 'json' );

        foreach( $response_contents as $content ) {

            foreach( $content[ 'files' ] as $file ) {

                $_filename = $file[ 'filename' ];
                $_status = $file[ 'status' ];
                $_raw_url = $file[ 'raw_url' ];

                /** skip jika VERSION.md */
                if( $_filename == self::version_file ) continue;

                switch( $_status ) {

                    /** jika removed */
                    case self::type_git_removed  :
                        if( !array_key_exists( $_filename, $this->removed_lists ) && !in_array( $_filename, $this->ignored_files ) )
                            $removed_lists[ $_filename ] = $_raw_url;
                        break;

                    /** added, modified */
                    default :
                        if( !array_key_exists( $_filename, $this->updated_lists ) && !in_array( $_filename, $this->ignored_files ) )
                            $this->updated_lists[ $_filename ] = $_raw_url;
                        break;

                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function _init_commits() {

        $response_lists = $this->cURL->_fetch( $this->commits_url, 'json' );

        foreach( $response_lists as $k => $resp ) {

            /** update timestamp, digunakan sebagai pointer update selanjutnya (VERSION.md)  */
            $k != 0 || $this->new_last_update_timestamp = $resp[ 'commit' ][ 'committer' ][ 'date' ];

            $this->commit_lists[ $resp[ 'sha' ] ] = $resp[ 'url' ];

        }

        return $this;

    }

    /**
     * @return $this
     */
    private function _init_urls() {
        $this->commits_url = self::GITHUB_API_REPOSITORY . DS . $this->_account_slug . DS . $this->_repo_slug . DS . 'commits?sha=' . $this->_branch . '&since=' . $this->last_update_timestamp;
        $this->raw_url = self::GITHUB_API_REPOSITORY . DS . $this->_account_slug . DS . $this->_repo_slug . DS . 'raw';
        return $this;
    }

    /**
     * @return $this
     */
    private function _init_update_timestamp() {
        $_load_from_file = preg_match( '/@last-update : (.*)/', file_get_contents( self::version_file ), $matches );
        $this->last_update_timestamp = date( 'Y-m-d\TH:i:s\Z', ( $_load_from_file ? strtotime( $matches[ 1 ] ) : time() ) );
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