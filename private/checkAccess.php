<?php
//
// Description
// ===========
// This function will check if the user has access to the news aggregator method they requested.
//
// Arguments
// =========
// ciniki:
// business_id: 		The ID of the business the request is for.
// method:				The requested public method.
// 
// Returns
// =======
// <rsp stat="ok" />
//
function ciniki_newsaggregator_checkAccess($ciniki, $business_id, $method) {
	//
	// Most methods won't need to check the business id
	//
	$user_methods = array(
		'ciniki.newsaggregator.articleList',
		'ciniki.newsaggregator.articleGet',
		'ciniki.newsaggregator.subscriptionList',
		'ciniki.newsaggregator.subscriptionListCategories',
		);
	if( ($business_id == null || $business_id == '' || $business_id == 0 || $business_id == '0') && in_array($method, $user_methods) ) {
		return array('stat'=>'ok');
	}

	//
	// Check if the business is active and the module is enabled
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'checkModuleAccess');
	$rc = ciniki_businesses_checkModuleAccess($ciniki, $business_id, 'ciniki', 'newsaggregator');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	if( !isset($rc['ruleset']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'912', 'msg'=>'No permissions granted'));
	}

	//
	// Check the users is a part of the business
	//
	$strsql = "SELECT business_id, user_id FROM ciniki_business_users "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
		. "AND package = 'ciniki' "
		. "AND status = 10 "
        . "AND (permission_group = 'owners' OR permission_group = 'employees' OR permission_group = 'resellers' ) "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.businesses', 'user');
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
	return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'914', 'msg'=>'Access denied.'));
}
?>
