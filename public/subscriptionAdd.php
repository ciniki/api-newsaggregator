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
// tnid:         The ID of the tenant to add the subscription to.
// feed_id:             The ID of an existing feed to subscribe to.
// feed_url:            The URL of the feed.
// category:            The category for the subscription.
// flags:               The flags for the subscription. **future**
//
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_newsaggregator_subscriptionAdd(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'feed_id'=>array('required'=>'no', 'name'=>'Feed'), 
        'feed_url'=>array('required'=>'no', 'name'=>'URL'), 
        'category'=>array('required'=>'no', 'default'=>'', 'trimblanks'=>'yes', 'blank'=>'yes', 'name'=>'Category'),
        'flags'=>array('required'=>'no', 'default'=>'0', 'name'=>'Flags'),
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    // 
    // Check that feed_id or feed_url has been supplied
    //
    if( (!isset($args['feed_id']) && !isset($args['feed_url']))
        || (!isset($args['feed_id']) && isset($args['feed_url']) && $args['feed_url'] == '')
        || (!isset($args['feed_url']) && isset($args['feed_id']) && $args['feed_id'] == '')
        ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.6', 'msg'=>'You must specify either a feed'));
    }

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['tnid'], 'ciniki.newsaggregator.subscriptionAdd'); 
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
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Find the feed ID
    //
    if( isset($args['feed_url']) && $args['feed_url'] != '' ) {
        $strsql = "SELECT id FROM ciniki_newsaggregator_feeds "
            . "WHERE feed_url = '" . ciniki_core_dbQuote($ciniki, $args['feed_url']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'feed');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
            return $rc;
        }
        if( !isset($rc['feed']) ) {
            //
            // Verify the feed is valid URL
            //
            $rss = simplexml_load_file($args['feed_url']);
            if( $rss === false ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.7', 'msg'=>'Invalid RSS feed'));
            }

            $args['title'] = '';
            if( isset($rss->channel->title) ) {
                $args['title'] = $rss->channel->title;
            }
            $args['site_url'] = '';
            if( isset($rss->channel->link) ) {
                $args['site_url'] = $rss->channel->link;
            }
            $args['description'] = '';
            if( isset($rss->channel->description) ) {
                $args['description'] = $rss->channel->description;
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
            $args['uuid'] = $rc['uuid'];

            //
            // Add the feed
            //
            $strsql = "INSERT INTO ciniki_newsaggregator_feeds (uuid, feed_url, "
                . "title, site_url, description, update_frequency, last_checked, date_added, last_updated) VALUES ("
                . "'" . ciniki_core_dbQuote($ciniki, $args['uuid']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $args['feed_url']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $args['title']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $args['site_url']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $args['description']) . "', "
                . "'3600', "
                . "UTC_TIMESTAMP(), "
                . "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
            $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.newsaggregator');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
                return $rc;
            }
            $args['feed_id'] = $rc['insert_id'];

            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
                1, 'ciniki_newsaggregator_feeds', $args['feed_id'], 'uuid', $args['uuid']);
            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
                1, 'ciniki_newsaggregator_feeds', $args['feed_id'], 'feed_url', $args['feed_url']);
            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
                1, 'ciniki_newsaggregator_feeds', $args['feed_id'], 'title', $args['title']);
            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
                1, 'ciniki_newsaggregator_feeds', $args['feed_id'], 'site_url', $args['site_url']);
            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
                1, 'ciniki_newsaggregator_feeds', $args['feed_id'], 'description', $args['description']);
            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
                1, 'ciniki_newsaggregator_feeds', $args['feed_id'], 'update_frequency', '3600');
            // Add to syncqueue
            $ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.feed', 
                'args'=>array('id'=>$args['feed_id']));
            
            //
            // Update feed articles
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'updateRSSArticles');
            $rc = ciniki_newsaggregator_updateRSSArticles($ciniki, $args['feed_id'], $rss);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
        else {
            $args['feed_id'] = $rc['feed']['id'];
        }
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
    $args['uuid'] = $rc['uuid'];

    //
    // Add the subscription to the database
    //
    $strsql = "INSERT INTO ciniki_newsaggregator_subscriptions (uuid, tnid, "
        . "user_id, feed_id, "
        . "category, flags, "
        . "date_added, last_updated) VALUES ("
        . "'" . ciniki_core_dbQuote($ciniki, $args['uuid']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['feed_id']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['category']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['flags']) . "', "
        . "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
    $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) { 
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
        return $rc;
    }
    if( !isset($rc['insert_id']) || $rc['insert_id'] < 1 ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.8', 'msg'=>'Unable to add subscription'));
    }
    $subscription_id = $rc['insert_id'];

    //
    // Add all the fields to the change log
    //
    $changelog_fields = array(
        'uuid',
        'user_id',
        'feed_id',
        'category',
        'flags',
        );
    foreach($changelog_fields as $field) {
        if( isset($args[$field]) && $args[$field] != '' ) {
            $rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 
                'ciniki_newsaggregator_history', $args['tnid'], 
                1, 'ciniki_newsaggregator_subscriptions', $subscription_id, $field, $args[$field]);
        }
    }

    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'newsaggregator');

    $ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.subscription', 
        'args'=>array('id'=>$subscription_id));

    return array('stat'=>'ok', 'id'=>$subscription_id);
}
?>
