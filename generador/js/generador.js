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
});