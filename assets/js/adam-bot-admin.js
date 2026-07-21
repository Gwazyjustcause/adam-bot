(function () {
	'use strict';

	var settings = window.adamBotKnowledgeAdmin || {};
	var blockList = document.getElementById('adam-bot-response-blocks');
	var template = document.getElementById('adam-bot-response-block-template');
	var draggedBlock = null;

	function reindexBlocks() {
		if (!blockList) return;
		blockList.querySelectorAll('.adam-bot-response-block').forEach(function (block, index) {
			block.querySelectorAll('[name]').forEach(function (field) {
				field.name = field.name.replace(/adam_bot_response_blocks\[\d+\]/, 'adam_bot_response_blocks[' + index + ']');
			});
			toggleBlockUrl(block);
		});
	}

	function toggleBlockUrl(block) {
		var type = block.querySelector('.adam-bot-block-type');
		var url = block.querySelector('.adam-bot-block-url');
		if (!type || !url) return;
		url.hidden = type.value !== 'button' && type.value !== 'link';
	}

	function bindBlock(block) {
		var remove = block.querySelector('.adam-bot-remove-block');
		var type = block.querySelector('.adam-bot-block-type');
		if (remove) {
			remove.addEventListener('click', function () {
				block.remove();
				reindexBlocks();
			});
		}
		if (type) type.addEventListener('change', function () { toggleBlockUrl(block); });
		block.addEventListener('dragstart', function () {
			draggedBlock = block;
			block.classList.add('is-dragging');
		});
		block.addEventListener('dragend', function () {
			block.classList.remove('is-dragging');
			draggedBlock = null;
			reindexBlocks();
		});
		block.addEventListener('dragover', function (event) {
			event.preventDefault();
			if (!draggedBlock || draggedBlock === block) return;
			var box = block.getBoundingClientRect();
			block.parentNode.insertBefore(draggedBlock, event.clientY < box.top + box.height / 2 ? block : block.nextSibling);
		});
		toggleBlockUrl(block);
	}

	if (blockList) {
		blockList.querySelectorAll('.adam-bot-response-block').forEach(bindBlock);
		var add = document.getElementById('adam-bot-add-block');
		if (add && template) {
			add.addEventListener('click', function () {
				var fragment = template.content.cloneNode(true);
				var block = fragment.querySelector('.adam-bot-response-block');
				blockList.appendChild(fragment);
				bindBlock(block);
				reindexBlocks();
				block.querySelector('textarea').focus();
			});
		}
		reindexBlocks();
	}

	document.querySelectorAll('.adam-bot-related-filter').forEach(function (input) {
		input.addEventListener('input', function () {
			var value = input.value.toLocaleLowerCase();
			var list = input.nextElementSibling;
			if (!list) return;
			list.querySelectorAll('label').forEach(function (label) {
				label.hidden = value !== '' && (label.dataset.search || '').indexOf(value) === -1;
			});
		});
	});

	document.querySelectorAll('.adam-bot-related-list').forEach(function (list) {
		var dragged = null;
		list.querySelectorAll('label').forEach(function (label) {
			label.addEventListener('dragstart', function () { dragged = label; label.classList.add('is-dragging'); });
			label.addEventListener('dragend', function () { label.classList.remove('is-dragging'); dragged = null; });
			label.addEventListener('dragover', function (event) {
				event.preventDefault();
				if (!dragged || dragged === label) return;
				var box = label.getBoundingClientRect();
				list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? label : label.nextSibling);
			});
		});
	});

	function post(action, data) {
		var body = new URLSearchParams(Object.assign({ action: action, nonce: settings.nonce || '' }, data));
		return fetch(settings.ajaxUrl || window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) { return response.json(); });
	}

	function addLine(parent, label, value) {
		var row = document.createElement('p');
		var strong = document.createElement('strong');
		strong.textContent = label + ': ';
		row.appendChild(strong);
		row.appendChild(document.createTextNode(value));
		parent.appendChild(row);
	}

	var previewButton = document.getElementById('adam-bot-run-preview');
	if (previewButton) {
		previewButton.addEventListener('click', function () {
			var question = document.getElementById('adam-bot-preview-question');
			var output = document.getElementById('adam-bot-preview-result');
			if (!question || !output) return;
			output.textContent = (settings.strings && settings.strings.testing) || 'Testing…';
			post('adam_bot_search_preview', { question: question.value }).then(function (payload) {
				output.textContent = '';
				if (!payload.success) throw new Error('preview');
				var data = payload.data || {};
				addLine(output, 'Matched entry', data.matched_title || '—');
				addLine(output, 'Matched keywords', (data.matched_keywords || []).join(', ') || '—');
				addLine(output, 'Confidence', String(data.confidence || 0) + '%');
				var heading = document.createElement('strong');
				heading.textContent = 'Response preview';
				var response = document.createElement('div');
				response.className = 'adam-bot-preview-response';
				String(data.response || '—').split(/\r?\n/).filter(Boolean).forEach(function (line) {
					var callout = line.match(/^\[!(warning|information|success)\]\s+(.+)$/i);
					var element = document.createElement('p');
					if (callout) {
						element.className = 'adam-bot-admin-callout adam-bot-admin-callout--' + callout[1].toLowerCase();
						element.textContent = callout[2];
					} else {
						element.textContent = line.replace(/^#{1,3}\s+/, '');
					}
					response.appendChild(element);
				});
				output.appendChild(heading);
				output.appendChild(response);
			}).catch(function () {
				output.textContent = (settings.strings && settings.strings.error) || 'The preview could not be loaded.';
			});
		});
	}

	var questionInput = document.getElementById('adam-bot-question');
	var duplicateOutput = document.getElementById('adam-bot-duplicate-result');
	var duplicateTimer;
	if (questionInput && duplicateOutput) {
		questionInput.addEventListener('input', function () {
			window.clearTimeout(duplicateTimer);
			duplicateTimer = window.setTimeout(function () {
				post('adam_bot_duplicate_check', { question: questionInput.value, post_id: settings.postId || 0 }).then(function (payload) {
					if (!payload.success) return;
					var matches = (payload.data && payload.data.matches) || [];
					duplicateOutput.textContent = '';
					var title = document.createElement('p');
					title.textContent = matches.length ? 'Possible duplicate detected' : 'No likely duplicates detected.';
					if (matches.length) title.className = 'adam-bot-duplicate-warning';
					duplicateOutput.appendChild(title);
					matches.forEach(function (match) {
						addLine(duplicateOutput, match.question || '', String(match.similarity || 0) + '% similar');
					});
				});
			}, 450);
		});
	}
}());
