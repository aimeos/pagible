(function() {
	const nav = document.querySelectorAll('main nav');
	const toc = document.querySelector('.sidebar .toc');

	if(nav && toc) {
		nav.forEach(function(el) {
			toc.appendChild(el.cloneNode(true));
		});
	}

	const open = document.querySelector('.open');
	const close = document.querySelector('.close');
	const sidebar = document.querySelector('.sidebar');
	const content = document.querySelector('.main-content');

	open.addEventListener('click', function() {
		open.classList.toggle('show');
		close.classList.toggle('show');
		sidebar.classList.toggle('show');
	});

	close.addEventListener('click', function() {
		sidebar.classList.toggle('show');
		close.classList.toggle('show');
		open.classList.toggle('show');
	});

	const fcn = function() {
		sidebar.classList.remove('show');
		content.classList.remove('show');
		close.classList.remove('show');
		open.classList.add('show');
	};

	toc.addEventListener('click', fcn);
	content.addEventListener('click', fcn);


	const search = document.querySelector('.sidebar .search');

	search.addEventListener('input', function(ev) {
		let regex = new RegExp( ev.target.value, 'i');

		toc.querySelectorAll('a').forEach(function(item) {
			if(regex.test(item.textContent)) {
				item.parentNode.classList.remove('hide');
			} else {
				item.parentNode.classList.add('hide');
			}
		});
	});
})();
