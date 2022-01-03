<?php
	/**
	 *   ___  ___  ______ _
	 *  / _ \/ _ \/ __/  ' \
	 * /_//_/\___/_/ /_/_/_/
	 *
	 * Provides the abstraction layer for the Plural classes.
	 * @version  2.0
	 * @author   Rodrigo Tejero <rodrigo.tejero@thewebchi.mp>
	 * @license  MIT
	 */

	namespace NORM;

	include 'utilities.inc.php';

	use Dabbie\Dabbie;
	use \PDO;

	class NORM {

		protected static $table;
		protected static $table_fields;
		protected static $singular_class_name;
		protected static $plural_class_name;
		protected static $db_handler;

		/**
		 * Returns the class table name
		 *
		 * @return string $conditions   table name
		 */
		public static function getTable() {

			return static::$table ?? tableize(rtrim(get_called_class(), 's'));
		}

		public static function getTableFields() {

			$singular = ucfirst(static::getTable());
			if(class_exists($singular)) {

				$singular = new $singular;
				$table_fields = $singular->getTableFields();
				return $table_fields;
			}

			return static::$table_fields;
		}

		public static function getSingular() { return static::$singular_class_name ?? ucfirst(self::getTable()); }
		public static function getPlural() { return static::$plural_class_name ?? get_class(); }

		public static function checkSoftDelete() {
			return in_array('NORM\SoftDelete', class_uses(self::getSingular()));
		}

		public static function setDBHandler(Dabbie $handler) {

			static::$db_handler = $handler;
		}

		/**
		 * Gets the database handler to connect
		 *
		 * @return string $dbh  PDO Database Handler (From Dabbie)
		 */
		public static function getDBHandler() {

			return static::$db_handler ? static::$db_handler->getHandler() : null;
		}

		/**
		 * The al'mighty magic __callStatic function
		 *
		 * @param  string $method  Name of the method called
		 * @param  array $params   Non-asociative array with the params from called methos
		 * @return mixed           Return values depending on the method called
		 */
		public static function __callStatic($method, $params) {

			$ret = false;
			$matches = [];

			$res = preg_match('/^((?<method>get|all)((?<not>not)?(?<type>by|like|in|between|exists|regexp)?))(?<field>[A-Za-z]+)$/i', $method, $matches);
			if($res === 1) {

				# Get the matched parameters
				$method = 	get_item($matches, 'method', 'get');
				$type = 	get_item($matches, 'type', 'By');
				$field = 	get_item($matches, 'field', 'Id');

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

				switch($type) {
					case 'BY':
						$conditions = "`{$field}` = '{$params[0]}'";
					break;
					case 'LIKE':
					case 'NOT LIKE':
						$conditions = "`{$field}` {$type} '{$params[0]}'";
					break;
					case 'IN':
					case 'NOT IN':
						$values = implode(',', $params[0]);
						$conditions = "`{$field}` {$type} ({$values})";
					break;
					case 'BETWEEN':
					case 'NOT BETWEEN':
						$conditions = "`{$field}` {$type} ('{$params[0]}' AND '{$params[1]}')";
						# Shift the index up
						$params_index = 2;
					break;
					case 'REGEXP':
					case 'NOT REGEXP':
						$conditions = "`{$field}` {$type} '{$params[0]}'";
					break;
					default:
						$conditions = '';
					break;
				}

				# Execute method
				if($conditions) {

					$options = [];
					$options['conditions'] = [ $conditions ];

					# Now for the actual parameters
					$norm_params = get_item($params, $params_index, []);

					if(is_array($norm_params) && isset($norm_params['conditions'])) {

						if(is_array($norm_params['conditions'])) {

							$options['conditions'] = array_merge($options['conditions'], $norm_params['conditions']);

						} else {

							$options['conditions'] .= $norm_params['conditions'];
						}

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
		 * Retrieve one element from the database depending the conditions
		 *
		 * @param  array $options  List of options intended to modify the query behavior
		 * @return array           Array with User objects, False on error
		 */
		public static function get($options = []) {

			$ret = false;

			$options['show'] = 1;
			$rows = self::all($options);

			if($rows) { $ret = array_shift($rows); }
			return $ret;
		}

		/**
		 * Return the number of elements depending on the conditions
		 *
		 * @param  string $id  Condition for the counting query
		 * @return string      Number of counted elements
		 */
		public static function count($conditions = 1, $table = false) {

			$dbh = self::getDBHandler();
			$ret = 0;

			$conditions = $conditions ?: 1;
			if(is_array($conditions)) {

				$conditions = array_filter($conditions);
				$conditions = implode(' AND ', $conditions);
			}

			$conditions = "WHERE {$conditions}";

			# Generals
			$table = $table ?: self::getTable();

			if(self::checkSoftDelete()) {

				$conditions = $conditions ? "{$conditions} AND deleted != 1" : 'WHERE deleted != 1';
			}

			try {
				$sql = "SELECT COUNT(*) AS total FROM `{$table}` {$conditions};";
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$ret = $stmt->fetch(PDO::FETCH_COLUMN);
			} catch (PDOException $e) {
				throw new \Exception("NORM Database error in NORM::count: {$e->getCode()}");
			}
			return $ret;
		}

		/**
		 * Retrieve all the elements from the database depending the conditions
		 *
		 * @param  array $options  List of options intended to modify the query behavior
		 * @return array           Array with User objects, False on error
		 */
		public static function all($options = []) {

			$dbh = self::getDBHandler();
			$ret = [];

			# Generals
			$table = 		get_item($options, 'table', self::getTable());
			$table_fields = get_item($options, 'table_fields', self::getTableFields());
			$class_name = 	get_item($options, 'class_name', self::getSingular());

			if(!$table || !$table_fields) {
				throw new \Exception('NORM Parameter Error: Missing table, table_fields and/or class_name.');
			}

			$table_fields = is_string($table_fields) ? explode(',', $table_fields) : $table_fields;
			$table_fields = array_map('trim', $table_fields);

			$query_fields = querify(get_item($options, 'query_fields', $table_fields), 'escape');

			# Default variables
			$show =			get_item($options, 'show', 1000);
			$page =			get_item($options, 'page', 1);
			$sort =			get_item($options, 'sort', 'asc');
			$by =			get_item($options, 'by', 'id');
			$group =		get_item($options, 'group', '');

			$conditions =	get_item($options, 'conditions', '');

			$pdoargs =		get_item($options, 'pdoargs', false);
			$pdoargs =		$pdoargs ?: get_item($options, 'args', []);

			$debug =		get_item($options, 'debug', false);
			$code =			get_item($options, 'code', false);
			$query =		get_item($options, 'query', false);

			$offset = $show * ($page - 1);

			// Sanity checks if not arbitrary query
			if(!$query) {

				# Sanity checks

				if(is_array($by) && is_array($sort) && count($by) != count($sort)) {
					throw new \Exception('NORM Parameter Error: sort and by are array but they have different lengths.');
				}

				if(is_string($by)) {

					$by = in_array($by, $table_fields) ? $by : false;

				} elseif(is_array($by)) {

					foreach($by as $b) {
						if(!in_array($b, $table_fields)) $by = false;
						break;
					}
				}

				if(is_string($sort)) {

					$sort =		in_array( $sort, ['asc', 'desc'] ) ? $sort : false;
					$sort =		$sort ? strtoupper($sort) : $sort;

				} elseif(is_array($sort)) {

					foreach($sort as $s) {
						if(!in_array( $s, ['asc', 'desc'] )) $sort = false;
						break;
					}

					if($sort) {
						$sort = array_map(function($s) { return strtoupper($s); }, $sort);
					}
				}

				$offset =	is_numeric($offset) ? $offset : false;
				$show =		is_numeric($show) ? $show : false;
				$group =	in_array($group, $table_fields) ? $group : false;

				if ($by === false || $sort === false || $offset === false || $show === false) {
					throw new \Exception('NORM Parameter Error: sort, by, offset or show not well defined.');
				}

				if($group) {
					if(!in_array($group, $table_fields)) {
						throw new \Exception('NORM Parameter Error: group not well defined.');
					}

					$group = $group ? "GROUP BY {$group}" : '';
				}

				if(is_array($conditions)) {

					$conditions = array_filter($conditions);
					$conditions = implode(' AND ', $conditions);
				}

				$conditions = $conditions ? "WHERE ({$conditions})" : '';

				# Soft Delete
				if(self::checkSoftDelete()) {

					$conditions = $conditions ? "{$conditions} AND deleted != 1" : 'WHERE deleted != 1';
				}

				# By / Sort

				$by_sort = '';

				if(is_array($by)) {

					$by_sort = [];

					if(is_array($sort)) {

						for($i = 0; $i < count($by); $i++) {

							$by_sort[] = "`{$by[$i]}` {$sort[$i]}";
						}

					} else {

						for($i = 0; $i < count($by); $i++) {

							$by_sort[] = "`{$by[$i]}` {$sort}";
						}
					}

					$by_sort = implode(', ', $by_sort);

				} else {

					if(is_array($sort)) {

						$by_sort = "`{$by}` {$sort[0]}";

					} else {

						$by_sort = "`{$by}` {$sort}";
					}
				}
			}

			try {

				$sql = $query ? $query : "SELECT {$query_fields} FROM `{$table}` {$conditions} {$group} ORDER BY {$by_sort} LIMIT {$offset}, {$show}";

				$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

				$stmt = $dbh->prepare($sql);
				$stmt->execute();

				if(class_exists($class_name)) {
					$stmt->setFetchMode(PDO::FETCH_CLASS, $class_name, [$pdoargs]);
				}

				$ret = $stmt->fetchAll();

				$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

				if($query_fields = get_item($options, 'query_fields')) {

					if(is_string($query_fields)) $query_fields = explode(',', $query_fields);
					$query_fields = array_map('trim', $query_fields);

					array_map(function($item) use($query_fields) {


						foreach($item as $k => $v) {
							if($k == 'metas') continue;

							if(!in_array($k, $query_fields) && $v === null) {
								unset($item->{$k});
							}
						}
					}, $ret);
				}

			} catch (PDOException $e) {
				throw new \Exception("NORM Database error: {$e->getCode()}");
			}
			return $ret;
		}
	}
?>