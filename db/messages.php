<?php


defined('MOODLE_INTERNAL') || die();

global $CFG;

$messageproviders = array(
    'content_rolledover_wizard' => array(
        'defaults' => array(
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_FORCED,
        ),
    )
);

