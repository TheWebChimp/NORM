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