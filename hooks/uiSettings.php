<?php
//
// Description
// -----------
// This function will return a list of user interface settings for the module.
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get newsaggregator for.
//
// Returns
// -------
//
function ciniki_newsaggregator_hooks_uiSettings($ciniki, $tnid, $args) {

    //
    // Setup the default response
    //
    $rsp = array('stat'=>'ok', 'menu_items'=>array());

    //
    // Check permissions for what menu items should be available
    //
    if( isset($ciniki['tenant']['modules']['ciniki.newsaggregator'])
        && (isset($args['permissions']['owners'])
            || isset($args['permissions']['employees'])
            || isset($args['permissions']['resellers'])
            || ($ciniki['session']['user']['perms']&0x01) == 0x01
            )
        ) {
        $menu_item = array(
            'priority'=>900,
            'label'=>'News', 
            'edit'=>array('app'=>'ciniki.newsaggregator.main'),
            );
        $rsp['menu_items'][] = $menu_item;
    } 

    return $rsp;
}
?>
