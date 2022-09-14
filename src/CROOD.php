<?php
	/**
	 *                        __
	 *  ___________  ___  ___/ /
	 * / __/ __/ _ \/ _ \/ _  /
	 * \__/_/  \___/\___/\_,_/
	 *
	 * Provides the abstraction layer for the Single classes.
	 * @version  2.0
	 * @author   Rodrigo Tejero <rodrigo.tejero@thewebchi.mp>
	 * @license  MIT
	 */

	namespace NORM;

	include 'utilities.inc.php';

	use Dabbie\Dabbie;
	use Exception;
	use PDOException;
	use stdClass;

	/**
	 * @property false|string $created
	 * @property stdClass     $metas
	 * @property string       $modified
	 */
	class CROOD {

		/**
		 * @var string
		 */
		protected $table;
		/**
		 * @var array
		 */
		protected $table_fields;
		/**
		 * @var array
		 */
		protected $update_fields;
		/**
		 * @var array
		 */
		protected $mandatory_fields;
		/**
		 * @var
		 */
		protected $field_types;
		/**
		 * @var
		 */
		protected $search_fields;

		/**
		 * @var string
		 */
		protected $singular_class_name;
		/**
		 * @var string
		 */
		protected $plural_class_name;

		/**
		 * @var
		 */
		protected static $db_handler;

		/**
		 * @var int
		 */
		public $id;

		# Meta Model
		/**
		 * @var
		 */
		protected $meta_id;
		/**
		 * @var
		 */
		protected $meta_table;

		/**
		 * @return array
		 */
		public function __debugInfo() {

			$result = get_object_vars($this);
			unset($result['table']);
			unset($result['table_fields']);
			unset($result['update_fields']);
			unset($result['mandatory_fields']);
			unset($result['field_types']);
			unset($result['search_fields']);

			unset($result['singular_class_name']);
			unset($result['plural_class_name']);

			unset($result['meta_id']);
			unset($result['meta_table']);

			return $result;
		}

		public function __call($name, $arguments) {

			$singular = $this->getSingularClass();
			$plural = $this->getPluralClass();

			// Check if the plural function exists

			if(method_exists($plural, $name)) {

				$r = new \ReflectionMethod($plural, $name);
				$params = $r->getParameters();

				if(isset($params[0])) {

					// Check if the first parameter receives the id for the
					if($params[0]->getName() == 'id_' . camel_to_snake($singular)) {

						return call_user_func_array("{$plural}::{$name}", [$this->id, ...$arguments]);
					}

					// Check if first parameter is a property inside the called class
					if(in_array($params[0]->getName(), $this->table_fields)) {

						return call_user_func_array("{$plural}::{$name}", [$this->{$params[0]->getName()}, ...$arguments]);
					}
				}

			} else {

				throw new Exception("CROOD Magic function: The function `{$name}` does not exists in class: {$plural}");
			}
		}

		/**
		 * Constructor
		 * @throws Exception
		 */
		function __construct() {
			$params = func_get_args();

			$now = date('Y-m-d H:i:s');

			$this->init();
			$this->fill();

			if(!$this->id) {

				$this->default();
				$this->id = 0;
				$this->created = $now;
				$this->modified = $now;
				$this->metas = new stdClass();

			} else {

				$params = $this->preInit($params);
				try {

					$this->prepare($params ?? []);
				} catch(Exception $e) {
					echo $e->getMessage();
				}
			}
		}

		/**
		 * @return string
		 * @throws Exception
		 */
		public function getTable(): string {
			return $this->table ?? tableize(get_class($this));
		}

		/**
		 * @param $table
		 * @return void
		 */
		public function setTable($table) {
			$this->table = $table;
		}

		/**
		 * @return array
		 */
		public function getTableFields(): array {
			return $this->table_fields;
		}

		/**
		 * @return string
		 */
		public function getSingularClass(): string {
			return $this->singular_class_name ?? get_class($this);
		}

		/**
		 * @return string
		 */
		public function getPluralClass(): string {
			return $this->plural_class_name ?? pluralize(get_class($this));
		}

		/**
		 * @param $plural
		 * @return void
		 */
		public function setPluralClass($plural) {
			$this->plural_class_name = $plural;
		}

		/**
		 * @return string
		 * @throws Exception
		 */
		public function getMetaId(): string {
			return $this->meta_id ?? 'id_' . $this->getTable();
		}

		/**
		 * @param $meta_id
		 * @return void
		 */
		public function setMetaId($meta_id) {
			$this->$meta_id = $meta_id;
		}

		/**
		 * @return string
		 * @throws Exception
		 */
		public function getMetaTable(): string {
			$meta_table = $this->meta_table ?? $this->getTable() . '_meta';
			return strtolower($meta_table);
		}

		/**
		 * @param $meta_table
		 * @return void
		 */
		public function setMetaTable($meta_table) {
			$this->meta_table = $meta_table;
		}

		/**
		 * @return void
		 */
		public function init() { }

		/**
		 * @return void
		 */
		public function default() { }

		/**
		 * @param $args
		 * @return void
		 */
		public function prepare($args) { }

		/**
		 * @return false|string
		 */
		public function __toString() {
			return json_encode($this);
		}

		/**
		 * @param Dabbie $handler
		 * @return void
		 */
		public static function setDBHandler(Dabbie $handler) {

			static::$db_handler = $handler;
		}

		/**
		 * @return null
		 */
		public static function getDBHandler() {

			return static::$db_handler ? static::$db_handler->getHandler() : null;
		}

		/**
		 * @return void
		 */
		public function fill() {
			foreach($this->table_fields as $field) {

				$this->{$field} = $this->{$field} ?? '';
			}
		}

		/**
		 * @return void
		 * @throws Exception
		 */
		public function preSave() {

			//Mandatory Fields
			if($this->mandatory_fields && is_array($this->mandatory_fields)) {

				foreach($this->mandatory_fields as $field) {
					if(empty($this->$field)) {
						throw new Exception("CROOD: Saving instance for `{$this->getTable()}` without completing mandatory field: {$field}");
					}
				}
			}

			//Checking field types
			if($this->field_types) {
				foreach($this->table_fields as $field) {
					foreach($this->field_types as $field_type => $def) {

						if($field == $field_type) {

							if($def['type'] == 'json') {

								if(!$this->{$field}) {
									$this->{$field} = [];
								}
								$this->{$field} = @json_encode($this->{$field});
							}
						}
					}
				}
			}
		}

		/**
		 * @return void
		 */
		function postSave() {

			//Checking field types
			if($this->field_types) {

				foreach($this->table_fields as $field) {
					foreach($this->field_types as $field_type => $def) {

						if($field == $field_type) {

							if($def['type'] == 'json') {

								if(!$this->{$field}) {
									$this->{$field} = [];
								}
								$this->{$field} = @json_decode($this->{$field});
							}
						}
					}
				}
			}
		}

		/**
		 * @throws Exception
		 */
		function save(): int {

			$dbh = static::getDBHandler();

			//$table_fields = 	querify($this->table_fields);
			$escape_fields = querify($this->table_fields, 'escape');
			$bind_fields = querify($this->table_fields, 'bind');
			$param_fields = querify($this->update_fields, 'param');

			$this->preSave();
			$sql = '';

			try {
				# Create or update
				$sql = sprintf(/** @lang text */ 'INSERT INTO `%s` (%s)
						VALUES (%s)
						ON DUPLICATE KEY UPDATE %s', $this->getTable(), $escape_fields, $bind_fields, $param_fields);

				$stmt = $dbh->prepare($sql);

				foreach($this->table_fields as $table_field) {
					$stmt->bindValue(":{$table_field}", $this->$table_field);
				}

				$stmt->execute();

				if(!$this->id && $dbh->lastInsertId()) {
					$this->id = $dbh->lastInsertId();
				}

				//Updating metas
				if(isset($this->metas) && (is_object($this->metas) || is_array($this->metas))) {

					if(is_array($this->metas)) {
						$this->metas = (object)$this->metas;
					}

					$this->updateMetas((array)$this->metas);
				}

				$this->prepare([]);
				$this->postSave();

				return $this->id;

			} catch(PDOException $e) {
				throw new Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}. Query: {$sql}");
			}
		}

		/**
		 * @return bool
		 * @throws Exception
		 */
		function delete(): bool {

			$dbh = static::getDBHandler();

			try {

				if(in_array('NORM\SoftDelete', class_uses($this->getSingularClass()))) {
					$sql = /** @lang text */
						"UPDATE `{$this->getTable()}` SET `deleted` = 1 WHERE `id` = :id";
				} else {
					$sql = /** @lang text */
						"DELETE FROM `{$this->getTable()}` WHERE `id` = :id";
				}

				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->execute();

			} catch(PDOException $e) {
				throw new Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}.");
			}
			return true;
		}

		/**
		 * @param $args
		 * @param $param
		 * @return false|mixed
		 */
		function param($args, $param) {

			if(isset($args[$param])) {
				return $args[$param];
			}

			return false;
		}

		/**
		 * @param mixed $args
		 * @return mixed
		 * @throws Exception
		 */
		protected function preInit($args = false) {

			$this->postSave();

			//Metas
			if($this->getMetaTable() && (!isset($this->metas) || !$this->metas)) {
				$this->metas = new stdClass();
			}

			//Args
			if(is_array($args) && isset($args[0])) {

				$args = $args[0];

				$plural = $this->getPluralClass();

				// Magic functions
				foreach($args as $key => $value) {

					//Preparing ladder
					$ladder_args = $args;
					$main_ladder_key = '';

					foreach($ladder_args as $arg_key => $arg) {

						if(!$main_ladder_key && $arg == $value) {

							$main_ladder_key = $arg_key;

						} else if(strpos($arg_key, "{$main_ladder_key}.") == 0) {

							$ladder_args[str_replace("{$main_ladder_key}.", '', $arg_key)] = $arg;
						}

						unset($ladder_args[$arg_key]);
					}

					// Magic function with args
					if(is_array($value) && isset($value[0]) && $value[0] && is_string($value[0]) && is_callable([$this, $value[0]])) {
						try {
							$method = $value[0];
							array_shift($value);

							if(is_array($value[0])) {
								$value = array_merge($value[0], [ 'args' => $ladder_args ]);
								$vale = [$value];
							}


							$this->$key = call_user_func_array([$this, $method], $value);

						} catch(Exception $e) { }

					// Magic function without args
					} else if(is_string($value) && is_callable([$this, $value])) {
						try {

							$to_pass = count($ladder_args) ? [ 'args' => $ladder_args ] : [];

							$this->$key = call_user_func_array([$this, $value], [$to_pass]);
						} catch(Exception $e) { }
					}
				}

				// Metas

				if($metas = $this->param($args, 'fetch_metas')) {

					if(!is_array($metas) && $metas != 1) {
						$metas = explode(',', $metas);
					}

					try {
						$this->fetchMetas(is_array($metas) ? $metas : null);
					} catch(Exception $e) { }
				}

				// Dynamic Fetch

				foreach($args as $key => $value) {

					if(preg_match('/fetch_(?<entity>.*)/', $key, $matches)) {

						$entity = $matches['entity'];

						$received_class = ucwords(snake_to_camel($entity));

						if($entity != 'metas' && class_exists($received_class)) {

							// Singular class
							if(is_subclass_of($received_class, 'NORM\CROOD')) {
								$method = 'getById';
								$obj = new $received_class();
								$plural_class = $obj->getPluralClass();
							} else {
								$method = 'allById';
								$plural_class = $received_class;
							}

							// Check if instance has id from entity
							$id_entity = "id_{$entity}";

							try {

								if(isset($this->$id_entity)) {

									$this->$entity = call_user_func("{$plural_class}::{$method}", $this->$id_entity, is_array($value) ? $value : []);

								} else {

									$this->$entity = call_user_func("{$plural_class}::{$method}" . ucwords($this->getSingularClass()), $this->id, is_array($value) ? $value : []);
								}
							} catch(Exception $e) {
							}
						}
					}
				}
			}
			return $args;
		}

		/**
		 * @param array $args
		 * @return void
		 */
		public function expand(array $args) {

			foreach($args as $prop => $opts) {

				$local = get_item($opts, 'local', 'id');
				$foreign = get_item($opts, 'foreign');
				$model = get_item($opts, 'model');
				$method = get_item($opts, 'method', 'all');
				$params = get_item($opts, 'params', []);

				$call = "{$method}By" . snake_to_camel($foreign);

				$this->$prop = $model::$call($this->$local, $params);
			}
		}

		/* Meta Model */
		/* -------------------------------------------------------------------------------------- */

		/**
		 * @param array|null $metas
		 * @return void
		 * @throws Exception
		 */
		public function fetchMetas(?array $metas = null) { $this->getMetas($metas); }

		/**
		 * @param string $name
		 * @param mixed  $default
		 * @return mixed
		 * @throws Exception
		 */
		public function getMeta(string $name, $default = '') {

			$dbh = static::getDBHandler();
			$ret = $default;

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			try {
				$sql = /** @lang text */
					"SELECT `value` FROM `{$meta_table}` WHERE `{$meta_id}` = :id AND `name` = :name";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->bindValue(':name', $name);
				$stmt->execute();
				if($row = $stmt->fetch()) {

					$ret = @unserialize($row->value);
					if($ret === false) {
						$ret = $row->value;
					}

					if(!isset($this->metas) || !is_object($this->metas)) {
						$this->metas = new stdClass();
					}
					$this->metas->$name = $ret;
				}
			} catch(PDOException $e) {
				error_log("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
				throw new Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return $ret;
		}

		public function meta(string $name, $default = '') {

			$dbh = static::getDBHandler();
			$ret = $default;

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			try {
				$sql = /** @lang text */
					"SELECT `value` FROM `{$meta_table}` WHERE `{$meta_id}` = :id AND `name` = :name";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->bindValue(':name', $name);
				$stmt->execute();
				if($row = $stmt->fetch()) {

					$ret = @unserialize($row->value);
					if($ret === false) {
						$ret = $row->value;
					}
				}
			} catch(PDOException $e) {
				error_log("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
				throw new Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return $ret;
		}

		/**
		 * @param array|null $default_metas
		 * @return object
		 * @throws Exception
		 */
		public function getMetas(?array $default_metas = null) {

			$dbh = static::getDBHandler();
			$ret = [];

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			$condition = "`{$meta_id}` = :id";

			if($default_metas) {

				$default_metas = querify($default_metas, 'string');
				$condition .= " AND `name` IN ({$default_metas})";
			}

			try {
				$sql = /** @lang text */
					"SELECT `name`, `value` FROM `{$meta_table}` WHERE {$condition}";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->execute();
				$metas = $stmt->fetchAll();

				foreach($metas as $meta) {

					$ret[$meta->name] = @unserialize($meta->value);
					if($ret[$meta->name] === false) {
						$ret[$meta->name] = $meta->value;
					}
				}

				if(isset($this->metas) && is_object($this->metas)) {

					foreach($ret as $k => $meta) {

						$this->metas->$k = $meta;
					}

				} else {
					$this->metas = (object)$ret;
				}

			} catch(PDOException $e) {
				throw new Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return (object)$ret;
		}

		/**
		 * @param $name
		 * @param $value
		 * @return bool
		 * @throws Exception
		 */
		public function updateMeta($name, $value): bool {

			$dbh = static::getDBHandler();
			$ret = false;

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			if(is_array($value) || is_object($value)) {
				$value = serialize($value);
			}

			try {
				$sql = /** @lang text */
					"INSERT INTO `{$meta_table}` (id, {$meta_id}, value, name) VALUES (0, :meta_id, :value, :name) ON DUPLICATE KEY UPDATE `value` = :value";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':meta_id', $this->id);
				$stmt->bindValue(':value', $value);
				$stmt->bindValue(':name', $name);
				$stmt->execute();

				if(!isset($this->metas) || !is_object($this->metas)) {
					$this->metas = new stdClass();
				}
				$this->metas->$name = $value;

				if($dbh->lastInsertId()) {
					$ret = true;
				}
			} catch(PDOException $e) {
				throw new Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return $ret;
		}

		/**
		 * @param $metas
		 * @return bool
		 * @throws Exception
		 */
		public function updateMetas($metas): bool {

			$ret = false;

			if($metas && is_array($metas)) {

				foreach($metas as $name => $value) {

					$this->updateMeta($name, $value);
				}

				$ret = true;
			}

			return $ret;
		}
	}

	trait SoftDelete { }
