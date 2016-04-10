<?php
	/**
	 * CROOD
	 *
	 * Provides the abstraction layer for the Single classes.
	 *
	 * @version  1.2
	 * @author   Rodrigo Tejero <rodrigo.tejero@thewebchi.mp> & Raul Vera <raul.vera@thewebchi.mp>
	 * @license  MIT
	 */
	class CROOD extends Model {

		protected $table;
		protected $table_fields;
		protected $update_fields;
		protected $search_fields;
		protected $singular_class_name;
		protected $plural_class_name;

		public $id;

		# MetaModel
		protected $meta_id;
		protected $meta_table;

		function init($args = false) { }

		# Create & Update
		function save() {

			global $site;
			$dbh = $site->getDatabase();
			$ret = false;

			$table_fields = $this->querify($this->table_fields);
			$bind_fields = $this->querify($this->table_fields, 'bind');
			$param_fields = $this->querify($this->update_fields, 'param');

			if( in_array('fts', $this->table_fields) && count($this->search_fields) ) {

				$fts_fields = array();
				foreach( $this->search_fields as $search_field ) {

					$fts_fields[] = $this->$search_field;
				}
				$this->fts = '[' . implode('][', pieces) . ']';
			}

			try {
				# Create or update
				$sql = "INSERT INTO {$this->table} ({$table_fields})
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

				$ret = $this->id;

			} catch (PDOException $e) {
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$this->singular_class_name}::" . __FUNCTION__ . ": {$e->getMessage()}. Query: {$sql}", 'crood' );
			}
			return $ret;
		}

		/**
		 * Delete model
		 * @return boolean True on success, False otherwise
		 */
		function delete() {
			global $site;
			$dbh = $site->getDatabase();
			$ret = false;

			try {

				if(in_array('deleted', $this->table_fields)) {

					$sql = "UPDATE {$this->table} SET deleted = 1 WHERE id = :id";

				} else {

					$sql = "DELETE FROM {$this->table} WHERE id = :id";
				}

				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->execute();
				$ret = true;
			} catch (PDOException $e) {
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$this->singular_class_name}::" . __FUNCTION__ . ": {$e->getMessage()}.", 'crood' );
			}
			return $ret;
		}

		function __toString() {
			return json_encode($this);
		}

		/**
		 * Querify the fields passed (implodes)
		 * @param  array $fields  Array with field list
		 * @return string         String with all the fields imploded for querying
		 */
		public function querify($fields, $action = false) {

			$ret = array();

			if ($action == 'bind') {
				foreach ($fields as $field) {
					$ret[] = ":{$field}";
				}
			}

			else if($action == 'param') {
				foreach ($fields as $field) {

					if($field == 'modified') 	$ret[] = "{$field} = NOW()";
					else 						$ret[] = "{$field} = :{$field}";
				}
			} else { $ret = $fields; }

			return implode(', ', $ret);
		}

		/*  ____      _ __     ____                 __  _
		   /  _/___  (_) /_   / __/_  ______  _____/ /_(_)___  ____  _____
		   / // __ \/ / __/  / /_/ / / / __ \/ ___/ __/ / __ \/ __ \/ ___/
		 _/ // / / / / /_   / __/ /_/ / / / / /__/ /_/ / /_/ / / / (__  )
		/___/_/ /_/_/\__/  /_/  \__,_/_/ /_/\___/\__/_/\____/_/ /_/____/
		                                                                   */

		protected function preInit($args = false) {

			//Metas
			if(isset($this->meta_table) && $this->meta_table && (!isset($this->metas) || !$this->metas)) {
				$this->metas = new stdClass();
			}

			//Args
			if(is_array($args) && isset($args[0])) {

				$init_args = $args[0];
				$is_assoc = is_array($init_args) ? array_keys($init_args) !== range(0, count($init_args) - 1) : false;

				if($is_assoc) {

					if( array_key_exists('fetch_metas', $init_args) || in_array('fetch_metas', $init_args) ) {
						$this->fetchMetas();
					}

					if( array_key_exists('expand', $init_args) ) {

						$this->expand($init_args['expand']);
					}

					return $init_args;

				} else {

					if( in_array('fetch_metas', $init_args) ) {
						$this->fetchMetas();
					}

					return $args[0];
				}

			} else {

				return $args;
			}
		}

		protected function postInit($args = false) { return $args; }

		/* Auxiliar functions */
		/* -------------------------------------------------------------------------------------- */

		public function fetchMetas() {

			$this->metas = $this->getMetas();
		}

		public function expand($args) {

			if( is_array($args) ) {

				foreach ($args as $prop => $opts) {

					$local = get_item($opts, 'local', 'id');
					$foreign = get_item($opts, 'foreign');
					$model = get_item($opts, 'model');
					$method = get_item($opts, 'method', 'all');
					$params = get_item($opts, 'params', array());

					$call = "{$method}By" . snake_to_camel($foreign);

					$this->$prop = $model::$call($this->$local, $params);
				}
			}

			else {

				throw new Exception('Expand args must be an associative array.');
			}
		}

		/*  __  ___     __        __  ___          __     __
		   /  |/  /__  / /_____ _/  |/  /___  ____/ /__  / /
		  / /|_/ / _ \/ __/ __ `/ /|_/ / __ \/ __  / _ \/ /
		 / /  / /  __/ /_/ /_/ / /  / / /_/ / /_/ /  __/ /
		/_/  /_/\___/\__/\__,_/_/  /_/\____/\__,_/\___/_/
		                                                 */

		function getMeta($name, $default = '') {

			global $site;
			$dbh = $site->getDatabase();
			$ret = $default;

			try {
				$sql = "SELECT value FROM {$this->meta_table} WHERE {$this->meta_id} = :id AND name = :name";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id', $this->id);
				$stmt->bindValue(':name', $name);
				$stmt->execute();
				if ( $row = $stmt->fetch() ) {
					$ret = @unserialize($row->value);
					if ($ret === false) {
						$ret = $row->value;
					}
				}
			} catch (PDOException $e) {
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$this->singular_class_name}::" . __FUNCTION__ . ": {$e->getMessage()}", 'crood' );
			}
			return $ret;
		}

		function getMetas() {

			global $site;
			$dbh = $site->getDatabase();
			$ret = array();
			try {
				$sql = "SELECT name, value FROM {$this->meta_table} WHERE {$this->meta_id} = :id";
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

			} catch (PDOException $e) {
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$this->singular_class_name}::" . __FUNCTION__ . ": {$e->getMessage()}", 'crood' );
			}
			return (object) $ret;
		}

		function updateMeta($name, $value) {

			global $site;
			$dbh = $site->getDatabase();
			$ret = false;
			if ( is_array($value) || is_object($value) ) {
				$value = serialize($value);
			}
			try {
				$sql = "INSERT INTO {$this->meta_table} (id, {$this->meta_id}, value, name) VALUES (0, :meta_id, :value, :name) ON DUPLICATE KEY UPDATE value = :value";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':meta_id', $this->id);
				$stmt->bindValue(':value', $value);
				$stmt->bindValue(':name', $name);
				$stmt->execute();
				if ( $dbh->lastInsertId() ) {
					$ret = true;
				}
			} catch (PDOException $e) {
				log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$this->singular_class_name}::" . __FUNCTION__ . ": {$e->getMessage()}", 'crood' );
			}
			return $ret;
		}

		function updateMetas($metas) {

			global $site;
			$dbh = $site->getDatabase();
			$ret = false;

			if( $metas && is_array($metas) ) {

				foreach( $metas as $name => $value ) {

					$ret = $this->updateMeta($name, $value);
				}
				$ret = true;
			}

			return $ret;
		}
	}
?>