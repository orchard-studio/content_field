<?php

	/**
	 * @package content_field
	 */
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once FACE . '/interface.exportablefield.php';
	require_once FACE . '/interface.importablefield.php';
	require_once __DIR__ . '/../libs/message-stack.php';
	require_once __DIR__ . '/../libs/content-type.php';
	require_once __DIR__ . '/../libs/image-upload-content.php';	
	require_once __DIR__ . '/../libs/text-content-type.php';
	require_once __DIR__ . '/../libs/block-content.php';
	require_once __DIR__ . '/../libs/image-content-type.php';
	// TODO: get dynamically from folder
	
	ini_set('xdebug.var_display_max_depth', 500);
	ini_set('xdebug.var_display_max_children', 2048);
	ini_set('xdebug.var_display_max_data', 28186);
	class FieldContent extends Field implements ExportableField, ImportableField {
		protected $errors;

		public function __construct() {
			parent::__construct();

			$this->_name = 'Content';
			$this->_required = true;
			//$this->_showcolumn = true;
			$this->errors = array();
		}

		public function createTable() {
			$field_id = $this->get('id');

			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`handle` VARCHAR(255) DEFAULT NULL,
					`value` TEXT DEFAULT NULL,
					`value_formatted` TEXT DEFAULT NULL,
					`type` VARCHAR(64) DEFAULT NULL,
					`data` TEXT DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					FULLTEXT KEY `value` (`value`),
					FULLTEXT KEY `value_formatted` (`value_formatted`),
					FULLTEXT KEY `type` (`type`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");
		}

		/**
		 * Fetch a list of installed content types.
		 */
		public function getInstances() {
			// TODO: get dynamically from folder
			$instances = (object)array(
				'text-content'	=> new TextContentType(),
				'image-block'	=> new ImageBlockContentType(),
				'image-upload'=> new ImageUploadContentType(),
				'block'=> new BlockContentType()
			);
			/*$context['items']->{'image-block'} = new ImageUploadContentType();
			$context['items']->{'block'} = new BlockContentType();
			$context['items']->{'text-content'} =  new TextContentType();
			$context['items']->{'image-block'} =  new ImageBlockContentType();*/
			Symphony::ExtensionManager()->notifyMembers(
				'AppendContentType', '*', array(
					'items'	=> $instances
				)
			);

			$instances = (array)$instances;
			//var_dump($instances);
			
			$check = uksort($instances, function($a, $b) {
				
				return strcasecmp($a, $b);
			});
			//var_dump($instances);
		//	die;
			return $instances;
		}

		public function getSettings() {
			if (is_object($this->get('settings'))) {
				return $this->get('settings');
			}

			else if (is_array($this->get('settings'))) {
				return (object)$this->get('settings');
			}

			return json_decode($this->get('settings'));
		}

		public function findDefaults(array &$fields) {
			$fields['required'] = 'yes';
			$fields['default_type'] = 'text-content';
			$fields['settings'] = new StdClass();
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			Extension_Content_Field::appendSettingsHeaders();
			
			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();
			$all_errors = isset($errors['settings'])
				? $errors['settings']
				: array();
			$order = $this->get('sortorder');

			// Default size
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$values = array();

			foreach ($all_instances as $type => $instance) {
				$values[] = array($type, $this->get('default_type') == $type, $instance->getName());
			}

			$label = Widget::Label('Default Content Type');
			$label->appendChild(Widget::Select(
				"fields[{$order}][default_type]", $values
			));

			$group->appendChild($label);
			$wrapper->appendChild($group);

			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
			foreach ($all_instances as $type => $instance) {
				$interface = new XMLElement('fieldset');
				$interface->addClass('content-type content-type-' . $type);
				$interface->setAttribute('data-type', $type);
				$field_name = "fields[{$order}][settings][$type]";

				$input = Widget::Input("{$field_name}[enabled]", 'no', 'hidden');
				$wrapper->appendChild($input);

				$legend = new XMLElement('legend');
				$legend->setValue($instance->getName());
				$interface->appendChild($legend);

				$settings = $instance->sanitizeSettings(
					isset($all_settings->{$type})
						? $all_settings->{$type}
						: new StdClass()
				);
				$messages = isset($all_errors[$type])
					? $all_errors[$type]
					: new MessageStack();

				$instance->appendSettingsInterface(
					$interface, $field_name,
					$settings, $messages
				);

				// Enable this content type:
				$input = Widget::Input("{$field_name}[enabled]", 'yes', 'checkbox');

				if ($settings->{'enabled'} == 'yes') {
					$input->setAttribute('checked', 'checked');
				}

				$label = Widget::Label(
					__('%s Enable this content type', array(
						$input->generate()
					))
				);
				$label->addClass('enable-content-type');
				$interface->appendChild($label);

				$wrapper->appendChild($interface);
			}
		}

		public function checkFields(array &$errors, $checkForDuplicates = true) {
			parent::checkFields($errors, $checkForDuplicates);

			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();
			$all_errors = array();
			$status = is_array($errors) && !empty($errors)
				? self::__ERROR__
				: self::__OK__;

			foreach ($all_instances as $type => $instance) {
				$settings = $instance->sanitizeSettings(
					isset($all_settings->{$type})
						? $all_settings->{$type}
						: new StdClass()
				);
				$all_errors[$type] = new MessageStack();
				$valid = $instance->validateSettings($settings, $all_errors[$type]);
				
				// An error occured:
				if ($valid === false) {
					$status = self::__ERROR__;
				}
			}

			if ($status == self::__ERROR__) {
				$errors['settings'] = $all_errors;
			}

			return $status;
		}

		public function commit() {
			if (!parent::commit()) return false;

			$id = $this->get('id');
			$handle = $this->handle();

			if ($id === false) return false;

			$fields = array(
				'field_id'		=> $id,
				'default_type'	=> $this->get('default_type'),
				'settings'		=> is_string($this->get('settings'))
					? $this->get('settings')
					: json_encode($this->get('settings'))
			);
			
			return FieldManager::saveSettings($id, $fields);
		}
		public function object_to_array($obj) {
			if(is_object($obj)){
				$obj = (array) $obj;		
				$new = array();
				foreach($obj as $key => $val) {
					$new[$key] = self::object_to_array($val)? self::object_to_array($val) : $val;
				}		
				return $new;       
			}		
		}
		public function displayPublishPanel(XMLElement &$wrapper, $all_data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			
			$all_data = (array) json_decode($all_data['data']);
			
			
			foreach($all_data as $block => $blockvalues){
				if(is_object($blockvalues)){
					$all_data[$block] = $this->object_to_array($blockvalues);
				}else{
					$all_data[$block] = $blockvalues;				
				}
			}
			
			Extension_Content_Field::appendPublishHeaders();
			
			$all_instances = $this->getInstances();
			
			$all_settings = $this->getSettings();
			
			//$sortorder = $this->get('sortorder');
			$element_name = $this->get('element_name');
			$label = Widget::Label($this->get('label'));

			if ($this->get('required') != 'yes') {
				$label->appendChild(new XMLElement('i', __('Optional')));
			}
			
			if ($error != null) {
				$label = Widget::Error($label, $error);
			}

			$wrapper->appendChild($label);			
			$p = new XMLElement('p');
			$p->setAttributeArray(array('class'=>'help toggle js-affix js-affix-top'));
			$expand = new XMLElement('a','Expand all');
			$collapse = new XMLElement('a','Collapse all');
			$br = new XMLElement('br');
			$expand->setAttribute('class','expand');
			$collapse->setAttribute('class','collapse');
			$p->appendChild($expand);
			$p->appendChild($br);
			$p->appendChild($collapse);
			$duplicator = new XMLElement('ol');
			//$duplicator->addClass('content-field-duplicator');
			$duplicator->setAttribute('data-preselect', $this->get('default_type'));
			
			// Data is given is stupid backwars form, fix it:
			if (is_array($all_data)) {
				$temp = array();
				
				//var_dump($all_data);
				foreach ($all_data as $key => $values) {
					
					if (is_array($values) === false) {
						if (isset($temp[0]) === false) {
							$temp[0] = array();
						}

						$temp[0][$key] = $values;
					}
					
					else foreach ($values as $index => $value) {
						//$temp[$index][$key] = $value;
						$temp[$key] = $value;
					}
					
				}

				$all_data = $temp;
			}
			
			// Append content:
			//foreach($all_data as $in => $value){
				
				
				if (is_array($all_data)){
					foreach ($all_data as $index => $item) {
						//var_dump($item);
						$field_name = "fields[$element_name][$index]";
						
						$type = $item['type'];
						$data = $item['data'];
						/*$data = isset($item['data'])
							? json_decode($item['data'])
							: null;*/
						
						// No content type found:
						if (array_key_exists($type, $all_instances) === false) {
							continue;
						}
						
						$instance = $all_instances[$type];
						//var_dump($index);
						//var_dump($item);
						//var_dump($all_instances);
						
						$settings = $instance->sanitizeSettings(
							isset($all_settings->{$type})
								? $all_settings->{$type}
								: new StdClass()
						);
						
						$errors = isset($this->errors[$index])
							? $this->errors[$index]
							: new MessageStack();
						$data = $instance->sanitizeData($settings, $data);
						
						$item = new XMLElement('li');
						
						$item->addClass('content-type-' . $type);
						$item->setAttribute('data-type', $type);
						
						$header = new XMLElement('header');
						$header->setAttribute('data-name',$instance->getName());
						$header->addClass('main');
						$header->appendChild(
							new XMLElement('strong', $instance->getName())
						);
						$item->appendChild($header);

						$interface = new XMLElement('div');
						$item->appendChild($interface);

						$instance->appendPublishInterface($interface, $field_name, $settings, $data, $errors, $entry_id);

						// Append content type:
						$input = new XMLElement('input');
						$input->setAttribute('name', "{$field_name}[type]");
						$input->setAttribute('type', 'hidden');
						$input->setAttribute('value', $type);
						$item->appendChild($input);
						$duplicator->appendChild($item);						
					}
				}
			//}
			//die;
				// Append content templates:
				$i = 0;
				foreach ($all_instances as $type => $instance) {
					//var_dump($type);
					$field_name = "fields[$element_name][-1]";
					
					$settings = $instance->sanitizeSettings(
						isset($all_settings->{$type})
							? $all_settings->{$type}
							: new StdClass()
					);
					$errors = new MessageStack();
					$data = $instance->sanitizeData($settings, null);

					if ($settings->{'enabled'} !== 'yes') {
						continue;
					}

					$item = new XMLElement('li');
					$item->addClass('template content-type-' . $type);
					$item->setAttribute('data-type', $type);

					$header = new XMLElement('header');
					$header->addClass('main');
					$header->setAttribute('data-name',$instance->getName());
					$header->appendChild(
						new XMLElement('strong', $instance->getName())
					);
					$item->appendChild($header);

					$interface = new XMLElement('div');
					$item->appendChild($interface);
					
					$instance->appendPublishInterface($interface, $field_name, $settings, $data, $errors, $entry_id);
					
					// Append content type:
					$input = new XMLElement('input');
					$input->setAttribute('name', "{$field_name}[type]");
					$input->setAttribute('type', 'hidden');
					$input->setAttribute('value', $type);
					$item->appendChild($input);

					$duplicator->appendChild($item);
					$i++;
				}
			
			
			$frame = new XMLElement('div');
			$frame->addClass('frame');
			$frame->addClass('content-field-duplicator');
			$wrapper->appendChild($p);
			$frame->appendChild($duplicator);
			$wrapper->appendChild($frame);		
		}

		public function checkPostFieldData($data, &$message, $entry_id = null) {
			
			$is_required = $this->get('required') == 'yes';
			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();
			$has_content = false;
			$this->errors = array();
			
			if (is_array($data)){
				foreach ($data as $index => $item) {
					$has_content = true;
					$item_type = $item['type'];
					$item_data = isset($item['data'])
						? $item['data'] : null;

					// No content type found:
					
					
					if (array_key_exists($item['type'], $all_instances) === false) {
						$message = __(
							'Unable to locate content type "%s".',
							array($item['type'])
						);

						return self::__INVALID_FIELDS__;
					}

					$this->errors[$index] = new MessageStack();
					$instance = $all_instances[$item_type];
					$settings = $instance->sanitizeSettings(
						isset($all_settings->{$item_type})
							? $all_settings->{$item_type}
							: new StdClass()
					);
					
					//var_dump($item_data);
					$item_data = $instance->sanitizeData($settings, $item_data);
					//var_dump($item_data);
					$valid = $instance->validateData($settings, $item_data, $this->errors[$index], $entry_id);
					//var_dump($valid);
					//var_dump($item_data);
					// An error occured:
					if ($valid === false) {
						// Show generic error message:
						if ($this->errors[$index]->valid() === false) {
							$message = __(
								"An error occured in '%s'.",
								array($this->get('label'))
							);
						}

						return self::__INVALID_FIELDS__;
					}
				}
			}
			// Complain if no items where added:
			
			if ($is_required && $has_content === false) {
				$message = __(
					"'%s' is a required field.",
					array($this->get('label'))
				);

				return self::__MISSING_FIELDS__;
			}
			

			return self::__OK__;
		}

		public function processRawFieldData($all_data, &$status, &$message = null, $simulate = false, $entry_id = null) {
			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();
			
			$status = self::__OK__;
			$results = array();
			
			if (is_array($all_data)) {
				foreach ($all_data as $index => $item) {
					$type = $item['type'];
					
					$data = isset($item['data'])
						? $item['data'] : null;
					if (array_key_exists($item['type'], $all_instances) === false) {
						$message = __(
							'Unable to locate content type "%s".',
							array($item['type'])
						);
						$status = self::__ERROR__;
						return $results;
					}
					//var_dump($item);
					$instance = $all_instances[$type];
					$settings = $instance->sanitizeSettings(
						isset($all_settings->{$type})
							? $all_settings->{$type}
							: new StdClass()
					);
					$data = $instance->sanitizeData($settings, $data);
					$data = $instance->processData($settings, $data, $entry_id);
					$alldata = array();
					
					if(method_exists($instance,'processRowData')){
						$row = $instance->processRowData($settings, $data, $entry_id);				
					}else{
						$row = (object) array('handle'=>$data->{'value'},'value'=>$data->{'value'},'value_formatted'=>$data->{'value'});
					}
					//var_dump($type);
					$row->type = $type;			
					$row->data = $data;
					
					//if(array_key_exists($type,$results)){
						$results[][$type] = $row;
						
					//}else{
						//$results[$type] = $row;
					//}
				}
				
			}
			
			$res['value'] = $all_data[0]['data']['value'];
			$res['value_formatted'] = General::sanitize($all_data[0]['data']['value']);
			$res['handle'] = $all_data[0]['data']['value'];
			$res['data'] = json_encode($results);	
				
			return $res;
		}

		public function fetchIncludableElements() {
			return array(
				$this->get('element_name') . ': all-items',
				$this->get('element_name') . ': one-items',
				$this->get('element_name') . ': three-items'
			);
		}

		public function appendFormattedElement(XMLElement $wrapper, $all_data, $encode = false, $mode = null, $entry_id = null) {
			
			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();
			//var_dump($all_data);
			// Data is given is stupid backwars form, fix it:
			if (is_array($all_data)) {
				$temp = array();
				
				foreach ($all_data as $key => $values) {
					if (is_array($values) === false) {
						if (isset($temp[0]) === false) {
							$temp[0] = array();
						}

						$temp[0][$key] = $values;
					}

					else foreach ($values as $index => $value) {
						$temp[$index][$key] = $value;
					}
				}

				$all_data = $temp;
			}
			
			
			if (is_array($all_data)){
					$all_data = $all_data[0];					
					//var_dump($all_data);
					$datas =  isset($all_data['data'])	?  json_decode($all_data['data']) : null;	
					
						
						if($mode== 'all-items'){
							$element = new XMLElement($this->get('element_name'));
							
							
							foreach($datas as $alldata => $dataobj){
									$type = $all_data['type'];
									
									$data = $this->object_to_array($dataobj);
								//var_dump($data);
					
									
									
									$element->setAttribute('mode', $mode);
									
									
									foreach($data as $colheader => $colvalue){										
										//var_dump($colvalue);
										$instance = $all_instances[$colheader];
										//var_dump($colvalue);
										$settings = $instance->sanitizeSettings(isset($all_settings->{$colheader})? $all_settings->{$colheader}	: new StdClass());
										$errors = isset($this->errors[$index])? $this->errors[$index] : new MessageStack();
										$data = $instance->sanitizeData($settings, $colvalue['data']);							
										$item = new XMLElement($colheader);
										//$item->setAttribute('type', $colheader);		
										
										$instance->appendFormattedElement($item, $settings, $data, $entry_id);										
										//var_dump($item);
										$element->appendChild($item);
									}
									$wrapper->appendChild($element);
							}
							$children = $wrapper->getChildren();
							$wrapper->setChildren(array($children[0]));
						}
					//die;
			}
			
			
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			
			$em = new EntryManager();
			$entry = $em->fetch($entry_id);
			$sectionid = $entry[0]->get('section_id');
			$fm = new FieldManager();
			$field = $fm->fetch(null,$sectionid);
			$fieldid = array_keys($field);
			$fieldid = $fieldid[0];
			$field = $field[$fieldid];
			
			if(array_key_exists('type',$data) && !isset($data['type'])){				
				$link->setValue($field->get('label').'-'.$entry_id);				
				return $link->generate();
			}else{
				return $entry_id;			
			
			}
			//return $link;
		}

		public function getImportModes() {
			return array(
				'getPostdata' =>	ImportableField::ARRAY_VALUE
			);
		}

		/**
		 * Give the field some data and ask it to return a value.
		 *
		 * @param mixed $all_data
		 * @param integer $entry_id
		 * @return array|null
		 */
		public function prepareImportValue($all_data, $mode, $entry_id = null) {
			$results = array();
			$modes = (object)$this->getImportModes();
			
			// Not supported mode
			if($mode !== $modes->getPostdata) {
				return null;
			}

			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();

			if (is_array($all_data)) foreach ($all_data as $index => $item) {
				$type = $item['type'];
				$data = isset($item['data'])
					? $item['data'] : null;

				// No content type found:
				if (array_key_exists($item['type'], $all_instances) === false) {
					$message = __(
						'Unable to locate content type "%s".',
						array($item['type'])
					);

					return $results;
				}

				$instance = $all_instances[$type];
				$settings = $instance->sanitizeSettings(
					isset($all_settings->{$type})
						? $all_settings->{$type}
						: new StdClass()
				);

				$data = $instance->sanitizeData($settings, $data);
				$data = $instance->processData($settings, $data, $entry_id);

				$row = $instance->processRowData($settings, $data, $entry_id);
				$row->type = $type;
				$row->data = json_encode($data);

				foreach ($row as $key => $value) {
					$results[$key][$index] = $value;
				}
			}

			return $results;
		}

		/**
		 * Return a list of supported export modes for use with `prepareExportValue`.
		 *
		 * @return array
		 */
		public function getExportModes() {
			return array(
				'getPostdata' =>		ExportableField::POSTDATA
			);
		}

		/**
		 * Give the field some data and ask it to return a value using one of many
		 * possible modes.
		 *
		 * @param mixed $all_data
		 * @param integer $mode
		 * @param integer $entry_id
		 * @return array|null
		 */
		 public function prepareTextValue($data, $entry_id){	
			
			$em = new EntryManager();
			$entry = $em->fetch($entry_id);
			$sectionid = $entry[0]->get('section_id');
			$fm = new FieldManager();
			$field = $fm->fetch(null,$sectionid);
			$fieldid = array_keys($field);
			$fieldid = $fieldid[0];
			$field = $field[$fieldid];
			if(array_key_exists('type',$data) && !isset($data['type'])){				
				//$link->setValue($field->get('label').'-'.$entry_id);
				return $field->get('label').'-'.$entry_id;				
			}else{
				return $entry_id;			
			
			}
			
		}
		function preparePlainTextValue($data, $entry_id){			
			return '**********';			
		}
		
		public function prepareExportValue($all_data, $mode, $entry_id = null) {
			$all_instances = $this->getInstances();
			$all_settings = $this->getSettings();
			$results = array();
			//var_dump($all_data);
			//die;
			// Data is given is stupid backwars form, fix it:
			if (is_array($all_data)) {
				$temp = array();

				foreach ($all_data as $key => $values) {
					if (is_array($values) === false) {
						if (isset($temp[0]) === false) {
							$temp[0] = array();
						}

						$temp[0][$key] = $values;
					}

					else foreach ($values as $index => $value) {
						$temp[$index][$key] = $value;
					}
				}

				$all_data = $temp;
			}

			if (is_array($all_data)) foreach ($all_data as $index => $item) {
				$results[] = array(
					'type' =>	$item['type'],
					'data' =>	json_decode($item['data'], true)
				);
			}

			return $results;
		}
	}