<?php
	
	/**
	 * @package image_upload_content
	 */
	 class ImageUploadContentType  implements ContentType{
		public function getName() {
			return __('Image Upload');
		}

		public function appendSettingsHeaders(HTMLPage $page) {
			
		}
		
		public function appendSettingsInterface(XMLElement $wrapper, $field_name, StdClass $settings = null, MessageStack $errors) {
			// Default size
			$ignore = array(
				'/workspace/jit-image-manipulation',
				'/workspace/events',
				'/workspace/data-sources',
				'/workspace/text-formatters',
				'/workspace/pages',
				'/workspace/utilities'
			  );
			  $directories = General::listDirStructure(WORKSPACE, null, true, DOCROOT, $ignore);

			  $label = Widget::Label(__('Destination Directory'));

			  $options = array();
			  $options[] = array('/workspace', false, '/workspace');
			  if(!empty($directories) && is_array($directories)){
				foreach($directories as $d) {
				  $d = '/' . trim($d, '/');
				  if(!in_array($d, $ignore)) $options[] = array($d, ($settings->{'destination'} == $d), $d);
				}
			  }

			  $label->appendChild(Widget::Select("{$field_name}[destination]", $options));

			  $wrapper->appendChild($label);
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$values = array(
				array('auto', false, __('Automatic')),
				array('small', false, __('Small Box')),
				array('medium', false, __('Medium Box')),
				array('large', false, __('Large Box')),
				array('huge', false, __('Huge Box'))
			);

			foreach ($values as &$value) {
				$value[1] = $value[0] == $settings->{'text-size'};
			}

			$label = Widget::Label(__('Default Size'));
			$label->appendChild(Widget::Select(
				"{$field_name}[text-size]", $values
			));

			$group->appendChild($label);

			// Text formatter:
			$field = new Field();
			$group->appendChild($field->buildFormatterSelect(
				isset($settings->{'text-formatter'})
					? $settings->{'text-formatter'}
					: null,
				"{$field_name}[text-formatter]",
				'Text Formatter'
			));
			$wrapper->appendChild($group);

			// Styles:
			$label = Widget::Label(__('Available Styles'));
			$input = Widget::Input(
				"{$field_name}[available-styles]",
				$settings->{'available-styles'}
			);
			$label->appendChild($input);
			$wrapper->appendChild($label);

			$list = new XMLElement('ul');
			$list->addClass('tags');

			foreach (explode(',', $settings->{'available-styles'}) as $tag) {
				$tag = trim($tag);

				if ($tag == '') continue;

				$list->appendChild(new XMLElement('li', $tag));
			}

			$wrapper->appendChild($list);
		}

		public function sanitizeSettings($settings) {
			if (is_array($settings)) {
				$settings = (object)$settings;
			}

			else if (is_object($settings) === false) {
				$settings = new StdClass();
			}

			if (isset($settings->{'enabled'}) === false) {
				$settings->{'enabled'} = 'no';
			}

			if (isset($settings->{'text-size'}) === false) {
				$settings->{'text-size'} = 'auto';
			}

			if (isset($settings->{'text-formatter'}) === false) {
				$settings->{'text-formatter'} = 'none';
			}

			if (isset($settings->{'available-styles'}) === false) {
				$settings->{'available-styles'} = __('Thumbnail') . ',' . __('Normal');
			}

			return $settings;
		}

		public function validateSettings(StdClass $data, MessageStack $errors) {
			if ( !is_writable(DOCROOT . $settings->{'destination'} . '/') ) {
				$errors->{'destination'} = __('The destination folder is not writeable. Please change permissions or choose another folder.');

				return false;
			  }

			  return true;
				}

		public function appendPublishHeaders(HTMLPage $page) {
			//$url = URL . '/extensions/image_upload_content/assets';
			//$page->addStylesheetToHead($url . '/publish.css', 'screen');
			
			//$page->addScriptToHead($url . '/publish.js');
			
		}

		public function appendPublishInterface(XMLElement $wrapper, $field_name, StdClass $settings, StdClass $data, MessageStack $errors, $entry_id = null) {
			
			//var_dump($data);
			//var_dump($entry_id);
			$wrapper->addClass('group');
			
			// Add drop target:
			$div = new XMLElement('div');
			$div->addClass('drop-target');
				if($data->{'url'} != ''){
					//$div->setAttribute('style','display:none');
				}
			
			$span = new XMLElement('span');
			$text = new XMLElement('span');
			$text->setValue(__('Drop your image here'));
			$span->appendChild($text);
			$div->appendChild($span);
			$wrapper->appendChild($div);

			// Image preview:
			$div = new XMLElement('div');
			$div->addClass('image-preview');
			$img = new XMLElement('img');
			if($data->{'url'} != ''){
				$img->setAttribute('src',$data->url);
				$div->setAttribute('style','display:block');
			}
			$div->appendChild($img);
			
			$wrapper->appendChild($div);
			
			$group = new XMLElement('div');
			//$anchor = Widget::Anchor('Remove File','#','Remove File','remove-file');
			if($data->{'url'} != ''){
				//$group->appendChild($anchor);
			}
			$group->setAttribute('class', 'image-fields');
			$wrapper->appendChild($group);

			// Image style:
			$values = array();

			foreach (explode(',', $settings->{'available-styles'}) as $style) {
				$style = trim($style);

				if ($style == '') continue;

				$values[] = array(
					$style, $style == $data->{'style'}, $style
				);
				// checks if option is selected
			}
		
			$label = Widget::Label('Image style');
			$label->appendChild(Widget::Select(
				"{$field_name}[data][style]", $values
			));
			$group->appendChild($label);
			
			
			/*$label = Widget::Label(__('Image URL'));
			$input = Widget::Input(
				"{$field_name}[data][url]", (
					isset($data->url)
						? $data->url
						: null
				)
			);
			$input->setAttribute('placeholder', 'http://.../image.png');
			$label->appendChild($input);

			if (isset($errors->url)) {
				$label = Widget::wrapFormElementWithError($label, $errors->url);
			}

			$wrapper->appendChild($label);
			*/
			// Alt text:
			$url = $data->{'url'};
			$label = Widget::Label(__('Alternative text'));
			$label->appendChild(new XMLElement('i', __('Optional')));
			
			
			
			$input = Widget::Input("{$field_name}[data][url]","{$url}",'hidden',array('class'=>'image-data','id'=>'image-url','data-entry-id'=>$entry_id,'data-image'=>$settings->{'destination'}));
			
			$text = Widget::Textarea("{$field_name}[data][alt-text]", 1, 20, (isset($data->{'alt-text'})? $data->{'alt-text'}: null));
			$text->addClass('size-auto');						
			
			$labeltwo = Widget::Label(__('URL Link'));
			$labeltwo->appendChild(new XMLElement('i', __('Optional')));
			
			$link = Widget::Textarea("{$field_name}[data][link]", 1, 20, (isset($data->{'link'})	? $data->{'link'}		: null	));
			
			$labeltwo->appendChild($link);
			
			if ($settings->{'text-formatter'} != 'none') {
				$text->addClass($settings->{'text-formatter'});
			}
			
			$label->appendChild($input);
			$label->appendChild($text);
			$group->appendChild($label);
			$group->appendChild($labeltwo);
			
			return;

			// URL:
			/*$label = Widget::Label(__('Image URL'));
			$input = Widget::Input(
				"{$field_name}[data][url]", (
					isset($data->url)
						? $data->url
						: null
				)
			);
			$input->setAttribute('placeholder', 'http://.../image.png');
			$label->appendChild($input);

			if (isset($errors->url)) {
				$label = Widget::wrapFormElementWithError($label, $errors->url);
			}

			$wrapper->appendChild($label);

			// Alt text:
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$wrapper->appendChild($group);

			$label = Widget::Label(__('Alternative text'));
			$label->appendChild(new XMLElement('i', __('Optional')));
			$group->appendChild($label);

			$text = Widget::Textarea(
				"{$field_name}[data][alt-text]", 1, 20, (
					isset($data->{'alt-text'})
						? $data->{'alt-text'}
						: null
				)
			);
			$text->addClass('size-auto');

			if ($settings->{'text-formatter'} != 'none') {
				$text->addClass($settings->{'text-formatter'});
			}

			$label->appendChild($text);

			// Image style:
			$values = array();

			foreach (explode(',', $settings->{'available-styles'}) as $style) {
				$style = trim($style);

				if ($style == '') continue;

				$values[] = array(
					$style, $style == $data->{'style'}, $style
				);
			}

			$label = Widget::Label('Image style');
			$label->appendChild(Widget::Select(
				"{$field_name}[data][style]", $values
			));
			
			$group->appendChild($label);*/
		}
		
		public function processData(StdClass $settings, StdClass $data, $entry_id = null) {
			if ($settings->{'text-formatter'} != 'none') {
				$tfm = new TextformatterManager();
				$formatter = $tfm->create($settings->{'text-formatter'});
				$formatted = $formatter->run($data->{'alt-text'});
				$formatted = preg_replace('/&(?![a-z]{0,4}\w{2,3};|#[x0-9a-f]{2,6};)/i', '&amp;', $formatted);
			}

			else {
				$formatted = General::sanitize($data->{'alt-text'});
			}
			
			return (object)array(
				'handle'			=> null,
				'value'				=> $data->{'alt-text'},
				'value_formatted'	=> $formatted,
				'url'				=> $data->{'url'},
				'link' 				=> $data->{'link'},
				'alt-text'			=> $data->{'alt-text'},
				'style'				=> $data->{'style'}
			);
		}
		 public function processRowData(StdClass $settings, StdClass $data, $entry_id = null) {
			
		  return (object)array(
			'handle'      => General::createHandle($data->{'caption'}),
			'value'       => $data->{'caption'},
			'value_formatted' => '<img src="' . $data->{'rel_path'} . '/' . $data->{'file'} . '"/>'
		  );
		}
		public function sanitizeData(StdClass $settings, $data) {
			
			
			if (is_object($data) && isset($data->{'url'})) {
				return $data;
			}elseif (is_array($data) && isset($data['url'])) {
				return (object) $data;
			}else{
				$result = (object)array(
					'url'		=> null,
					'alt-text'	=> null,
					'style'		=> null
				);
				return $result;
			}
		}
		
		public function validateData(StdClass $settings, StdClass $data, MessageStack $errors, $entry_id = null) {
			// Check that either http or http are used:
			if (!preg_match('%^https?://%', $data->url)) {
				$errors->append('url', __('Invalid URL, please check that you entered it correctly.'));

				return false;
			}

			return true;
		}

		public function appendFormattedElement(XMLElement $wrapper, StdClass $settings, StdClass $data, $entry_id = null) {
			//$image = new XMLElement('image');
			$path = str_replace(URL,'',$data->{'url'});
			
			if(strpos($data->{'url'},'workspace')){
				$file = str_replace(URL.'/workspace/','',$data->{'url'});
			}else{
				$file = str_replace(URL,'',$data->{'url'});
			}
			$path = str_replace('/'.$file,'',$path);
			$imagesize = getimagesize(ltrim($path.'/'.$file,'/'));
			$wrapper->setAttributeArray(array(				
				'path' => $data->{'url'},
				'type'=>$imagesize['mime'],
				'set-path' => $path
			));
		
			$filename = new XMLElement('filename', $file);
			$filename->setAttribute('handle',self::getCleanFilename($file));
			//$image->appendChild(new XMLElement('filename', $file));
			//$image->appendChild(new XMLElement('clean-filename', self::getCleanFilename($file)));			
			$wrapper->appendChild($filename);
			$wrapper->appendChild(new XMLElement('alt-text', $data->{'value_formatted'}));
			$wrapper->appendChild(new XMLElement('style', $data->{'style'}));
				if($data->{'link'} != ''){
				if(strpos($data->{'link'},'http://')){
					$link = new XMLElement('link',$data->{'link'});
				}else{
					$link = new XMLElement('link','http://'.$data->{'link'});
				}
				
				$wrapper->appendChild($link);
			}
			$wrapper->setAttribute('style','image');
		}
		private static function getCleanFilename($filename) {
		  return preg_replace("/([^\/]*)(\-[a-f0-9]{13})(\.[^\.]+)$/", '$1$3', $filename);
		}
	}