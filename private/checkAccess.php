<?php
//
// Description
// ===========
// This function will check if the user has access to the news aggregator method they requested.
//
// Arguments
// =========
// ciniki:
// tnid:         The ID of the tenant the request is for.
// method:              The requested public method.
// 
// Returns
// =======
// <rsp stat="ok" />
//
function ciniki_newsaggregator_checkAccess($ciniki, $tnid, $method) {
    //
    // Most methods won't need to check the tenant id
    //
    $user_methods = array(
        'ciniki.newsaggregator.articleList',
        'ciniki.newsaggregator.articleGet',
        'ciniki.newsaggregator.subscriptionList',
        'ciniki.newsaggregator.subscriptionListCategories',
        );
    if( ($tnid == null || $tnid == '' || $tnid == 0 || $tnid == '0') && in_array($method, $user_methods) ) {
        return array('stat'=>'ok');
    }

    //
    // Check if the tenant is active and the module is enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'checkModuleAccess');
    $rc = ciniki_tenants_checkModuleAccess($ciniki, $tnid, 'ciniki', 'newsaggregator');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( !isset($rc['ruleset']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.1', 'msg'=>'No permissions granted'));
    }

    //
    // Check the users is a part of the tenant
    //
    $strsql = "SELECT tnid, user_id FROM ciniki_tenant_users "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "AND package = 'ciniki' "
        . "AND status = 10 "
        . "AND (permission_group = 'owners' OR permission_group = 'employees' OR permission_group = 'resellers' ) "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.tenants', 'user');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // If the user has permission, return ok
    //
    if( isset($rc['rows']) && isset($rc['rows'][0]) 
        && $rc['rows'][0]['user_id'] > 0 && $rc['rows'][0]['user_id'] == $ciniki['session']['user']['id'] ) {
        return array('stat'=>'ok');
    }

    //
    // By default, fail
    //
    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.2', 'msg'=>'Access denied.'));
}
?>
