<?php // $Id: access.php,v 1.1 2012-10-29 22:29:55 vf Exp $

$capabilities = array(

    'enrol/sync:configure' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'manager' => CAP_ALLOW
        )
    ),

);

?>
