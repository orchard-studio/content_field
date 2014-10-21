<?php

	require_once(EXTENSIONS . '/content_field/libs/require.php');
	class contentExtensionContent_FieldUpload extends JSONPage {
		public function view() {
			//$result = array();
			$post = $_REQUEST;
			//$post = $_REQUEST;
			
			//$result = $_FILES;
			$type = explode('/',$post['type']);
			$datauri = $post['dataurl'];
			//$dataimgurl = $this->imageCreateFromAny($datauri,$type[1]);
			$path = $post['imageurl'];
			$name = $post['name'];
			$fp = fopen(ltrim($path,'/'),'w+');
			$imagecontents = file_get_contents($datauri);
			$check = fwrite($fp,$imagecontents);
			//if (headers_sent()) exit;
			if(file_exists(ltrim($path,'/'))){
				//$p = array(,$name,$check,$type);
				//rename($location,$newlocation);
				$this->_Result['post'] = URL.$path;
				//rename($name,$newlocation);
			}else{
				//rename($location,$newlocation);
				$this->_Result['post'] = false;
				//$this->_Result['post'] = false;
			}
			
			//header_remove();
			
			
			//header('Cache-Control: no-cache, must-revalidate');
			//header("Content-Type: application/json; charset=UTF-8");
			//header($_SERVER['SERVER_PROTOCOL'].$status, true);
			
			//echo json_encode($post);
			//exit;
		}
		public function imageCreateFromAny($filepath,$type) { 
			//$type = exif_imagetype($filepath); // [] if you don't have exif you could use getImageSize() 
			$allowedTypes = array( 
				'gif',
				'jpeg',				
				'png',
				'bmp'
			); 
			if (!in_array($type, $allowedTypes)) { 
				return false; 
			} 
			switch ($type) { 
				case 'gif' : 
					$im = imageCreateFromGif($filepath); 
				break; 
				case 'jpeg' : 
					$im = imageCreateFromJpeg($filepath); 
				break; 
				case 'png' : 
					$im = imageCreateFromPng($filepath); 
				break; 
				case 'bmp' : 
					$im = imageCreateFromBmp($filepath); 
				break; 
			}    
			return $im;  
		} 
	}