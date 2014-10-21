<?php

  /*
  Image Blocks content type by George Wilson 2013
  Based on Symphony's upload field (+ michael-e's unique upload field)

  GitHub: g-wilson
  Email: george@g-wilson.co.uk
  */

  /**
   * @package content_field
   */
  class ImageBlockContentType implements ContentType {
    public function getName() {
      return __('Image Block');
    }

    public function appendSettingsHeaders(HTMLPage $page) {
      // Attach CSS + JS to settings page
    }

    public function appendSettingsInterface(XMLElement $wrapper, $field_name, StdClass $settings = null, MessageStack $errors) {

      // Destination Folder

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
    }

    public function sanitizeSettings($settings) {
      if (is_array($settings)) {
        $settings = (object)$settings;
      }

      else if (is_object($settings) === false) {
        $settings = new StdClass();
      }

      if (isset($settings->{'enabled'}) === false) {
        $settings->{'enabled'} = 'yes';
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
      // Attach JS + CSS to publish page
    }

    public function appendPublishInterface(XMLElement $wrapper, $field_name, StdClass $settings, StdClass $data, MessageStack $errors, $entry_id = null) {
      
      $group = new XMLElement('div', NULL, array('class' => 'group two columns'));


      // The upload form

      $fileUpload = Widget::Label('Image upload');
      $fileUpload->setAttribute('class', 'file column');
      $span = new XMLElement('span', NULL, array('class' => 'frame'));
      
      if (isset($data->{'file'})) {
        $filename = $settings->{'destination'} . '/' . basename($data->{'file'});
        $file = $data->{'abs_path'} . '/' . $data->{'file'};
        $span->appendChild(new XMLElement('span', Widget::Anchor(preg_replace("![^a-z0-9]+!i", "$0&#8203;", $filename), URL . $filename)));
      }
      else {
        $filename = null;
      }
      $span->appendChild(Widget::Input("{$field_name}[data][file]", $filename, ($filename ? 'hidden' : 'file')));
      
      if ( isset($filename) && ( file_exists($file) === false || !is_readable($file)) ) {
        // File should be there, but can't be found
        $span = Widget::Error(
          $span, __('The file uploaded is no longer available. Please check that it exists, and is readable.')
        );
      } elseif (isset($data->{'error'})) {
        // Error was encountered during save
        $span = Widget::Error(
          $span, $data->{'error'}
        );
      }

      $fileUpload->appendChild($span);
      $group->appendChild($fileUpload);

      // The position selector

      $positionLabel = Widget::Label(__('Position'));
      $positionLabel->setAttribute('class', 'column');
      $positionOptions = array(
        ['center', 0, 'Center'],
        ['left', 0, 'Float left'],
        ['right', 0, 'Float right'],
        ['full', 0, 'Full width']
      );
        // Preset stored value
      foreach ($positionOptions as $key => $value) {
        if ( $data->{'position'} == $value[0] ) {
          $positionOptions[$key][1] = 1;
        }
      }
      $positionSelect = Widget::Select("{$field_name}[data][position]", $positionOptions);
      $positionLabel->appendChild($positionSelect);
      $group->appendChild($positionLabel);


      // The caption

      $captionLabel = Widget::Label(__('Caption'));
      $captionField = Widget::Input("{$field_name}[data][caption]", $data->{'caption'});
      $captionLabel->appendChild($captionField);


      // Pass back exisiting file info

      $hidden = new XMLElement('div');
      $hidden->appendChild( Widget::Input("{$field_name}[data][abs_path]", $data->{'abs_path'}, 'hidden') );
      $hidden->appendChild( Widget::Input("{$field_name}[data][rel_path]", $data->{'rel_path'}, 'hidden') );
      $hidden->appendChild( Widget::Input("{$field_name}[data][old_file]", $data->{'file'}, 'hidden') );
      $hidden->appendChild( Widget::Input("{$field_name}[data][old_size]", $data->{'size'} . '', 'hidden') );
      $hidden->appendChild( Widget::Input("{$field_name}[data][old_type]", $data->{'mimetype'}, 'hidden') );


      $wrapper->appendChild($group);
      $wrapper->appendChild($captionLabel);
      $wrapper->appendChild($hidden);
    }

    public function processData(StdClass $settings, StdClass $data, $entry_id = null) {
      
      if ( isset($data->file['name']) ) {   // New file submitted, upload + replace

        // Write directory
        $abs_path = DOCROOT . '/' . trim($settings->{'destination'}, '/');
        $rel_path = str_replace('/workspace', '', $settings->{'destination'});

        // Make filename unique
        $uniqueFilename = self::getUniqueFilename($data->file['name']);

        // New file submitted, use new
        $result = array(
          'position' => $data->position,
          'caption' => $data->caption
        );

        // Attempt to upload the file
        $uploaded = General::uploadFile(
          $abs_path, $uniqueFilename, $data->file['tmp_name'],
          Symphony::Configuration()->get('write_mode', 'file')
        );

        if ( !$uploaded === false) {
          // TODO: get size + type from uploaded file not from browser
          $result['file'] = $uniqueFilename;
          $result['size'] = $data->file['size'];
          $result['mimetype'] = $data->file['type'];
          $result['abs_path'] = $abs_path;
          $result['rel_path'] = $rel_path;
        } else {
          $result['error'] = 'There was an error uploading the file, is the filesize too large?';
        }

        // If there is an existing file, (and a new one), remove the old one
        if ( $data->old_file ) {
          General::deleteFile($data->abs_path . '/' . $data->old_file);
        }

      } else {   // No new file, use existing file info, but update other fields

        $result = array(
          'file' => $data->old_file,
          'size' => $data->old_size,
          'mimetype' => $data->old_type,
          'abs_path' => $data->abs_path,
          'rel_path' => $data->rel_path,
          'position' => $data->position,
          'caption' => $data->caption
        );

      }

      return $this->sanitizeData($settings, $result);
    }

    public function processRowData(StdClass $settings, StdClass $data, $entry_id = null) {

      return (object)array(
        'handle'      => General::createHandle($data->{'caption'}),
        'value'       => $data->{'caption'},
        'value_formatted' => '<img src="' . $data->{'rel_path'} . '/' . $data->{'file'} . '"/>'
      );
    }

    public function sanitizeData(StdClass $settings, $data) {
      $accept = array( 'file', 'size', 'mimetype', 'abs_path', 'rel_path', 'position', 'caption');
      $result = (object)array(
       'file' => null,
       'size' => null,
       'mimetype' => null,
       'abs_path' => null,
       'rel_path' => null,
       'position' => null,
       'caption' => null,
      );

      if (is_object($data) || is_array($data)) {
        foreach ($data as $key => $value) {
          if (in_array($key, $accept) === false) continue;
          $result->{$key} = $value;
        }
      }
      return (object)$data;
    }

    public function validateData(StdClass $settings, StdClass $data, MessageStack $errors, $entry_id = null) {
      // TODO
      return true;
    }

    public function appendFormattedElement(XMLElement $wrapper, StdClass $settings, StdClass $data, $entry_id = null) {

      //$image = new XMLElement('image');
	  
	  $data->rel_path = str_replace(DOCROOT,URL,$data->abs_path);
	$data->set_path = str_replace(DOCROOT,'',$data->abs_path);
      $wrapper->setAttributeArray(array(
        'size' => $data->size,
        'path' => $data->rel_path.'/'.$data->file,
        'type' => $data->mimetype,
		'set-path' => $data->set_path
      ));
	  $filename = new XMLElement('filename', $data->file);
      $filename->setAttribute('handle',self::getCleanFilename($data->file));
      $caption = new XMLElement('caption', General::sanitize($data->caption));
      $position = new XMLElement('position', $data->position);
      
	  $wrapper->appendChild($filename);	  
      $wrapper->appendChild($caption);
      $wrapper->appendChild($position);
	  $wrapper->setAttribute('style','image');
    }


    // From Unique Upload Field extension

    private static function getUniqueFilename($filename) {
      ## since uniqid() is 13 bytes, the unique filename will be limited to ($crop+1+13) characters;
      $crop  = '30';
      return preg_replace("/([^\/]*)(\.[^\.]+)$/e", "substr('$1', 0, $crop).'-'.uniqid().'$2'", $filename);
    }

    private static function getCleanFilename($filename) {
      return preg_replace("/([^\/]*)(\-[a-f0-9]{13})(\.[^\.]+)$/", '$1$3', $filename);
    }
  }