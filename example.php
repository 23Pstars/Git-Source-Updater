<?php
/**
 * LRsoft Corp.
 * http://lrsoft.co.id
 *
 * Author: Zaf
 * Date: 12/17/15
 * Time: 6:59 AM
 */

set_time_limit( 0 );

require_once( 'cURL.php' );
require_once( 'GitHub.php' );
require_once( 'Bitbucket.php' );

/** Bitbucket credentials */
$username       = 'User';
$password       = 'password';
$account_slug   = 'user';
$repo_slug      = 'repo';


/** /
$github = new GitHub( $username, $password, $account_slug, $repo_slug );
$status = $github->_init()->get_status();
/***/


/** /
$bitbucket = new Bitbucket( $username, $password, $account_slug, $repo_slug );
$status = $bitbucket->_init()->get_status();
/***/

//print_r( $status );