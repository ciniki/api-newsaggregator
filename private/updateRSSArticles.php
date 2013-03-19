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
function ciniki_newsaggregator_updateRSSArticles($ciniki, $feed_id, $rss) {

	//
	// Get the existing articles for the RSS feed
	//
	$num_articles = count($rss->channel->item);
	if( $num_articles < 1 ) {
		$num_articles = 25;
	}
	$strsql = "SELECT guid, UNIX_TIMESTAMP(last_updated) "
		. "FROM ciniki_newsaggregator_articles "
		. "WHERE feed_id = '" . ciniki_core_dbQuote($ciniki, $feed_id) . "' "
		. "ORDER BY published_date DESC "
		. "LIMIT " . ciniki_core_dbQuote($ciniki, $num_articles) . " "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
	$rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
		array('container'=>'articles', 'fname'=>'guid', 
			'fields'=>array('last_updated')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( isset($rc['articles']) ) {
		$articles = $rc['articles'];	
	} else {
		$articles = array();
	}

	foreach($rss->channel->item as $item) {
		if( isset($item->guid) ) {
			$guid = $item->guid;
		} elseif( isset($item->id) ) {
			$guid = $item->id;
		} else {
			continue;
		}

		if( isset($articles[$guid]) ) {
			continue;
		}

		//
		// Check to make sure this is a valid item with a title
		//
		if( !isset($item->title) ) {
			continue;
		}
		$title = $item->title;

		//
		// Load the required namespace to parse dc:date field
		//
		$namespaces = $item->getNameSpaces(true);
		if( isset($namespaces['dc']) ) {
			$dc = $item->children($namespaces['dc']);
		}

		//
		// Get the article details
		//
		$published_date = '';
		$url = '';
		$content = '';
		if( isset($dc) && isset($dc->date) ) {
			$published_date = strtotime($dc->pubDate);
		} elseif( isset($item->pubDate) ) {
			$published_date = strtotime($item->pubDate);
		} elseif( isset($item->published) ) {
			$published_date = strtotime($item->published);
		}
		if( isset($item->link) ) {
			$url = $item->link;
		}

		if( isset($item->content) ) {
			$content = $item->content;
		} elseif( isset($item->summary) ) {
			$content = $item->summary;
		} elseif( isset($item->description) ) {
			$content = $item->description;
		}

		//
		// Get a new UUID
		//
		ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
		$rc = ciniki_core_dbUUID($ciniki, 'ciniki.newsaggregator');
		if( $rc['stat'] != 'ok' ) {
			ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
			return $rc;
		}
		$uuid = $rc['uuid'];

		//
		// Add the article to the database
		//
		$strsql = "INSERT INTO ciniki_newsaggregator_articles (uuid, feed_id, "
			. "title, url, content, published_date, guid, "
			. "date_added, last_updated) VALUES("
			. "'" . ciniki_core_dbQuote($ciniki, $uuid) . "', "
			. "'" . ciniki_core_dbQuote($ciniki, $feed_id) . "', "
			. "'" . ciniki_core_dbQuote($ciniki, $title) . "', "
			. "'" . ciniki_core_dbQuote($ciniki, $url) . "', "
			. "'" . ciniki_core_dbQuote($ciniki, $content) . "', "
			. "FROM_UNIXTIME('" . ciniki_core_dbQuote($ciniki, $published_date) . "'), "
			. "'" . ciniki_core_dbQuote($ciniki, $guid) . "', "
			. "UTC_TIMESTAMP(), UTC_TIMESTAMP())"
			. "";
		error_log($strsql);
		$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.newsaggregator');
		// Ignore the return of 'exists', only error on fail
		if( $rc['stat'] == 'fail' ) {
			return $rc;
		}
		if( $rc['stat'] == 'ok' ) {
			$article_id = $rc['insert_id'];
			//
			// Only store the uuid in the history, the articles
			// will never be recovered if lost or not sync'd.  
			// Store the uuid so it can be tracked properly if deleted.
			//
			$rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 
				'ciniki_newsaggregator_history', 0, 
				1, 'ciniki_newsaggregator_articles', $article_id, 'uuid', $uuid);
			// Add to syncqueue
			$ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.article', 
				'args'=>array('id'=>$article_id));
		}
	}

	return array('stat'=>'ok');
}
?>
