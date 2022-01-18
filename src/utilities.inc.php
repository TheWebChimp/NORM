<?php

	if(!function_exists('print_a')) {
		function print_a($a) {
			print('<pre>');
			print_r($a);
			print('</pre>');
		}
	}

	if(!function_exists('get_item')) {
		function get_item($var, $key, $default = '') {
			return is_object($var) ?
				( isset( $var->$key ) ? $var->$key : $default ) :
				( isset( $var[$key] ) ? $var[$key] : $default );
		}
	}

	if(!function_exists('camel_to_snake')) {
		function camel_to_snake($val) {
			$val = preg_replace_callback('/[A-Z]/', '_camel_to_snake_callback', $val);
			return ltrim($val, '_');
		}

		function _camel_to_snake_callback($match) {
			return "_" . strtolower($match[0]);
		}
	}

	if(!function_exists('querify')) {
		function querify($fields, $action = false) {

			$ret = [];
			$fields = is_string($fields) ? explode(',', $fields) : $fields;
			$fields = array_map('trim', $fields);

			if ($action == 'escape') {
				foreach ($fields as $field) {
					$ret[] = "`{$field}`";
				}
			} else if ($action == 'string') {
				foreach ($fields as $field) {
					$ret[] = "'{$field}'";
				}
			} else if ($action == 'bind') {
				foreach ($fields as $field) {
					$ret[] = ":{$field}";
				}
			} else if($action == 'param') {
				foreach ($fields as $field) {

					if($field == 'modified') 	$ret[] = "`{$field}` = NOW()";
					else 						$ret[] = "`{$field}` = :{$field}";
				}
			} else { $ret = $fields; }

			return implode(', ', $ret);
		}
	}

	if(!function_exists('pluralize')) {
		function pluralize($singular, $plural = null) {

			if($plural !== null) return $plural;

			$last_letter = strtolower($singular[strlen($singular)-1]);
			switch($last_letter) {
				case 'y':
					return substr($singular,0,-1) . 'ies';
				case 's':
					return $singular . 'es';
				default:
					return $singular . 's';
			}
		}
	}

	if(!function_exists('singularize')) {
		function singularize($params) {
			if (is_string($params)) {
				$word = $params;
			} else if (!$word = $params['word']) {
				return false;
			}

			$singular = array (
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
			foreach ($ignore as $ignore_word) {
				if (substr($lower_word, (-1 * strlen($ignore_word))) == $ignore_word) {
					return $word;
				}
			}

			foreach ($irregular as $singular_word => $plural_word) {
				if (preg_match('/('.$plural_word.')$/i', $word, $arr)) {
					return preg_replace('/('.$plural_word.')$/i', substr($arr[0],0,1).substr($singular_word,1), $word);
				}
			}

			foreach ($singular as $rule => $replacement) {
				if (preg_match($rule, $word)) {
					return preg_replace($rule, $replacement, $word);
				}
			}

			return $word;
		}
	}

	if(!function_exists('tableize')) {
		function tableize(string $word): string {
			$tableized = preg_replace('~(?<=\\w)([A-Z])~u', '_$1', $word);

			if ($tableized === null) {
				throw new \Exception(sprintf('preg_replace returned null for value "%s"', $word));
			}

			return mb_strtolower($tableized);
		}
	}
?>