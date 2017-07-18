<?php

	$table_name = '';
	$version = '';

	$singular_class_name = '';
	$singular_class_description = '';

	$plural_class_name = '';
	$plural_class_description = '';

	$author_name = '';
	$author_email = '';

	$meta_id = '';
	$meta_table = '';

	$table_fields = '';
	$update_fields = '';

	if($_POST) {

		//Grabbing the template

		$template = file_get_contents('template.norm');

		//Define the variables

		$singular_class_name = isset($_POST['singular_class_name']) ? $_POST['singular_class_name'] : '';
		$singular_class_description = isset($_POST['singular_class_description']) && $_POST['singular_class_description'] ? $_POST['singular_class_description'] : '';

		$plural_class_name = isset($_POST['plural_class_name']) ? $_POST['plural_class_name'] : '';
		$plural_class_description = isset($_POST['plural_class_description']) && $_POST['plural_class_description'] ? $_POST['plural_class_description'] : '';

		$author_name = isset($_POST['author_name']) ? $_POST['author_name'] : 'WebChimp';
		$author_email = isset($_POST['author_email']) ? $_POST['author_email'] : 'sistemas@thewebchi.mp';

		$meta_id = isset($_POST['meta_id']) ? $_POST['meta_id'] : '';
		$meta_table = isset($_POST['meta_table']) ? $_POST['meta_table'] : '';

		$has_meta = $meta_id && $meta_table;

		$version = isset($_POST['version']) ? $_POST['version'] : '';
		$table_name = isset($_POST['table_name']) ? strtolower($_POST['table_name']) : '';

		$table_fields = isset($_POST['table_fields']) ? $_POST['table_fields'] : '';
		$update_fields = isset($_POST['update_fields']) ? $_POST['update_fields'] : '';

		//Setup variables
		$fields = explode("\r\n", $table_fields);
		$fields_update = explode("\r\n", $update_fields);

		//Default values
		$default_values = '';

		foreach($fields as $field) {

			$field = trim($field);
			$default_values .= "public \${$field};\n\t\t";
		}

		$default_values = trim($default_values);

		//Table Fields
		$array_table_fields = array();
		foreach($fields as $field) {

			$array_table_fields[] = "'{$field}'";
		}

		$array_table_fields = implode(', ', $array_table_fields);

		//Update fields
		$array_update_fields = array();
		foreach($fields_update as $field) {

			$array_update_fields[] = "'{$field}'";
		}

		$array_update_fields = implode(', ', $array_update_fields);

		//Attributes Default Values
		$attr_default_values = '';

		foreach($fields as $field) {

			$field = trim($field);
			$value = $field == 'id' ? 0 : "''";
			$value = strpos($field, 'id_') !== false ? 0 : "''";

			if($field == 'created' || $field == 'modified')
				$attr_default_values .= "\$this->{$field} = \$now;\n\t\t\t\t";

			else
				$attr_default_values .= "\$this->{$field} = {$value};\n\t\t\t\t";
		}

		if($has_meta) $attr_default_values .= "\$this->metas = new stdClass();\n\t\t\t\t";

		$attr_default_values = trim($attr_default_values);

		//Replacing

		//Metas
		$meta_model;

		if($has_meta) {

			$meta_model = '';
			$meta_model .= "\n\n\t\t\t#metaModel\n";
			$meta_model .= "\t\t\t\$this->meta_id = \t\t\t\t'{$meta_id}';\n";
			$meta_model .= "\t\t\t\$this->meta_table = \t\t\t'{$meta_table}';";
		}

		$template = str_replace('%singular_class_name%', $singular_class_name, $template);
		$template = str_replace('%singular_class_description%', "\n\t * {$singular_class_description}\n\t *", $template);

		$template = str_replace('%plural_class_name%', $plural_class_name, $template);
		$template = str_replace('%plural_class_description%', "\n\t * {$plural_class_description}\n\t *", $template);

		$template = str_replace('%author_name%', $author_name, $template);
		$template = str_replace('%author_email%', $author_email, $template);

		$template = str_replace('%meta_model%', $meta_model, $template);

		$template = str_replace('%version%', $version, $template);
		$template = str_replace('%table_name%', $table_name, $template);

		$template = str_replace('%meta_id%', $meta_id, $template);
		$template = str_replace('%meta_table%', $meta_table, $template);

		$template = str_replace('%default_values%', $default_values, $template);
		$template = str_replace('%attr_default_values%', $attr_default_values, $template);

		$template = str_replace('%table_fields%', $array_table_fields, $template);
		$template = str_replace('%update_fields%', $array_update_fields, $template);
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>NORM | Generador de Modelos</title>

	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.3.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans:400,400italic,700,700italic,300,300italic|Oswald:400,300">

	<link rel="stylesheet" href="css/codemirror.css">
	<link rel="stylesheet" href="css/codemirror.monokai.css">

	<link rel="stylesheet" href="css/reset.css">
	<link rel="stylesheet" href="css/chimplate.css">
	<link rel="stylesheet" href="css/generador.css">
</head>
<body>

	<div class="generador-wrapper">
		<header class="generador-header">
			<div class="boxfix-vert">
				<div class="margins">
					<h2><i class="fa fa-fw fa-code"></i> NORM Generator</h2>
				</div>
			</div>
		</header>
		<section class="margins">

			<div class="row">
				<div class="col col-6">
					<div class="the-content">
						<h2>Generador de modelos NORM / CROOD</h2>
						<p>Estimado chimp, llenando el siguiente formulario podrás crear tu propio modelo.</p>
					</div>

					<form action="" method="post">

						<div class="form-group">
							<label for="inferer_lang" class="control-label">Inferir plurales</label>
							<select type="text" class="form-control input-block" name="inferer_lang">
								<option value="spa">En español</option>
								<option value="eng">En inglés</option>
							</select>
						</div>

						<h3>La Clase</h3>

						<div class="row">
							<div class="col col-8">
								<div class="form-group">
									<label for="table_name" class="control-label">Nombre de la tabla <span class="required">*</span></label>
									<input id="table_name" type="text" class="form-control input-block" name="table_name" value="<?php echo $table_name; ?>">
								</div>
							</div>

							<div class="col col-4">
								<div class="form-group">
									<label for="version" class="control-label">Versión <span class="required">*</span></label>
									<input id="version" type="text" class="form-control input-block" name="version" value="<?php echo $version; ?>">
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col col-6">
								<div class="form-group">
									<label for="singular_class_name" class="control-label">Nombre de Clase Singular <span class="required">*</span></label>
									<input id="singular_class_name" type="text" class="form-control input-block" name="singular_class_name" value="<?php echo $singular_class_name; ?>">
								</div>
							</div>

							<div class="col col-6">
								<div class="form-group">
									<label for="plural_class_name" class="control-label">Nombre de Clase Plural <span class="required">*</span></label>
									<input id="plural_class_name" type="text" class="form-control input-block" name="plural_class_name" value="<?php echo $plural_class_name; ?>">
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col col-6">
								<div class="form-group">
									<label for="singular_class_description" class="control-label">Descripción de Clase Singular <span class="required">*</span></label>
									<input id="singular_class_description" type="text" class="form-control input-block" name="singular_class_description" value="<?php echo $singular_class_description; ?>">
								</div>
							</div>

							<div class="col col-6">
								<div class="form-group">
									<label for="plural_class_description" class="control-label">Descripción de Clase Plural <span class="required">*</span></label>
									<input id="plural_class_description" type="text" class="form-control input-block" name="plural_class_description" value="<?php echo $plural_class_description; ?>">
								</div>
							</div>
						</div>

						<h3>El Autor</h3>
						<div class="row">
							<div class="col col-6">
								<div class="form-group">
									<label for="author_name" class="control-label">Nombre</label>
									<input id="author_name" type="text" class="form-control input-block" name="author_name" value="<?php echo $author_name; ?>">
								</div>
							</div>

							<div class="col col-6">
								<div class="form-group">
									<label for="author_email" class="control-label">Email</label>
									<input id="author_email" type="text" class="form-control input-block" name="author_email" value="<?php echo $author_email; ?>">
								</div>
							</div>
						</div>

						<h3>Metas</h3>
						<div class="row">
							<div class="col col-6">
								<div class="form-group">
									<label for="meta_id" class="control-label">Columna de ID <span class="required">*</span></label>
									<input id="meta_id" type="text" class="form-control input-block" name="meta_id" value="<?php echo $meta_id; ?>">
								</div>
							</div>

							<div class="col col-6">
								<div class="form-group">
									<label for="meta_table" class="control-label">Nombre de la Tabla <span class="required">*</span></label>
									<input id="meta_table" type="text" class="form-control input-block" name="meta_table" value="<?php echo $meta_table; ?>">
								</div>
							</div>
						</div>

						<h3>Campos</h3>
						<div class="form-group">
							<label for="table_fields" class="control-label">Campos de la tabla (uno por linea)</label>
							<textarea id="table_fields" type="text" class="form-control input-block" name="table_fields" rows="6"><?php echo $table_fields; ?></textarea>
						</div>

						<div class="form-group">
							<label for="update_fields" class="control-label">Campos de actualizacion (uno por linea)</label>
							<textarea id="update_fields" type="text" class="form-control input-block" name="update_fields" rows="6"><?php echo $update_fields; ?></textarea>
						</div>

						<div class="form-actions">
							<p class="text-right">
								<button type="reset" class="button button-link">Limpiar valores</button>
								<button type="submit" class="button button-primary">Generar</button>
							</p>
						</div>

					</form>

				</div>
				<div class="col col-6">
					<div class="form-group codemirror" data-mode="php" data-readonly="readonly">
						<textarea name="code" id="code"><?php if(isset($template)) echo $template; ?></textarea>
					</div>
				</div>
			</div>
		</section>
		<div class="generador-push"></div>
	</div>

	<footer class="generador-footer">
		<div class="boxfix-vert">
			<div class="margins">
				<div class="footer-copyright">
					<small class="cf">
						<span class="copyright-left">Copyright &copy; 2015 tetstets</span>
						<span class="copyright-right">Made for <strong>Hummingbird</strong> with <strong>Chimplate</strong></span>
					</small>
				</div>
			</div>
		</div>
	</footer>

	<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<script type="text/javascript" src="js/codemirror.min.js"></script>
	<script type="text/javascript" src="js/generador.js"></script>
</body>
</html>