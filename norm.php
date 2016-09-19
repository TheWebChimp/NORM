<?php
	/**
	 * NORM
	 *
	 * Provides the abstraction layer for the Plural classes.
	 *
	 * @version  1.0
	 * @author   Rodrigo Tejero <rodrigo.tejero@thewebchi.mp> & Raul Vera <raul.vera@thewebchi.mp>
	 * @license  MIT
	 */
	class NORM {

		protected static $table;
		protected static $table_fields;
		protected static $singular_class_name;
		protected static $plural_class_name;

		/**
		 * The al'mighty magic __callStatic function
		 * @param  string $method  Name of the method called
		 * @param  array $params   Non asociative array with the params from called methos
		 * @return mixed           Return values depending on the method called
		 */
		public static function __callStatic($method, $params) {
			$ret = false;
			$matches = array();
			# Run the regular expression
			$res = preg_match('/^((get|all)((?:not)?(?:by|like|in|between|exists|regexp)?))([A-Za-z]+)$/i', $method, $matches);
			if ($res === 1) {
				# Get the matched parameters
				$method = get_item($matches, 2, 'get');
				$type = get_item($matches, 3, 'By');
				$field = get_item($matches, 4, 'Id');
				# Snake-ize them
				$method = camel_to_snake($method);
				$type = camel_to_snake($type);
				$field = camel_to_snake($field);
				# Check the type
				$type = strtoupper($type);
				$type = str_replace('_', ' ', $type);
				# Prepare variables
				$conditions = null;
				$params_index = 1;
				switch ($type) {
					case 'BY':
						$conditions = "{$field} = '{$params[0]}'";
					break;
					case 'LIKE':
					case 'NOT LIKE':
						$conditions = "{$field} {$type} '{$params[0]}'";
					break;
					case 'IN':
					case 'NOT IN':
						$values = implode($params[0], ',');
						$conditions = "{$field} {$type} ({$values})";
					break;
					case 'BETWEEN':
					case 'NOT BETWEEN':
						$conditions = "{$field} {$type} ('{$params[0]}' AND '{$params[1]}')";
						# Shift the index up
						$params_index = 2;
					break;
					case 'REGEXP':
					case 'NOT REGEXP':
						$conditions = "{$field} {$type} '{$params[0]}'";
					break;
					default:
						$conditions = '';
					break;
				}
				# Execute method
				if ($conditions) {
					$options = array();
					$options['conditions'] = $conditions;
					# Now for the actual parameters
					$norm_params = get_item($params, $params_index, array());
					if ( is_array($norm_params) && isset( $norm_params['conditions'] ) ) {
						$options['conditions'] .= $norm_params['conditions'];
						unset( $norm_params['conditions'] );
					}
					$options = array_merge($options, $norm_params);
					# And call the function
					$methods = array('get', 'all');
					$method = in_array($method, $methods) ? $method : 'get';
					$ret = self::$method($options);
				}
			}
			return $ret;
		}

		/**
		 * Querify the fields passed (implodes)
		 * @param  array $fields  Array with field list
		 * @return string         String with all the fields imploded for querying
		 */
		public static function querify($fields, $action = false) {
			$ret = array();

			if($action == 'bind') {
				foreach ($fields as $field) {
					$ret[] = ":{$field}";
				}
			}

			else if($action == 'param') {
				foreach ($fields as $field) {
					$ret[] = "{$field} = :{$field}";
				}
			}

			else { $ret = $fields; }

			return implode(', ', $ret);
		}

		/**
		 * Return the number of elements depending on the conditions
		 * @param  string $id  Condition for the counting query
		 * @return string      Number of counted elements
		 */
		public static function count($conditions = 1) {
			global $site;
			$dbh = $site->getDatabase();
			$ret = 0;

			$conditions = $conditions ?: 1;
			if( is_array($conditions) ) {

				$conditions = array_filter($conditions);
				$conditions = implode(' AND ', $conditions);
			}

			# Generals
			$table = static::$table;
			$class_name = static::$plural_class_name;

			try {
				$sql = "SELECT COUNT(*) AS total FROM {$table} WHERE {$conditions};";
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$row = $stmt->fetch();
				$ret = $row->total;
			} catch (PDOException $e) {
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$class_name}::count(): {$e->getMessage()}", 'norm' );
			}
			return $ret;
		}

		/**
		 * Retrieve one element from the database depending the conditions
		 * @param  array $options  List of options intended to modify the query behavior
		 * @return array           Array with User objects, False on error
		 */
		public static function get( $options = array() ) {

			$ret = false;

			$options['show'] = 1;
			$rows = self::all($options);

			if($rows) {

				$ret = array_shift($rows);
			}
			return $ret;
		}

		/**
		 * Retrieve all the elements from the database depending the conditions
		 * @param  array $options  List of options intended to modify the query behavior
		 * @return array           Array with User objects, False on error
		 */
		public static function all( $options = array() ) {
			global $site;
			$dbh = $site->getDatabase();
			$ret = array();

			# Generals
			$table = static::$table;
			$table_fields = static::$table_fields;
			$class_name = static::$plural_class_name;
			$query_fields = static::querify(get_item($options, 'query_fields', $table_fields));

			# Default variables
			$show =			get_item($options, 'show', 1000);
			$page =			get_item($options, 'page', 1);
			$sort =			get_item($options, 'sort', 'asc');
			$by =			get_item($options, 'by', 'id');
			$group =		get_item($options, 'group', '');

			$conditions =	get_item($options, 'conditions', '');
			$pdoargs =		get_item($options, 'pdoargs', array());

			$debug =		get_item($options, 'debug', false);
			$code =			get_item($options, 'code', false);
			$query =		get_item($options, 'query', false);

			$offset = $show * ($page - 1);

			# Sanity checks
			$by =		in_array($by, $table_fields) ? $by : false;
			$sort =		in_array( $sort, array('asc', 'desc') ) ? $sort : false;
			$sort =		$sort ? strtoupper($sort) : $sort;
			$offset =	is_numeric($offset) ? $offset : false;
			$show =		is_numeric($show) ? $show : false;
			$group =	in_array($group, $table_fields) ? $group : false;

			if ($by === false || $sort === false || $offset === false || $show === false) {

				log_to_file('Parameter Error: sort, offset or show not well defined. (Line ' . __LINE__ . ')', 'norm');
				return $ret;
			}

			if($group) {
				if( !in_array($group, $table_fields) ) {

					log_to_file('Parameter Error: group not well defined. (Line ' . __LINE__ . ')', 'norm');
				return $ret;
				}
				$group = 	$group ? "GROUP BY {$group}" : '';
			}

			if( is_array($conditions) ) {

				$conditions = array_filter($conditions);
				$conditions = implode(' AND ', $conditions);
			}
			$conditions = $conditions ? "WHERE {$conditions}" : '';

			# Soft Delete
			if(in_array('deleted', $table_fields)) {

				$conditions .= ($conditions ? ' AND deleted != 1' : 'WHERE deleted != 1');
			}

			try {

				$sql = $query ? $query : "SELECT {$query_fields} FROM {$table} {$conditions} {$group} ORDER BY {$by} {$sort} LIMIT {$offset}, {$show}";

				if($debug) echo $sql;
				if($code) return $sql;

				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$stmt->setFetchMode(PDO::FETCH_CLASS, static::$singular_class_name, array($pdoargs));
				$ret = $stmt->fetchAll();

			} catch (PDOException $e) {

				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$class_name}::all(): {$e->getMessage()}", 'norm' );
			}
			return $ret;
		}
	}
?>