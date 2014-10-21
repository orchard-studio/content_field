<?php

	/**
	 * @package header_content
	 */
	class BlockContentType extends TextContentType {
		public function getName() {
			return __('Block');
		}

		public function appendSettingsHeaders(HTMLPage $page) {

		}

		public function appendSettingsInterface(XMLElement $wrapper, $field_name, StdClass $settings = null, MessageStack $errors) {
			$group = new XMLElement('div');
			$group->addClass('group');
			$wrapper->appendChild($group);

			// Text formatter:
			$field = new Field();
			$group->appendChild($field->buildFormatterSelect(
				isset($settings->{'text-formatter'})
					? $settings->{'text-formatter'}
					: null,
				"{$field_name}[text-formatter]",
				'Text Formatter'
			));

			// Styles:
			$div = new XMLElement('div');
			$label = Widget::Label(__('Available Styles'));
			$input = Widget::Input(
				"{$field_name}[available-styles]",
				$settings->{'available-styles'}
			);
			$label->appendChild($input);
			$div->appendChild($label);

			$list = new XMLElement('ul');
			$list->addClass('tags');

			foreach (explode(',', $settings->{'available-styles'}) as $tag) {
				$tag = trim($tag);

				if ($tag == '') continue;

				$list->appendChild(new XMLElement('li', $tag));
			}

			$div->appendChild($list);
			$group->appendChild($div);
		}

		public function sanitizeSettings($settings) {
			$settings = parent::sanitizeSettings($settings);

			if (isset($settings->{'available-styles'}) === false) {
				$settings->{'available-styles'} = __('Header') . ', ' . __('Sub Header'). ', ' . __('Footer'). ', ' . __('Block Quote'). ', ' . __('Cite'). ', ' . __('List');
			}

			return $settings;
		}

		public function appendPublishHeaders(HTMLPage $page) {
			$url = URL . '/extensions/content_field/assets';
			$page->addStylesheetToHead($url . '/publish.css', 'screen');
		}

		public function appendPublishInterface(XMLElement $wrapper, $field_name, StdClass $settings, StdClass $data, MessageStack $errors, $entry_id = null) {

			// Style:
			$values = array();

			foreach (explode(',', $settings->{'available-styles'}) as $style) {
				$style = trim($style);

				if ($style == '') continue;

				$values[] = array(
					$style, $style == $data->{'style'}, $style
				);
			}

			$label = Widget::Label('Block style');
			$label->appendChild(Widget::Select(
				"{$field_name}[data][style]", $values
			));

			$wrapper->appendChild($label);
			
			// value
			$text = Widget::Textarea(
				"{$field_name}[data][value]", 1, 50, (
					isset($data->value)
						? $data->value
						: null
				)
			);
			$text->addClass('size-' . $settings->{'text-size'});

			if ($settings->{'text-formatter'} != 'none') {
				$text->addClass($settings->{'text-formatter'});
			}
			$label = Widget::Label('Block Text');
			$label->setAttribute('class','block-text');
			$label->appendChild($text);
			$wrapper->appendChild($label);
		}

		public function processData(StdClass $settings, StdClass $data, $entry_id = null) {
					
			$result = parent::processData($settings, $data, $entry);
			$result->style = $data->{'style'};

			return $result;
		}

		public function appendFormattedElement(XMLElement $wrapper, StdClass $settings, StdClass $data, $entry_id = null) {
			parent::appendFormattedElement($wrapper, $settings, $data, $entry_id);			
			if (isset($data->style)) {
				$wrapper->setAttribute('style', General::createHandle($data->style));
				if(General::createHandle($data->style) == 'list'){
					$val = $wrapper->getValue();
					$wrapper->replaceValue(array(''));
					$wrapper->setAttribute('value',$val);
					$listarray = explode(',',$data->value);
					$ul = new XMLElement('list');
					//$ul->setAttribute('class','list');
					foreach($listarray as $index => $value){
						$li = new XMLElement('item',rtrim(ltrim($value)));
						$ul->appendChild($li);
					}
					
				}
				$wrapper->appendChild($ul);
			}
			
		}
	}