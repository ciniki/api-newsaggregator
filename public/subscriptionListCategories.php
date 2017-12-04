<?php
//
// Description
// -----------
// This method get the list of unread messages for the users subscription categories.
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
function ciniki_newsaggregator_subscriptionListCategories(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'name'=>'Tenant'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure the user has permission to this method
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'private', 'checkAccess');
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['tnid'], 'ciniki.newsaggregator.subscriptionListCategories'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Get the list of categories
    //
    $strsql = "SELECT DISTINCT "
        . "IF(ciniki_newsaggregator_subscriptions.category='', 'Uncategorized', ciniki_newsaggregator_subscriptions.category) AS category, "
        . "'0' AS unread_count "
        . "FROM ciniki_newsaggregator_subscriptions "
        . "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' ";
    if( $args['tnid'] != '' && $args['tnid'] != '0' ) {
        $strsql .= "AND ciniki_newsaggregator_subscriptions.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' ";
    }
    $strsql .= "ORDER BY ciniki_newsaggregator_subscriptions.category "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
    $rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
        array('container'=>'categories', 'fname'=>'category', 'name'=>'category',
            'fields'=>array('name'=>'category', 'unread_count')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    // No feeds setup yet
    if( !isset($rc['categories']) ) {
        return array('stat'=>'ok', 'unread_count'=>'0', 'feeds'=>array());
    }
    $categories = $rc['categories'];
    if( count($categories) < 2 
        || (isset($categories[0]['category']['name']) && $categories[0]['category']['name'] == 'Uncategorized') ) {
        //
        // If no categories are found, return output of subscriptionList instead
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'public', 'subscriptionList');
        return ciniki_newsaggregator_subscriptionList($ciniki);
    }

    //
    // Get the list of unread counts for the requesting user
    //
    $strsql = "SELECT " //'unread' AS unread, "
        . "IF(ciniki_newsaggregator_subscriptions.category='', 'Uncategorized', ciniki_newsaggregator_subscriptions.category) AS category, "
        . "COUNT(ciniki_newsaggregator_articles.id) AS unread_count "
        . "FROM ciniki_newsaggregator_subscriptions "
        . "LEFT JOIN ciniki_newsaggregator_articles ON (ciniki_newsaggregator_subscriptions.feed_id = ciniki_newsaggregator_articles.feed_id "
            . "AND ciniki_newsaggregator_subscriptions.date_read_all < ciniki_newsaggregator_articles.published_date ) "
        . "WHERE ciniki_newsaggregator_subscriptions.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "";
    if( $args['tnid'] != '' && $args['tnid'] != '0' ) {
        $strsql .= "AND ciniki_newsaggregator_subscriptions.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' ";
    }
    $strsql .= "AND NOT EXISTS (SELECT article_id FROM ciniki_newsaggregator_article_users "
        . "WHERE ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
        . "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "AND (ciniki_newsaggregator_article_users.flags&0x01) = 1 "
        . ") ";
    $strsql .= "GROUP BY ciniki_newsaggregator_subscriptions.category "
        . "ORDER BY ciniki_newsaggregator_subscriptions.category "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.newsaggregator', array(
//      array('container'=>'unread', 'fname'=>'unread', 'name'=>'all',
//          'fields'=>array('unread_count'), 'sums'=>array('unread_count')),
        array('container'=>'categories', 'fname'=>'category', 
            'fields'=>array('name'=>'category', 'unread_count')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    // Nothing found, return categories list
    if( !isset($rc['categories']) ) {
        return array('stat'=>'ok', 'unread_count'=>'0', 'categories'=>$categories);
    }
    $unread = $rc['categories'];
//  $unread = $rc['unread'][0]['all'];
    
    //
    // Merge unread_count into categories
    //
    $unread_total = 0;
    foreach($categories as $cid => $category) {
        $category = $category['category'];
        if( isset($unread[$category['name']]) ) {
            $categories[$cid]['category']['unread_count'] = $unread[$category['name']]['unread_count'];
            $unread_total += $unread[$category['name']]['unread_count'];
        }
    }

    return array('stat'=>'ok', 'unread_count'=>$unread_total, 'categories'=>$categories);

//  if( isset($unread['categories']) && (count($unread['categories']) > 1 || $unread['categories'][0]['category']['name'] == 'Uncategorized') ) {
//      // If there is categories, it must be more than one category,
//      // or the single category must not be blank (uncategorized).  
//      // Otherwise a list of feeds for the blank category should be returned
//      return array('stat'=>'ok', 'unread_count'=>$unread['unread_count'], 'categories'=>$unread['categories']);
//  }
    

    //
    // If no categories are found, return output of subscriptionList instead
    //
//  ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'public', 'subscriptionList');
//  return ciniki_newsaggregator_subscriptionList($ciniki);
}
?>
