<?php

	if(!function_exists('print_a')) {
		/**
		 * Pretty print for <pre>
		 * @param $object
		 * @return void
		 */
		function print_a($object) {
			print('<pre>');
			print_r($object);
			print('</pre>');
		}
	}

	if(!function_exists('get_item')) {
		/**
		 * Gets an item from an array or object, if not found, returns a default or empty string
		 * @param       $var
		 * @param       $key
		 * @param mixed $default
		 * @return mixed
		 */
		function get_item($var, $key, $default = '') {
			return !is_object($var) ? ($var[$key] ?? $default) : ($var->$key ?? $default);
		}
	}

	if(!function_exists('camel_to_snake')) {
		/**
		 * Convert camelCase to snake_case
		 * @param string $val Original string
		 * @return string      The converted string
		 */
		function camel_to_snake(string $val): string {
			$val = preg_replace_callback('/[A-Z]/', '_camel_to_snake_callback', $val);
			return ltrim($val, '_');
		}

		/**
		 * @param $match
		 * @return string
		 */
		function _camel_to_snake_callback($match): string {
			return "_" . strtolower($match[0]);
		}
	}

	if(!function_exists('snake_to_camel')) {
		/**
		 * Convert snake_case to camelCase
		 * @param string $val Original string
		 * @return string      The converted string
		 */
		function snake_to_camel(string $val): string {
			$val = str_replace(' ', '', ucwords(str_replace('_', ' ', $val)));
			return strtolower(substr($val, 0, 1)) . substr($val, 1);
		}
	}

	if(!function_exists('querify')) {
		/**
		 * @param mixed $fields
		 * @param mixed $action
		 * @return string
		 */
		function querify($fields, $action = false): string {

			$ret = [];
			$fields = is_string($fields) ? explode(',', $fields) : $fields;
			$fields = array_map('trim', $fields);

			switch($action) {
				case 'escape':
					foreach($fields as $field) {
						$ret[] = "`{$field}`";
					}
					break;
				case 'string':
					foreach($fields as $field) {
						$ret[] = "'{$field}'";
					}
					break;
				case 'bind':
					foreach($fields as $field) {
						$ret[] = ":{$field}";
					}
					break;
				case 'param':
					foreach($fields as $field) {
						if($field == 'modified') $ret[] = "`{$field}` = NOW()";
						else $ret[] = "`{$field}` = :{$field}";
					}
					break;
				default:
					$ret = $fields;
			}

			return implode(', ', $ret);
		}
	}

	if(!function_exists('pluralize')) {
		/**
		 * @param $singular
		 * @param $plural
		 * @return mixed|string
		 */
		function pluralize($singular, $plural = null) {

			if($plural !== null) return $plural;

			$last_letter = strtolower($singular[strlen($singular) - 1]);
			switch($last_letter) {
				case 'y':
					return substr($singular, 0, -1) . 'ies';
				case 's':
					return $singular . 'es';
				default:
					return $singular . 's';
			}
		}
	}

	if(!function_exists('singularize')) {
		/**
		 * @param $params
		 * @return mixed
		 */
		function singularize($params) {
			if(is_string($params)) {
				$word = $params;
			} else if(!$word = $params['word']) {
				return false;
			}

			$singular = array(
				'/(quiz)zes$/i' => '\\1',
				'/(matr)ices$/i' => '\\1ix',
				'/(vert|ind)ices$/i' => '\\1ex',
				'/^(ox)en/i' => '\\1',
				'/(alias|status)es$/i' => '\\1',
				'/([octop|vir])i$/i' => '\\1us',
				'/(cris|ax|test)es$/i' => '\\1is',
				'/(shoe)s$/i' => '\\1',
				'/(o)es$/i' => '\\1',
				'/(bus)es$/i' => '\\1',
				'/([m|l])ice$/i' => '\\1ouse',
				'/(x|ch|ss|sh)es$/i' => '\\1',
				'/(m)ovies$/i' => '\\1ovie',
				'/(s)eries$/i' => '\\1eries',
				'/([^aeiouy]|qu)ies$/i' => '\\1y',
				'/([lr])ves$/i' => '\\1f',
				'/(tive)s$/i' => '\\1',
				'/(hive)s$/i' => '\\1',
				'/([^f])ves$/i' => '\\1fe',
				'/(^analy)ses$/i' => '\\1sis',
				'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\\1\\2sis',
				'/([ti])a$/i' => '\\1um',
				'/(n)ews$/i' => '\\1ews',
				'/s$/i' => ''
			);

			$irregular = array(
				'person' => 'people',
				'man' => 'men',
				'child' => 'children',
				'sex' => 'sexes',
				'move' => 'moves'
			);

			$ignore = array(
				'equipment',
				'information',
				'rice',
				'money',
				'species',
				'series',
				'fish',
				'sheep',
				'press',
				'sms',
			);

			$lower_word = strtolower($word);
			foreach($ignore as $ignore_word) {
				if(substr($lower_word, (-1 * strlen($ignore_word))) == $ignore_word) {
					return $word;
				}
			}

			foreach($irregular as $singular_word => $plural_word) {
				if(preg_match('/(' . $plural_word . ')$/i', $word, $arr)) {
					return preg_replace('/(' . $plural_word . ')$/i', substr($arr[0], 0, 1) . substr($singular_word, 1), $word);
				}
			}

			foreach($singular as $rule => $replacement) {
				if(preg_match($rule, $word)) {
					return preg_replace($rule, $replacement, $word);
				}
			}

			return $word;
		}
	}

	if(!function_exists('tableize')) {
		/**
		 * @param string $word
		 * @return string
		 * @throws Exception
		 */
		function tableize(string $word): string {
			$tableized = preg_replace('~(?<=\\w)([A-Z])~u', '_$1', $word);

			if($tableized === null) {
				throw new Exception(sprintf('preg_replace returned null for value "%s"', $word));
			}

			return mb_strtolower($tableized);
		}
	}
