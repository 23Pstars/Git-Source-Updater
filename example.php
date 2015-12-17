<?php
/**
 * LRsoft Corp.
 * http://lrsoft.co.id
 *
 * Author: Zaf
 * Date: 12/17/15
 * Time: 6:59 AM
 */

require_once( 'Bitbucket.php' );

/** Bitbucket credentials */
$username       = 'User';
$password       = 'password';
$account_slug   = 'user';
$repo_slug      = 'repo';

$bitbucket = new Bitbucket( $username, $password, $account_slug, $repo_slug );

$bitbucket
    ->set_limit( 25 )
    ->set_branch( 'versi-1.2.3' )
    ->_init();

// print_r( $bitbucket->get_status() );