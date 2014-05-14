<?php

if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
    exit();

delete_option('dbsideload');
delete_metadata('user', 0, 'dbsideload_tokens', '', true);
