<?php
//
// Description
// -----------
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant to add the exhibition to.
//
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_newsaggregator_articleGet(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'name'=>'Tenant'), 
        'article_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Article'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure the user has permission to this method
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['tnid'], 'ciniki.newsaggregator.articleGet'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki);

    //
    // Get the list of categories for the requesting user
    //
    $strsql = "SELECT ciniki_newsaggregator_articles.id, "
        . "ciniki_newsaggregator_articles.title, "
        . "ciniki_newsaggregator_articles.url, "
        . "DATE_FORMAT(published_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS published_date, "
        . "IF((ciniki_newsaggregator_article_users.flags&0x01)=1, 'yes', 'no') AS article_read, "
        . "ciniki_newsaggregator_articles.content, "
        . "ciniki_newsaggregator_feeds.title AS feed_title "
        . "FROM ciniki_newsaggregator_articles "
        . "LEFT JOIN ciniki_newsaggregator_article_users ON (ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
            . "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
        . "LEFT JOIN ciniki_newsaggregator_feeds ON (ciniki_newsaggregator_articles.feed_id = ciniki_newsaggregator_feeds.id) "
        . "WHERE ciniki_newsaggregator_articles.id = '" . ciniki_core_dbQuote($ciniki, $args['article_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'article');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['article']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.newsaggregator.5', 'msg'=>"Unable to find the article requested"));
    }

    $article = $rc['article'];
    $article['read'] = $article['article_read'];
    unset($article['article_read']);

    //  
    // Turn off autocommit
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Update the read time
    //
    $strsql = "SELECT id, flags "
        . "FROM ciniki_newsaggregator_article_users "
        . "WHERE article_id = '" . ciniki_core_dbQuote($ciniki, $args['article_id']) . "' "
        . "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'user');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
        return $rc;
    }
    if( !isset($rc['user']) ) {
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
        $strsql = "INSERT INTO ciniki_newsaggregator_article_users (uuid, article_id, "
            . "user_id, flags, date_added, last_updated) VALUES ("
            . "'" . ciniki_core_dbQuote($ciniki, $args['uuid']) . "', "
            . "'" . ciniki_core_dbQuote($ciniki, $args['article_id']) . "', "
            . "'" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "', "
            . "'1', "
            . "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
        $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.newsaggregator');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
            return $rc;
        }
        $args['article_user_id'] = $rc['insert_id'];

        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            1, 'ciniki_newsaggregator_article_users', $args['article_user_id'], 'uuid', $args['uuid']);
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            1, 'ciniki_newsaggregator_article_users', $args['article_user_id'], 'article_id', $args['article_id']);
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            1, 'ciniki_newsaggregator_article_users', $args['article_user_id'], 'user_id', $ciniki['session']['user']['id']);
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            1, 'ciniki_newsaggregator_article_users', $args['article_user_id'], 'flags', 1);
        // Add to syncqueue
        $ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.article_user', 
            'args'=>array('id'=>$args['article_user_id']));
    } else {
        $article_user_id = $rc['user']['id'];
        $flags = $rc['user']['flags'] | 0x01;
        $strsql = "UPDATE ciniki_newsaggregator_article_users SET "
            . "flags = '" . ciniki_core_dbQuote($ciniki, $flags) . "', "
            . "last_updated = UTC_TIMESTAMP() "
            . "WHERE article_id = '" . ciniki_core_dbQuote($ciniki, $args['article_id']) . "' "
            . "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
        $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.newsaggregator');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.newsaggregator');
            return $rc;
        }
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.newsaggregator', 'ciniki_newsaggregator_history', 0, 
            2, 'ciniki_newsaggregator_article_users', $article_user_id, 'flags', $flags);
        // Add to syncqueue
        $ciniki['syncqueue'][] = array('push'=>'ciniki.newsaggregator.article_user', 
            'args'=>array('id'=>$article_user_id));
    }
        
    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.newsaggregator');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok', 'article'=>$article);
}
?>
