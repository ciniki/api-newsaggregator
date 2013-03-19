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
function ciniki_newsaggregator_articleGet(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'name'=>'Business'), 
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
    $rc = ciniki_newsaggregator_checkAccess($ciniki, $args['business_id'], 'ciniki.newsaggregator.articleGet'); 
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
		. "DATE_FORMAT(published_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS published_date, "
		. "IF((ciniki_newsaggregator_article_users.flags&0x01)=1, 'yes', 'no') AS article_read, "
		. "ciniki_newsaggregator_articles.content "
		. "FROM ciniki_newsaggregator_articles "
		. "LEFT JOIN ciniki_newsaggregator_article_users ON (ciniki_newsaggregator_articles.id = ciniki_newsaggregator_article_users.article_id "
			. "AND ciniki_newsaggregator_article_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
		. "WHERE ciniki_newsaggregator_articles.id = '" . ciniki_core_dbQuote($ciniki, $args['article_id']) . "' "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.newsaggregator', 'article');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['article']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'916', 'msg'=>"Unable to find the article requested"));
	}

	$article = $rc['article'];
	$article['read'] = $article['article_read'];
	unset($article['article_read']);

	//
	// Update the read time
	//
	


	return array('stat'=>'ok', 'article'=>$article);
}
?>
