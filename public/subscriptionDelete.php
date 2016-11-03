<?php
//
// Description
// -----------
// This method adds a subscription for a user to an existing or new news feed.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:         The ID of the business to add the subscription to.
// feed_id:             The ID of an existing feed to subscribe to.
// feed_url:            The URL of the feed.
// category:            The category for the subscription.
// flags:               The flags for the subscription. **future**
//
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_newsaggregator_subscriptionDelete(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'feed_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Feed'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['business_id'], 'ciniki.newsaggregator.subscriptionDelete'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //  
    // Turn off autocommit
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Find the feed ID
    //
    $strsql = "SELECT id, uuid FROM ciniki_newsaggregator_subscriptions "
        . "WHERE user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "AND feed_id = '" . ciniki_core_dbQuote($ciniki, $args['feed_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'feed');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
        return $rc;
    }
    if( !isset($rc['feed']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.9', 'msg'=>'Unable to find feed'));
    }
    $feed = $rc['feed'];

    $strsql = "DELETE FROM ciniki_newsaggregator_subscriptions "
        . "WHERE user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "AND feed_id = '" . ciniki_core_dbQuote($ciniki, $args['feed_id']) . "' "
        . "";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
        3, 'ciniki_newsaggregator_subscriptions', $feed['id'], '*', ''); 
    $ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.subscription', 
        'args'=>array('delete_id'=>$feed['id'], 'delete_uuid'=>$feed['uuid']));

    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'newsaggregator');

    return array('stat'=>'ok');
}
?>
