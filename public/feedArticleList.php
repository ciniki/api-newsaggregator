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
function ciniki_newsaggregator_feedArticleList(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'name'=>'Business'), 
        'feed_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Feed'), 
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
	// Make sure the user has permission to this method
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['business_id'], 'ciniki.newsaggregator.feedArticleList'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
	$datetime_format = ciniki_users_datetimeFormat($ciniki);

	//
	// Get the feed info
	//
	$strsql = "SELECT title, site_url, "
		. "DATE_FORMAT(last_checked, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS last_checked "
		. "FROM ciniki_newsaggregator_feeds "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['feed_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'feed');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( isset($rc['feed']) ) {
		$feed = $rc['feed'];
	}

	//
	// Get the unread articles
	//
	$strsql = "SELECT ciniki_newsaggregator_articles.id AS article_id, "
		. "ciniki_newsaggregator_articles.title, "
		. "DATE_FORMAT(published_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS published_date, "
		. "IF((ciniki_newsaggregator_article_users.flags&0x01)=1, 'yes', 'no') AS article_read, "
		. "ciniki_newsaggregator_feeds.title AS feed_title "
		. "FROM ciniki_newsaggregator_subscriptions "
		. "LEFT JOIN ciniki_newsaggregator_feeds ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_feeds.id ) "
		. "LEFT JOIN ciniki_newsaggregator_articles ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_articles.feed_id ";
	if( !isset($args['read']) || $args['read'] != 'yes' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.date_read_all < ciniki_newsaggregator_articles.published_date ";
	}
	$strsql .= ") "
		. "LEFT JOIN ciniki_newsaggregator_article_users ON (ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
			. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
		. "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' ";
	if( $args['business_id'] != '' && $args['business_id'] != '0' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	}
	$strsql .= "AND ciniki_newsaggregator_subscriptions.feed_id = '" . ciniki_core_dbQuote($ciniki, $args['feed_id']) . "' ";
	$strsql .= "AND NOT EXISTS (SELECT article_id FROM ciniki_newsaggregator_article_users "
		. "WHERE ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
		. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
		. "AND (ciniki_newsaggregator_article_users.flags&0x01) = 1 "
		. ") ";
	$strsql .= "ORDER BY ciniki_newsaggregator_articles.published_date DESC ";

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
		array('container'=>'articles', 'fname'=>'article_id', 'name'=>'article',
			'fields'=>array('id'=>'article_id', 'title', 'published_date', 'read'=>'article_read', 'feed_title')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['articles']) ) {
		$unread = array();
	} else {
		$unread = $rc['articles'];
	}

	//
	// Get the read articles
	//
	$strsql = "SELECT ciniki_newsaggregator_articles.id AS article_id, "
		. "ciniki_newsaggregator_articles.title, "
		. "DATE_FORMAT(published_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS published_date, "
		. "IF((ciniki_newsaggregator_article_users.flags&0x01)=1, 'yes', 'no') AS article_read, "
		. "ciniki_newsaggregator_feeds.title AS feed_title "
		. "FROM ciniki_newsaggregator_subscriptions "
		. "LEFT JOIN ciniki_newsaggregator_feeds ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_feeds.id ) "
		. "LEFT JOIN ciniki_newsaggregator_articles ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_articles.feed_id ";
	if( !isset($args['read']) || $args['read'] != 'yes' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.date_read_all < ciniki_newsaggregator_articles.published_date ";
	}
	$strsql .= ") "
		. "LEFT JOIN ciniki_newsaggregator_article_users ON (ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
			. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
		. "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' ";
	if( $args['business_id'] != '' && $args['business_id'] != '0' ) {
		$strsql .= "AND ciniki_newsaggregator_subscriptions.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	}
	$strsql .= "AND ciniki_newsaggregator_subscriptions.feed_id = '" . ciniki_core_dbQuote($ciniki, $args['feed_id']) . "' ";
	$strsql .= "AND (EXISTS (SELECT article_id FROM ciniki_newsaggregator_article_users "
			. "WHERE ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
			. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
			. "AND (ciniki_newsaggregator_article_users.flags&0x01) = 1 "
			. ") "
		. "OR ciniki_newsaggregator_subscriptions.date_read_all > ciniki_newsaggregator_articles.published_date "
		. ")";
	$strsql .= "ORDER BY ciniki_newsaggregator_articles.published_date DESC ";

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
		array('container'=>'articles', 'fname'=>'article_id', 'name'=>'article',
			'fields'=>array('id'=>'article_id', 'title', 'published_date', 'read'=>'article_read', 'feed_title')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['articles']) ) {
		$read = array();
	} else {
		$read = $rc['articles'];
	}

	return array('stat'=>'ok', 'feed'=>$feed, 'unread'=>$unread, 'read'=>$read);
}
?>
