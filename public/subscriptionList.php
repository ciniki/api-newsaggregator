<?php
//
// Description
// -----------
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:			The ID of the business to add the exhibition to.
//
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_newsaggregator_subscriptionList(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'name'=>'Business'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
	// Make sure the user has permission to this method
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['business_id'], 'ciniki.newsaggregator.subscriptionList'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

	//
	// Get the list of categories for the requesting user
	//
	$strsql = "SELECT 'unread' AS unread, "
		. "ciniki_newsaggregator_feeds.title, "
		. "ciniki_newsaggregator_feeds.feed_url, "
		. "COUNT(ciniki_newsaggregator_articles.id) AS unread_count, "
		. "ciniki_newsaggregator_article_users.flags AS user_flags "
		. "FROM ciniki_newsaggregator_subscriptions "
		. "LEFT JOIN ciniki_newsaggregator_feeds ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_feeds.id ) "
		. "LEFT JOIN ciniki_newsaggregator_articles ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_articles.feed_id "
			. "AND ciniki_newsaggregator_subscriptions.date_read_all < ciniki_newsaggregator_articles.published_date ) "
		. "LEFT JOIN ciniki_newsaggregator_article_users ON (ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
			. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
			. "AND (ciniki_newsaggregator_article_users.flags&0x01) = 0 "
			. ") "
		. "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' ";
	if( $args['business_id'] != '' && $args['business_id'] != '0' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	}
//	$strsql .= "AND (ciniki_newsaggregator_article_users.flags = NULL OR (ciniki_newsaggregator_article_users.flags&0x01) = 0x01) "
//	$strsql .= "AND ciniki_newsaggregator_article_users.flags = 0 ";
	$strsql .= "GROUP BY ciniki_newsaggregator_feeds.title "
		. "ORDER BY ciniki_newsaggregator_feeds.title "
		. "";
//	error_log($strsql);
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
		array('container'=>'unread', 'fname'=>'unread', 'name'=>'all',
			'fields'=>array('unread_count'), 'sums'=>array('unread_count')),
		array('container'=>'feeds', 'fname'=>'title', 'name'=>'feed',
			'fields'=>array('title', 'feed_url', 'unread_count', 'flags'=>'user_flags')),
		));
	
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['unread']) ) {
		return array('stat'=>'ok', 'unread_count'=>'0', 'feeds'=>array());
	}
	$unread = $rc['unread'][0]['all'];
	if( !isset($unread['feeds']) ) {
		return array('stat'=>'ok', 'unread_count'=>'0', 'feeds'=>array());
	}

	return array('stat'=>'ok', 'unread_count'=>$unread['unread_count'], 'feeds'=>$unread['feeds']);
}
?>
