<?php
 return array(
 	'_root_'  => 'welcome/index',  // The default route
 	'_404_'   => 'welcome/404',    // The main 404 route

 	'hello(/:name)?' => array('welcome/hello', 'name' => 'hello'),
 );

return array(
		'_root_'  => 'index',  // The default route
		'_404_'   => '404',    // The main 404 route
		//'bbs/(:num)'=> 'bbs/index/$1', //ページネーションのURL変更
);
