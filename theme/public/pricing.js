document.querySelectorAll('.pricing').forEach(function(pricing) {
	var units = [];

	pricing.querySelectorAll('.pricing-price').forEach(function(price) {
		var unit = price.dataset.unit;
		if(unit && !units.includes(unit)) units.push(unit);
	});

	if(units.length < 2) return;

	var list = pricing.querySelector('.pricing-list');
	var toggle = document.createElement('div');
	toggle.className = 'pricing-toggle';

	units.forEach(function(unit) {
		var caption = document.createElement('span');
		caption.textContent = unit;
		toggle.append(caption);

		caption.addEventListener('click', function() {
			select(unit, caption);
		});
	});

	if(list) list.before(toggle);
	select(units[0], toggle.firstElementChild);

	/**
	 * Displays the matching price in every package and updates its checkout input.
	 */
	function select(unit, caption) {
		Array.from(toggle.children).forEach(function(el) {
			el.classList.toggle('active', el === caption);
		});

		pricing.querySelectorAll('.pricing-item').forEach(function(item) {
			var prices = Array.from(item.querySelectorAll('.pricing-price'));
			var price = prices.find(function(el) {
				return el.dataset.unit === unit;
			}) || prices[0];

			prices.forEach(function(el) {
				el.hidden = el !== price;
			});

			var input = item.querySelector('input[name="price"]');
			if(input && price) input.value = price.dataset.priceid || '';
		});
	}
});
