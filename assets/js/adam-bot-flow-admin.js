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
	function updateAction(row) {
		var select = row.querySelector('[data-flow-action-type]');
		if (!select) return;
		row.querySelectorAll('[data-flow-action-node],[data-flow-action-page],[data-flow-action-url]').forEach(function (field) {
			var visible = (select.value === 'node' && field.hasAttribute('data-flow-action-node')) || (select.value === 'page' && field.hasAttribute('data-flow-action-page')) || (select.value === 'url' && field.hasAttribute('data-flow-action-url'));
			field.hidden = !visible;
			field.disabled = !visible;
		});
	}
	var list = document.getElementById('adam-bot-flow-actions-list');
	var template = document.getElementById('adam-bot-flow-action-template');
	var add = document.querySelector('[data-flow-add-action]');
	if (list && template && add) {
		list.querySelectorAll('[data-flow-action]').forEach(updateAction);
		list.addEventListener('change', function (event) { if (event.target.matches('[data-flow-action-type]')) updateAction(event.target.closest('[data-flow-action]')); });
		add.addEventListener('click', function () { var index = Date.now().toString(); list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index)); });
		add.addEventListener('click', function () { updateAction(list.lastElementChild); });
		list.addEventListener('click', function (event) { var remove = event.target.closest('[data-flow-remove-action]'); if (remove) { var row = remove.closest('[data-flow-action]'); if (row) row.remove(); } });
	}
}());
