(function () {
	'use strict';
	var type = document.querySelector('[data-flow-type]');
	function updateType() {
		if (!type) return;
		document.querySelectorAll('.flow-type-menu,.flow-type-dynamic,.flow-type-answer,.flow-type-redirect').forEach(function (el) { el.hidden = true; });
		var target = document.querySelectorAll('.flow-type-' + type.value);
		target.forEach(function (el) { el.hidden = false; });
	}
	if (type) { type.addEventListener('change', updateType); updateType(); }
	var list = document.getElementById('adam-bot-flow-actions-list');
	var template = document.getElementById('adam-bot-flow-action-template');
	var add = document.querySelector('[data-flow-add-action]');
	if (list && template && add) {
		add.addEventListener('click', function () { var index = Date.now().toString(); list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index)); });
		list.addEventListener('click', function (event) { var remove = event.target.closest('[data-flow-remove-action]'); if (remove) { var row = remove.closest('[data-flow-action]'); if (row) row.remove(); } });
	}
}());
