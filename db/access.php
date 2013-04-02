<?php // $Id: access.php,v 1.1 2011-07-27 20:44:43 vf Exp $

$enrol_sync_capabilities = array(

    'enrol/sync:configure' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

);

?>
