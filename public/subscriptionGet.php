<?php
//
// Description
// -----------
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:         The ID of the business to add the exhibition to.
//
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_newsaggregator_subscriptionGet(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'subscription_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Subscription'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure the user has permission to this method
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['business_id'], 'ciniki.newsaggregator.subscriptionGet'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Get the list of categories for the requesting user
    //
    $strsql = "SELECT ciniki_newsaggregator_subscriptions.category, "
        . "ciniki_newsaggregator_subscriptions.flags, "
        . "ciniki_newsaggregator_feeds.title AS feed_title, "
        . "ciniki_newsaggregator_feeds.feed_url, "
        . "ciniki_newsaggregator_feeds.site_url "
        . "FROM ciniki_newsaggregator_subscriptions, ciniki_newsaggregator_feeds "
        . "WHERE ciniki_newsaggregator_subscriptions.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "AND ciniki_newsaggregator_subscriptions.id = '" . ciniki_core_dbQuote($ciniki, $args['subscription_id']) . "' "
        . "AND ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_feeds.id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'subscription');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['subscription']) ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'918', 'msg'=>"Unable to find the subscription"));
    }

    return array('stat'=>'ok', 'subscription'=>$rc['subscription']);
}
?>
