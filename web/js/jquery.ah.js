/**
 * Code needed to clone nested relations forms
 * @plugin ahDoctrineEasyEmbeddedRelationsPlugin
 * @author Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
(function($) {

	/**
	 * Increments field IDs and names by one
	 */
	$.fn.incrementFields = function(container) {
		return this.each(function() {
			$(this).find(':input').each(function() { // find each input
				var re = new RegExp('\\[' + container + '\\]\\[(\\d+)\\]'), matches;
				if (matches = re.exec(this.name)) { // check if its name contains [container][number]
					// if so, increase the number
					this.name = this.name.replace(re,'[' + container + '][' + (parseInt(matches[1],10)+1) + ']');
				}
				$(this).trigger('change.ah'); // trigger onchange event just for a case
			}).end();

			// increase the number in first <th>
			$header = $(this).find('th').eq(0);
			if ($header.text().match(/^\d+$/)) {
				$header.text(parseInt($header.text(),10) + 1);
			}
			$(this).end();
		});
	}


})(jQuery);

jQuery(function($) {

	// when clicking the 'add relation' button
	$('.ahAddRelation').click(function() {

		// find last row of my siblings (each row represents a subform)
		$row = $(this).parents('tr').siblings(':last');

		// clone it, increment the fields and insert it below, additionally triggering events
		$row.clone()
			.incrementFields($(this).attr('rel'))
			.trigger('beforeadd.ah').insertAfter($row).trigger('afteradd.ah');

		//use events to further modify the cloned row like this
		// $(document).bind('beforeadd.ah', function(event) { $(event.target).hide() /* event.target is cloned row */ });
		// $(document).bind('afteradd.ah', function(event) { });
	})
})

