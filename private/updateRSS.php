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
function ciniki_newsaggregator_updateRSS(&$ciniki, $feed_id) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

    //
    // Get the feed information
    //
    $strsql = "SELECT title, feed_url, site_url, description, last_checked "
        . "FROM ciniki_newsaggregator_feeds "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $feed_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'feed');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['feed']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.3', 'msg'=>'Unable to find feed'));
    }
    $feed = $rc['feed'];

    error_log("Updating feed: " . $feed['feed_url']);

    //
    // Get the latest XML file
    //
    $rss = simplexml_load_file($feed['feed_url']);
    if( $rss === false ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.4', 'msg'=>'Invalid RSS feed'));
    }

    $args = array();
    $args['title'] = '';
    if( isset($rss->channel->title) ) {
        $args['title'] = $rss->channel->title;
    } elseif( isset($rss->title) ) {
        $args['title'] = $rss->title;
    }
    $args['site_url'] = '';
    if( isset($rss->channel->link) ) {
        $args['site_url'] = $rss->channel->link;
    } elseif( isset($rss->link) ) {
        foreach($rss->link as $link) {
            if( isset($link['rel']) && $link['rel'] == 'alternate' 
                && isset($link['href']) && $link['href'] != '' ) {
                $args['site_url'] = $link['href'];
            }
        }
    }
    $args['description'] = '';
    if( isset($rss->channel->description) ) {
        $args['description'] = $rss->channel->description;
    } elseif( isset($rss->description) ) {
        $args['description'] = $rss->description;
    }


    $strsql = '';
    if( $args['title'] != '' && $args['title'] != $feed['title'] ) {
        $strsql .= "title = '" . ciniki_core_dbQuote($ciniki, $args['title']) . "', ";
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            2, 'ciniki_newsaggregator_feeds', $feed_id, 'title', $args['title']);
    }
    if( $args['site_url'] != '' && $args['site_url'] != $feed['site_url'] ) {
        $strsql .= "site_url = '" . ciniki_core_dbQuote($ciniki, $args['site_url']) . "', ";
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            2, 'ciniki_newsaggregator_feeds', $feed_id, 'site_url', $args['site_url']);
    }
    if( $args['description'] != '' && $args['description'] != $feed['description'] ) {
        $strsql .= "description = '" . ciniki_core_dbQuote($ciniki, $args['description']) . "', ";
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            2, 'ciniki_newsaggregator_feeds', $feed_id, 'description', $args['description']);
    }
    if( $strsql != '' ) {
        $strsql = "UPDATE ciniki_newsaggregator_feeds SET "
            . $strsql
            . "last_updated = UTC_TIMESTAMP(), last_checked = UTC_TIMESTAMP() "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $feed_id) . "' "
            . "";
        $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.newsaggregator');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        // Add to syncqueue
        $ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.feed', 
            'args'=>array('id'=>$feed_id));
    }
    
    //
    // Update feed articles
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'updateRSSArticles');
    $rc = ciniki_newsaggregator_updateRSSArticles($ciniki, $feed_id, $rss);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
