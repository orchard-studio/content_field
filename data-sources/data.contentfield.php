<?php	
		require_once(TOOLKIT . '/class.datasource.php');	
	require_once(TOOLKIT.'/class.json.php');

	ini_set('xdebug.var_display_max_depth', 500);
	ini_set('xdebug.var_display_max_children', 2048);
	ini_set('xdebug.var_display_max_data', 28186);
	Final Class datasourceContentfield Extends DataSource{			

		function about(){
			return array(
					 'name' => 'Session Monster: Show Session Parameters',
					 'author' => array(
							'name' => 'Symphony Team',
							'website' => 'http://symphony21.com',
							'email' => 'team@symphony21.com'),
					 'version' => '1.0',
					 'release-date' => '2008-05-12');	
		}

		
		public function grab(){
			
			return null;
		}
	}
