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
	use \PDO;

	class CROOD {

		protected $table;
		protected $table_fields;
		protected $update_fields;
		protected $mandatory_fields;
		protected $field_types;
		protected $search_fields;

		protected $singular_class_name;
		protected $plural_class_name;

		protected static $db_handler;

		public $id;

		# Meta Model
		protected $meta_id;
		protected $meta_table;

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

		/**
		 * Constructor
		 */
		function __construct() {
			$params = func_get_args();

			$now = date('Y-m-d H:i:s');

			$this->init();
			$this->fill();
			$this->default();

			if(!$this->id) {

				$this->id = 0;
				$this->created = $now;
				$this->modified = $now;
				$this->metas = new \stdClass();

			} else {

				$params = $this->preInit($params);
				$this->prepare($params ?? []);
			}
		}

		public function getTable() {
			return $this->table ?? tableize(get_class($this));
		}

		public function setTable($table) {
			$this->table = $table;
		}

		public function getTableFields() {
			return $this->table_fields;
		}

		public function getSingularClass() {
			return $this->singular_class_name ?? get_class($this);
		}

		public function getPluralClass() {
			return $this->plural_class_name ?? pluralize(get_class($this));
		}

		public function setPluralClass($plural) {
			$this->plural_class_name = $plural;
		}

		public function getMetaId() {
			return $this->meta_id ?? 'id_' . $this->getTable();
		}

		public function setMetaId($meta_id) {
			$this->$meta_id = $meta_id;
		}

		public function getMetaTable() {
			$meta_table = $this->meta_table ?? $this->getTable() . '_meta';
			return strtolower($meta_table);
		}

		public function setMetaTable($meta_table) {
			$this->meta_table = $meta_table;
		}

		public function init() {}
		public function default() {}
		public function prepare($args) {}

		public function __toString() {
			return json_encode($this);
		}

		public static function setDBHandler(Dabbie $handler) {

			static::$db_handler = $handler;
		}

		public static function getDBHandler() {

			return static::$db_handler ? static::$db_handler->getHandler() : null;
		}

		public function fill() {
			foreach($this->table_fields as $field) {

				$this->{$field} = $this->{$field} ?? '';
			}
		}

		public function preSave() {

			//Mandatory Fields
			if($this->mandatory_fields && is_array($this->mandatory_fields)) {

				foreach($this->mandatory_fields as $field) {
					if(empty($this->$field)) {
						throw new \Exception("CROOD: Saving instance for `{$this->getTable()}` without completing mandatory field: {$field}");
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

		function save() {

			$dbh = static::getDBHandler();
			$ret = false;

			$table_fields = 	querify($this->table_fields);
			$escape_fields = 	querify($this->table_fields, 'escape');
			$bind_fields = 		querify($this->table_fields, 'bind');
			$param_fields = 	querify($this->update_fields, 'param');

			$this->preSave();

			try {
				# Create or update
				$sql = "INSERT INTO `{$this->getTable()}` ({$escape_fields})
						VALUES ({$bind_fields})
						ON DUPLICATE KEY UPDATE {$param_fields}";

				$stmt = $dbh->prepare($sql);

				foreach($this->table_fields as $table_field) {
					$stmt->bindValue(":{$table_field}", $this->$table_field);
				}

				$stmt->execute();

				if (! $this->id && $dbh->lastInsertId() ) {
					$this->id = $dbh->lastInsertId();
				}

				//Updating metas
				if( isset($this->metas) && (is_object($this->metas) || is_array($this->metas)) ) {

					if(is_array($this->metas)) {
						$this->metas = (object) $this->metas;
					}

					$this->updateMetas((array) $this->metas);
				}

				$this->prepare([]);
				$this->postSave();

				$ret = $this->id;
				return $ret;

			} catch (PDOException $e) {
				throw new \Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}. Query: {$sql}");
			}
		}

		function delete() {

			$dbh = static::getDBHandler();
			$ret = false;

			try {

				if(in_array('NORM\SoftDelete', class_uses($this->getSingularClass()))) {
					$sql = "UPDATE `{$this->getTable()}` SET `deleted` = 1 WHERE `id` = :id";
				} else {
					$sql = "DELETE FROM `{$this->getTable()}` WHERE `id` = :id";
				}

				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->execute();
				$ret = true;

			} catch (PDOException $e) {
				throw new \Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}.");
			}
			return $ret;
		}

		function param($args, $param) {

			if(isset($args[$param])) {
				return $args[$param];
			}

			return false;
		}

		protected function preInit($args = false) {

			$this->postSave();

			//Metas
			if($this->getMetaTable() && (!isset($this->metas) || !$this->metas)) {
				$this->metas = new \stdClass();
			}

			//Args
			if(is_array($args) && isset($args[0])) {

				$args = $args[0];

				if($metas = $this->param($args, 'fetch_metas')) {

					if(!is_array($metas) && $metas != 1) {
						$metas = explode(',', $metas);
					}

					$this->fetchMetas(is_array($metas) ? $metas : null);
				}

				return $args;

			} else {

				return $args;
			}
		}

		public function expand(array $args) {

			foreach ($args as $prop => $opts) {

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

		public function fetchMetas(?array $metas = null) { $this->getMetas($metas); }

		public function getMeta(string $name, $default = '') {

			$dbh = static::getDBHandler();
			$ret = $default;

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			try {
				$sql = "SELECT `value` FROM `{$meta_table}` WHERE `{$meta_id}` = :id AND `name` = :name";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->bindValue(':name', $name);
				$stmt->execute();
				if($row = $stmt->fetch()) {

					$ret = @unserialize($row->value);
					if ($ret === false) {
						$ret = $row->value;
					}

					if(isset($this->metas) && is_object($this->metas)) {
						$this->metas->$name = $ret;
					} else {
						$this->metas = new \stdClass();
						$this->metas->$name = $ret;
					}
				}
			} catch (PDOException $e) {
				throw new \Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
				error_log("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return $ret;
		}

		public function getMetas(?array $default_metas = null) {

			$dbh = static::getDBHandler();
			$ret = [];

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			$condition = "`{$meta_id}` = :id";

			if($default_metas) {

				if($default_metas) {

					$default_metas = querify($default_metas, 'string');
					$condition .= " AND `name` IN ({$default_metas})";
				}
			}

			try {
				$sql = "SELECT `name`, `value` FROM `{$meta_table}` WHERE {$condition}";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->execute();
				$metas = $stmt->fetchAll();

				foreach($metas as $meta) {

					$ret[$meta->name] = @unserialize($meta->value);
					if ($ret[$meta->name] === false) {
						$ret[$meta->name] = $meta->value;
					}
				}

				if(isset($this->metas) && is_object($this->metas)) {

					foreach($ret as $k => $meta) {

						$this->metas->$k = $meta;
					}

				} else {
					$this->metas = (object) $ret;
				}

			} catch (PDOException $e) {
				throw new \Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return (object) $ret;
		}

		public function updateMeta($name, $value) {

			$dbh = static::getDBHandler();
			$ret = false;

			$meta_table = $this->getMetaTable();
			$meta_id = $this->getMetaId();

			if ( is_array($value) || is_object($value) ) {
				$value = serialize($value);
			}

			try {
				$sql = "INSERT INTO `{$meta_table}` (id, {$meta_id}, value, name) VALUES (0, :meta_id, :value, :name) ON DUPLICATE KEY UPDATE `value` = :value";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':meta_id', $this->id);
				$stmt->bindValue(':value', $value);
				$stmt->bindValue(':name', $name);
				$stmt->execute();

				if(isset($this->metas) && is_object($this->metas)) {
					$this->metas->$name = $value;
				} else {
					$this->metas = new \stdClass();
					$this->metas->$name = $value;
				}

				if($dbh->lastInsertId()) {
					$ret = true;
				}
			} catch (PDOException $e) {
				throw new \Exception("CROOD Database error: {$e->getCode()} (Line {$e->getLine()}) in " . $this->getSingularClass() . "::" . __FUNCTION__ . ": {$e->getMessage()}");
			}
			return $ret;
		}

		public function updateMetas($metas) {

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

	trait SoftDelete {}
?>