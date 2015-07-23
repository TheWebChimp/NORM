<?php
	/**
	 * CROOD
	 *
	 * Provides the abstraction layer for the Single classes.
	 *
	 * @version  1.0
	 * @author   Rodrigo Tejero <rodrigo.tejero@thewebchi.mp> & Raul Vera <raul.vera@thewebchi.mp>
	 * @license  MIT
	 */
	class CROOD extends Model {

		protected $table;
		protected $table_fields;
		protected $update_fields;
		protected $singular_class_name;
		protected $plural_class_name;

		public $id;

		# MetaModel
		protected $meta_id;
		protected $meta_table;

		function init($args = false) {

			$this->id = 					0;

			$this->table = 					null;
			$this->table_fields = 			null;
			$this->update_fields = 			null;
			$this->singular_class_name = 	null;
			$this->plural_class_name = 		null;

			# MetaModel
			$this->meta_table = 			null;
			$this->meta_id = 				null;
		}

		# Create & Update
		function save() {

			global $site;
			$dbh = $site->getDatabase();
			$ret = false;

			$table_fields = $this->querify($this->table_fields);
			$bind_fields = $this->querify($this->table_fields, 'bind');
			$param_fields = $this->querify($this->update_fields, 'param');

			try {
				# Create or update user
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
				$sql = "DELETE FROM {$this->table} WHERE id = :id";

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
			}

			else {

				$ret = $fields;
			}

			return implode(', ', $ret);
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

					$ret[$meta->name] = $meta->value;
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

				try {
					$dbh->query('START TRANSACTION');

					$sql = "INSERT INTO {$this->meta_table} (id, {$this->meta_id}, value, name) VALUES (0, :meta_id, :value, :name) ON DUPLICATE KEY UPDATE value = :value";
					$stmt = $dbh->prepare($sql);

					foreach( $metas as $name => $value ) {

						if ( is_array($value) || is_object($value) ) {
							$value = serialize($value);
						}

						$stmt->bindValue(':meta_id', $this->id);
						$stmt->bindValue(':value', $value);
						$stmt->bindValue(':name', $name);
						$stmt->execute();
					}
					$dbh->query('COMMIT');
					$ret = true;

				} catch (PDOException $e) {
					log_to_file( "Database error: {$e->getCode()} (Line {$e->getLine()}) in {$this->singular_class_name}::" . __FUNCTION__ . ": {$e->getMessage()}.", 'crood' );
				}
			}

			return $ret;
		}
	}
?>