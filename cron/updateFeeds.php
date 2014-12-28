<?php
//
// Description
// ===========
//
// Arguments
// =========
// 
// Returns
// =======
// <rsp stat="ok" />
//
function ciniki_newsaggregator_cron_updateFeeds(&$ciniki) {

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'updateRSS');

	//
	// Get the feed information
	//
	$strsql = "SELECT id, last_checked "
		. "FROM ciniki_newsaggregator_feeds "
		. "WHERE (UTC_TIMESTAMP()-last_checked) > update_frequency "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'feed');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['rows']) ) {
		return array('stat'=>'ok');
	}
	$feeds = $rc['rows'];

	foreach($feeds as $feed) {
		//
		// Update feed articles
		//
		$rc = ciniki_newsaggregator_updateRSS($ciniki, $feed['id']);

		//
		// Update just the last checked, and don't worry about history or sync
		//
		$strsql = "UPDATE ciniki_newsaggregator_feeds SET last_checked = UTC_TIMESTAMP() "
			. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $feed['id']) . "' "
			. "";
		$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.newsaggregator');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
	}

	return array('stat'=>'ok');
}
?>
