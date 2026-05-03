document.querySelectorAll('.pricing .pricing-toggle > span').forEach(function(el) {
	el.addEventListener('click', function() {
		var toggle = el.closest('.pricing-toggle');
		var alt = el === toggle.lastElementChild;

		if(toggle.classList.contains('alt') === alt) return;
		toggle.classList.toggle('alt');

		el.closest('.pricing').querySelectorAll('.pricing-item').forEach(function(item) {
			var suffix = alt ? 'Alternative' : '';

			['price', 'unit'].forEach(function(key) {
				var node = item.querySelector('.' + (key === 'price' ? 'amount' : key));
				if(node) node.textContent = item.dataset[key + suffix] || node.textContent;
			});

			var input = item.querySelector('input[name="priceid"]');
			if(input) input.value = item.dataset['priceid' + suffix] || input.value;
		});
	});
});
