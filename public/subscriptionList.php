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
        'category'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Category'), 
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
	// Get the list of feeds
	//
	$strsql = "SELECT ciniki_newsaggregator_subscriptions.category AS category, "
		. "ciniki_newsaggregator_feeds.id AS feed_id, "
		. "IF(ciniki_newsaggregator_feeds.title='', ciniki_newsaggregator_feeds.feed_url, ciniki_newsaggregator_feeds.title) AS title, "
		. "ciniki_newsaggregator_feeds.feed_url, "
		. "'0' AS unread_count "
		. "FROM ciniki_newsaggregator_subscriptions "
		. "LEFT JOIN ciniki_newsaggregator_feeds ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_feeds.id ) "
		. "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' ";
	if( $args['business_id'] != '' && $args['business_id'] != '0' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	}
	if( isset($args['category']) ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.category = '" . ciniki_core_dbQuote($ciniki, $args['category']) . "' ";
	}
	$strsql .= "ORDER BY ciniki_newsaggregator_feeds.title "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
		array('container'=>'feeds', 'fname'=>'feed_id', 'name'=>'feed',
			'fields'=>array('id'=>'feed_id', 'category', 'title', 'feed_url', 'unread_count')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	// No feeds found, return empty array
	if( !isset($rc['feeds']) ) {
		return array('stat'=>'ok', 'unread_count'=>'0', 'feeds'=>array());
	}
	$feeds = $rc['feeds'];
	if( isset($feeds[0]) ) {
		$category = $feeds[0]['feed']['category'];
	} else {
		$category = '';
	}

	//
	// Get the list of categories for the requesting user
	//
	$strsql = "SELECT ciniki_newsaggregator_feeds.id AS feed_id, "
		. "COUNT(ciniki_newsaggregator_articles.id) AS unread_count "
		. "FROM ciniki_newsaggregator_subscriptions "
		. "LEFT JOIN ciniki_newsaggregator_feeds ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_feeds.id ) "
		. "LEFT JOIN ciniki_newsaggregator_articles ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_articles.feed_id "
			. "AND ciniki_newsaggregator_subscriptions.date_read_all < ciniki_newsaggregator_articles.published_date ) "
		. "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' ";
	if( $args['business_id'] != '' && $args['business_id'] != '0' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	}
	if( isset($args['category']) ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.category = '" . ciniki_core_dbQuote($ciniki, $args['category']) . "' ";
	}
	$strsql .= "AND NOT EXISTS (SELECT article_id FROM ciniki_newsaggregator_article_users "
		. "WHERE ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
		. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
		. "AND (ciniki_newsaggregator_article_users.flags&0x01) = 1 "
		. ") ";
	$strsql .= "GROUP BY ciniki_newsaggregator_feeds.id "
		. "ORDER BY ciniki_newsaggregator_feeds.title "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
	$rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
//		array('container'=>'unread', 'fname'=>'unread', 'name'=>'all',
//			'fields'=>array('unread_count', 'category'), 'sums'=>array('unread_count')),
		array('container'=>'feeds', 'fname'=>'feed_id', 
			'fields'=>array('id'=>'feed_id', 'unread_count')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['feeds']) ) {
		return array('stat'=>'ok', 'unread_count'=>'0', 'feeds'=>$feeds);
	}
	$unread = $rc['feeds'];

	//
	// Merge unread_count into categories
	//
	$unread_total = 0;
	foreach($feeds as $fid => $feed) {
		$feed = $feed['feed'];
		if( isset($unread[$feed['id']]) ) {
			$feeds[$fid]['feed']['unread_count'] = $unread[$feed['id']]['unread_count'];
			$unread_total += $unread[$feed['id']]['unread_count'];
		}
	}

	return array('stat'=>'ok', 'unread_count'=>$unread_total, 'category'=>$category, 'feeds'=>$feeds);
}
?>
