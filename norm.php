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

			if ( substr($method, 0, 5) == 'getBy' ) {

				$options = array();
				$field = substr($method, 5);
				$field = camel_to_snake($field);
				$options['conditions'] = "{$field} = '{$params[0]}'";

				if ( isset($params[1]) && is_array($params) ){

					if(isset($params[1]['conditions'])) {

						$options['conditions'] .= $params[1]['conditions'];
						unset($params[1]['conditions']);
					}

					$options = array_merge($options, $params[1]);
				}

				return self::get($options);
			}

			else if ( substr($method, 0, 7) == 'getLike' ) {

				$options = array();
				$field = substr($method, 7);
				$field = camel_to_snake($field);
				$options['conditions'] = "{$field} LIKE '%{$params[0]}%'";

				if ( isset($params[1]) && is_array($params) ){

					if(isset($params[1]['conditions'])) {

						$options['conditions'] .= $params[1]['conditions'];
						unset($params[1]['conditions']);
					}

					$options = array_merge($options, $params[1]);
				}

				return self::get($options);
			}

			else if ( substr($method, 0, 5) == 'allBy' ) {

				$options = array();
				$field = substr($method, 5);
				$field = camel_to_snake($field);
				$options['conditions'] = "{$field} = '{$params[0]}'";

				if ( isset($params[1]) && is_array($params) ){

					if(isset($params[1]['conditions'])) {

						$options['conditions'] .= $params[1]['conditions'];
						unset($params[1]['conditions']);
					}

					$options = array_merge($options, $params[1]);
				}

				return self::all($options);
			}

			else if ( substr($method, 0, 7) == 'allLike' ) {

				$options = array();
				$field = substr($method, 7);
				$field = camel_to_snake($field);
				$options['conditions'] = "{$field} LIKE '%{$params[0]}%'";

				if ( isset($params[1]) && is_array($params) ){

					if(isset($params[1]['conditions'])) {

						$options['conditions'] .= $params[1]['conditions'];
						unset($params[1]['conditions']);
					}

					$options = array_merge($options, $params[1]);
				}

				return self::all($options);
			}
		}

		/**
		 * Querify the fields passed (implodes)
		 * @param  array $fields  Array with field list
		 * @return string         String with all the fields imploded for querying
		 */
		public static function querify($fields, $action = false) {
			$ret = array();

			if ($action == 'bind') {
				foreach ($fields as $field) {
					$ret[] = ":{$field}";
				}
			}

			else if($action == 'param') {
				foreach ($fields as $field) {
					$ret[] = "{$field} = :{$field}";
				}
			}

			else {

				$ret = $fields;
			}

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
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$class_name}::count(): {$e->getMessage()}" );
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

			#Generals
			$table = static::$table;
			$table_fields = static::$table_fields;
			$class_name = static::$plural_class_name;
			$query_fields = static::querify(get_item($options, 'query_fields', $table_fields));

			#Default variables
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
			$sort =		strtoupper($sort);
			$offset =	is_numeric($offset) ? $offset : false;
			$show =		is_numeric($show) ? $show : false;
			$group =	in_array($group, $table_fields) ? $group : false;

			if ($group === false || $by === false || $sort === false || $offset === false || $show === false) {

				log_to_file('Parameter Error: by, group, sort, offset or show not well defined. (Line' . __FILE__ . ')', 'norm');
				return $ret;
			}

			$group = 	$group ? "GROUP BY {$group}" : '';

			$conditions = $conditions ? "WHERE {$conditions}" : '';

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