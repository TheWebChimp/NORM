jQuery(document).ready(function($) {

	$('.codemirror').each(function() {
		var el = $(this),
			mode = el.data('mode'),
			readOnly = el.data('readonly') || false,
			textarea = el.find('textarea');
		var editor = CodeMirror.fromTextArea(textarea[0], {
			styleActiveLine: true,
			autoCloseBrackets: true,
			matchTags: { bothTags: true },
			matchBrackets: true,
			lineWrapping: true,
			readOnly: readOnly,
			lineNumbers: true,
			theme: 'monokai',
			mode: mode
		});
		el.data('editor', editor);
	});

	function dashToUnderscore(str) {
		return str.replace('-', '_');
	}

	function toSeparateWords(str) {
		return str.replace(/[\-_]+/g, ' ');
	}

	function toTitleCase(str) {
		return str.replace(/\w\S*/g, function(txt){
			return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
		});
	}

	function trimAll(str) {
		return str.replace(/\s/g, '');
	}

	function inferPlural(str, lang) {
		switch (lang) {
			case 'spa':
				if ( str.match(/[aeiou]$/ig) ) {
					// If it ends with a vowel, usually 's' is appended
					str = str + 's';
				} else {
					// In spanish 'z' becomes 'c' and 's' usually vanishes if they're at the end
					str = str.replace(/z$/ig, 'c');
					str = str.replace(/s$/ig, '');
					// Append 'es'
					str = str + 'es';
				}
			break;
			case 'eng':
				if ( str.match(/(sh|ch|[sxz])$/ig) ) {
					// If it ends with 'sh', 'ch', 's', 'x' or 'z' we append 'es'
					str = str + 'es';
				} else if ( str.match(/o$/ig) ) {
					// If it ends with 'o' we append 'es'
					str = str + 'es';
				} else if ( str.match(/[^aeiou]y$/ig) ) {
					// If it ends with consonant + 'y' we change the 'y' for an 'i' and append 'es'
					str = str.replace(/y$/ig, 'ies');
				} else {
					// Else we append 's'
					str = str + 's';
				}
			break;
		}
		return str;
	}

	$('[name=table_name]').on('change', function() {
		var el = $(this),
			val = el.val(),
			lang = $('[name=inferer_lang]').val(),
			singularClassName = $('[name=singular_class_name]'),
			pluralClassName = $('[name=plural_class_name]'),
			singularClassDescription = $('[name=singular_class_description]'),
			pluralClassDescription = $('[name=plural_class_description]'),
			metaID = $('[name=meta_id]')
			metaTable = $('[name=meta_table]');
		// Normalize dashes to underscores (snake_case)
		val = dashToUnderscore(val);
		// Set meta values
		if (! metaID.val() ) metaID.val( 'id_' + val );
		if (! metaTable.val() ) metaTable.val( val + '_meta' );
		// Separate words and title case them
		val = toSeparateWords(val);
		val = toTitleCase(val);
		// Update fields
		if (! singularClassName.val() ) singularClassName.val( trimAll(val) );
		if (! pluralClassName.val() ) pluralClassName.val( trimAll(inferPlural(val, lang)) );
		if (! singularClassDescription.val() ) singularClassDescription.val( val );
		if (! pluralClassDescription.val() ) pluralClassDescription.val( inferPlural(val, lang) );
	});

	$('[name=table_fields]').on('change', function() {
		var el = $(this),
			val = el.val(),
			updateFields = $('[name=update_fields]');
		// Remove common create-only columns
		val = val.replace(/^id\s/gm, '');
		val = val.replace(/^created\s/gm, '');
		// Update field
		updateFields.val( val );
	});

	$('.js-reset-fields').on('click', function(e) {
		e.preventDefault();
		$('[name=table_name]').val('');
		$('[name=singular_class_name]').val('');
		$('[name=plural_class_name]').val('');
		$('[name=singular_class_description]').val('');
		$('[name=plural_class_description]').val('');
		$('[name=meta_id]').val('');
		$('[name=meta_table]').val('');
		$('[name=table_fields]').val('');
		$('[name=update_fields]').val('');
	});
});