(function($) {
	$.fn.incrementFields = function(container) {
		console.log(container);
		return this.each(function() {
			$(this).find(':input').each(function() {
				var re = new RegExp('\\[' + container + '\\]\\[(\\d+)\\]'), matches;
				if (matches = re.exec(this.name)) {
					this.name = this.name.replace(re,'[' + container + '][' + (parseInt(matches[1],10)+1) + ']');
				}
			})
		});
	}
})(jQuery);

/**
 * @author Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
jQuery(function($) {
	$('.ahAddRelation').click(function() {
		$row = $(this).parents('tr').siblings(':last');
		$row.clone().incrementFields($(this).attr('rel')).insertAfter($row);
	})
})

