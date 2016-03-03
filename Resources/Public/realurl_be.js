;(function($) {
	// Alias selector
	$(document).ready(function() {
		$('#realurlSelectedAlias').on('change', function() {
			$('#realurlAliasSelectionForm').submit();
		});
	});

	// Confirmation box
	window.tx_realurl_confirm = function(title, message, url) {
		top.TYPO3.Modal.confirm(title, message).on('button.clicked', function(e) {
			if (e.target.name == 'ok') {
				document.location.href = url;
			}
			top.TYPO3.Modal.dismiss();
		});
		return false;
	}
})(TYPO3.jQuery || jQuery);
