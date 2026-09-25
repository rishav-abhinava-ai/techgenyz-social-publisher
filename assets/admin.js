(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var modal = document.getElementById('aisp-modal');
		var postId = 0;
		var postUrl = '';
		var forceShare = false;
		var generationInFlight = false;
		var modalStateVersion = 0;
		var savedCaptions = {};
		var activeTab = 'facebook';
		var initializedEditors = {};
		var request = function (path, method, data) { return wp.apiFetch({ path: path, method: method, headers: { 'X-WP-Nonce': aispAdmin.nonce }, data: data }); };

		document.addEventListener('click', function (event) {
			var opener = event.target.closest('.aisp-open-modal');
			if (opener && modal) {
				event.preventDefault(); modalStateVersion += 1; postId = parseInt(opener.dataset.postId, 10); postUrl = opener.dataset.postUrl || ''; forceShare = opener.dataset.force === '1'; resetModal(opener); modal.hidden = false; document.body.classList.add('aisp-modal-open'); initializeEditor('facebook');
				loadCurrentStatuses(postId, modalStateVersion); modal.querySelector('.aisp-generate').focus(); return;
			}
			if (event.target.closest('[data-aisp-close]') && modal) { closeModal(); }
		});
		if (modal) {
			modal.querySelector('.aisp-generate').addEventListener('click', function () { generatePlatforms(selected(), null); });
			modal.querySelectorAll('.aisp-regenerate').forEach(function (button) { button.addEventListener('click', function () { generatePlatforms([button.dataset.regenerate], button); }); });
			modal.querySelectorAll('[data-aisp-tab]').forEach(function (button) { button.addEventListener('click', function () { showTab(button.dataset.tgspTab); }); });
			modal.querySelectorAll('[data-caption-editor] textarea').forEach(function (field) { field.addEventListener('input', function () { markManualEdit(field.closest('[data-platform]').dataset.platform); }); });
			modal.querySelector('.aisp-publish').addEventListener('click', publish);
		}

		function selected() { return Array.prototype.map.call(modal.querySelectorAll('.aisp-platform:checked'), function (item) { return item.value; }); }
		function editorId(platform) { var panel = modal.querySelector('[data-platform="' + platform + '"]'); return panel ? panel.dataset.editorId : ''; }
		function editorInstance(platform) { var id = editorId(platform); return id && window.tinymce ? window.tinymce.get(id) : null; }
		function captionField(platform) { var id = editorId(platform); return id ? document.getElementById(id) : null; }
		function htmlToPlainText(value) {
			var container = document.createElement('div'); container.innerHTML = String(value || '');
			container.querySelectorAll('[data-mce-type="bookmark"], .mce_SELRES_start, .mce_SELRES_end').forEach(function (node) { node.remove(); });
			container.querySelectorAll('ol').forEach(function (list) { Array.prototype.forEach.call(list.children, function (node, index) { if (node.tagName === 'LI') { node.insertBefore(document.createTextNode((index + 1) + '. '), node.firstChild); } }); });
			container.querySelectorAll('ul').forEach(function (list) { Array.prototype.forEach.call(list.children, function (node) { if (node.tagName === 'LI') { node.insertBefore(document.createTextNode('• '), node.firstChild); } }); });
			container.querySelectorAll('br').forEach(function (node) { node.replaceWith(document.createTextNode('\n')); });
			container.querySelectorAll('p,div,li,h1,h2,h3,h4,h5,h6,blockquote,ul,ol').forEach(function (node) { node.appendChild(document.createTextNode('\n')); });
			return String(container.textContent || '').replace(/\u00a0/g, ' ').replace(/\r\n?/g, '\n').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
		}
		function plainTextToHtml(value) { return escapeHtml(String(value || '')).replace(/\r\n?|\n/g, '<br>'); }
		function getCaption(platform) { var editor = editorInstance(platform), field = captionField(platform), value = ''; if (editor && !editor.isHidden()) { editor.save(); value = editor.getContent({ format: 'html' }); } else if (field) { value = field.value; } return htmlToPlainText(value); }
		function setCaption(platform, value) { var editor = editorInstance(platform), field = captionField(platform), text = String(value || ''); if (editor) { editor.setContent(plainTextToHtml(text)); editor.save(); } else if (field) { field.value = text; } }
		function initializeEditor(platform) {
			var id = editorId(platform), field = captionField(platform);
			if (!id || !field || initializedEditors[id] || !window.wp || !wp.editor || typeof wp.editor.initialize !== 'function') { return; }
			wp.editor.initialize(id, {
				tinymce: {
					wpautop: true,
					toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo,wp_adv',
					toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,wp_help',
					plugins: 'charmap,colorpicker,hr,lists,paste,tabfocus,textcolor,wordpress,wpautoresize,wplink,wpdialogs',
					setup: function (editor) { editor.on('input change keyup Undo Redo SetContent', function () { markManualEdit(platform); }); }
				},
				quicktags: true,
				mediaButtons: false
			});
			initializedEditors[id] = true;
		}
		function removeEditors() {
			['facebook', 'x', 'linkedin'].forEach(function (platform) { var id = editorId(platform); if (id && initializedEditors[id] && window.wp && wp.editor && typeof wp.editor.remove === 'function') { wp.editor.remove(id); delete initializedEditors[id]; } });
		}
		function showTab(platform) {
			activeTab = platform || 'facebook';
			modal.querySelectorAll('[data-aisp-tab]').forEach(function (button) { var active = button.dataset.tgspTab === activeTab; button.classList.toggle('nav-tab-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); });
			modal.querySelectorAll('.aisp-tab-panel').forEach(function (panel) { panel.hidden = panel.dataset.platform !== activeTab; });
			window.setTimeout(function () { initializeEditor(activeTab); var editor = editorInstance(activeTab); if (editor) { editor.show(); editor.execCommand('mceRepaint'); } updateCounter(activeTab); }, 0);
		}
		function resetModal(opener) {
			removeEditors();
			savedCaptions = {};
			modal.querySelector('.aisp-modal__article').textContent = opener.dataset.postTitle || '';
			['facebook', 'x', 'linkedin'].forEach(function (platform) { setCaption(platform, ''); });
			modal.querySelectorAll('.aisp-ai-indicator').forEach(function (indicator) { indicator.hidden = true; });
			modal.querySelectorAll('.aisp-platform').forEach(function (checkbox) { checkbox.checked = checkbox.defaultChecked; });
			var box = modal.querySelector('.aisp-modal__status'); box.className = 'aisp-modal__status'; box.textContent = '';
			updatePublishButton();
			setBusy(false); setGenerationBusy(false); showTab('facebook'); updateAllCounters();
		}
		function closeModal() { modalStateVersion += 1; removeEditors(); modal.hidden = true; setBusy(false); setGenerationBusy(false); document.body.classList.remove('aisp-modal-open'); }
		function setBusy(busy) { modal.querySelector('.aisp-generate').disabled = busy; modal.querySelectorAll('.aisp-regenerate').forEach(function (button) { button.disabled = busy; }); modal.querySelector('.aisp-publish').disabled = busy; }
		function setGenerationBusy(busy, trigger) { generationInFlight = busy; modal.querySelector('.aisp-generate').disabled = busy; modal.querySelectorAll('.aisp-regenerate').forEach(function (button) { button.disabled = busy; }); modal.querySelector('.aisp-publish').disabled = busy; if (trigger) { trigger.classList.toggle('is-loading', busy); } }
		function status(value, isError) { var box = modal.querySelector('.aisp-modal__status'); box.className = 'aisp-modal__status ' + (isError ? 'is-error' : 'is-success'); box.textContent = value; }
		function isCurrentRequest(requestPostId, requestVersion) { return !modal.hidden && postId === requestPostId && modalStateVersion === requestVersion; }
		function loadCurrentStatuses(requestPostId, requestVersion) {
			request(aispAdmin.paths.statuses + encodeURIComponent(requestPostId), 'GET').then(function (response) { if (!isCurrentRequest(requestPostId, requestVersion)) { return; } setSavedCaptions(response.saved_captions || {}); populateSavedCaptions(); if (!Object.keys(response.results || {}).length) { return; } updateForceFromResults(response.results); renderResults(response.results, aispAdmin.labels.done); pollProcessing(response.results, requestPostId, requestVersion); }).catch(function () {});
		}
		function setSavedCaptions(captions) { savedCaptions = {}; ['facebook', 'linkedin', 'x'].forEach(function (platform) { var value = typeof captions[platform] === 'string' ? captions[platform].trim() : ''; if (value) { savedCaptions[platform] = value; } }); }
		function populateSavedCaptions() { ['facebook', 'x', 'linkedin'].forEach(function (platform) { setCaption(platform, savedCaptions[platform] || ''); var indicator = modal.querySelector('[data-ai-indicator="' + platform + '"]'); if (indicator) { indicator.hidden = true; } }); updateAllCounters(); }
		function updatePublishButton() { if (modal) { modal.querySelector('.aisp-publish').textContent = forceShare ? aispAdmin.labels.shareAgain : aispAdmin.labels.shareNow; } }
		function updateForceFromResults(results) {
			var keys = Object.keys(results || {}); if (!keys.length) { return; }
			forceShare = keys.every(function (key) { return ['published', 'success', 'sent', 'accepted'].indexOf(results[key].status) !== -1; }); updatePublishButton();
		}
		function platformLabel(platform) { return platform === 'x' ? 'X' : platform.charAt(0).toUpperCase() + platform.slice(1); }
		function formatLabel(template, value) { return String(template || '').replace('%s', value); }
		function textLength(value) { return Array.from(String(value || '')).length; }
		function markManualEdit(platform) { var indicator = modal.querySelector('[data-ai-indicator="' + platform + '"]'); if (indicator) { indicator.hidden = true; } updateCounter(platform); }
		function xTextWithUrl(value) { value = String(value || '').trim(); if (postUrl && value.indexOf(postUrl) === -1) { value += (value ? '\n\n' : '') + postUrl; } return value; }
		function xWeightedLength(value) { var source = xTextWithUrl(value), pattern = /https?:\/\/[^\s<>"']+/gi, total = 0, index = 0, match; while ((match = pattern.exec(source)) !== null) { total += textLength(source.slice(index, match.index)) + 23; index = match.index + match[0].length; } return total + textLength(source.slice(index)); }
		function updateCounter(platform) { var output = modal.querySelector('[data-caption-count="' + platform + '"]'), value = getCaption(platform); if (!output) { return; } var length = platform === 'x' ? xWeightedLength(value) : textLength(value); output.textContent = length + ' ' + (platform === 'x' ? aispAdmin.labels.weightedCharacters : aispAdmin.labels.characters) + (platform === 'x' ? ' / 280' : ''); output.classList.toggle('is-over-limit', platform === 'x' && length > 280); }
		function updateAllCounters() { ['facebook', 'linkedin', 'x'].forEach(updateCounter); }
		function renderResults(results, message) {
			var html = '<p><strong>' + escapeHtml(message || aispAdmin.labels.done) + '</strong></p><ul>'; Object.keys(results || {}).forEach(function (key) { var item = results[key]; html += '<li><strong>' + escapeHtml(key === 'x' ? 'X' : key.charAt(0).toUpperCase() + key.slice(1)) + ':</strong> ' + escapeHtml(item.status === 'published' ? 'Published ✓' : item.status === 'failed' ? 'Failed ✕' : 'Processing...') + '<br><small>' + escapeHtml(item.message || '') + '</small></li>'; }); modal.querySelector('.aisp-modal__status').className = 'aisp-modal__status'; modal.querySelector('.aisp-modal__status').innerHTML = html + '</ul>';
		}
		function pollProcessing(results, requestPostId, requestVersion) {
			var attempts = 0;
			function processingAttempts() { var values = {}; Object.keys(results || {}).forEach(function (platform) { var item = results[platform]; if (item && item.status === 'processing' && item.remote_id) { values[platform] = item.remote_id; } }); return values; }
			function poll() {
				if (!isCurrentRequest(requestPostId, requestVersion)) { return; } var active = processingAttempts(); if (!Object.keys(active).length) { return; }
				if (attempts >= 5) { Object.keys(active).forEach(function (platform) { results[platform].message = aispAdmin.labels.unconfirmed; }); renderResults(results, aispAdmin.labels.done); return; }
				attempts += 1; request(aispAdmin.paths.reconcile + encodeURIComponent(requestPostId), 'POST', { attempts: active }).then(function (response) {
					if (!isCurrentRequest(requestPostId, requestVersion)) { return; } Object.keys(response.results || {}).forEach(function (platform) { results[platform] = response.results[platform]; }); renderResults(results, aispAdmin.labels.done); if (!response.rate_limited && Object.keys(processingAttempts()).length) { window.setTimeout(poll, 2000); }
				}).catch(function (error) { if (!isCurrentRequest(requestPostId, requestVersion)) { return; } Object.keys(active).forEach(function (platform) { results[platform].message = error && error.message ? error.message : aispAdmin.labels.unconfirmed; }); renderResults(results, aispAdmin.labels.done); if (attempts < 5) { window.setTimeout(poll, 2000); } });
			}
			if (Object.keys(processingAttempts()).length) { window.setTimeout(poll, 2000); }
		}
		function generatePlatforms(platforms, trigger) {
			var requestPostId = postId, requestVersion = modalStateVersion, single = platforms.length === 1 ? platforms[0] : ''; if (!platforms.length) { status(aispAdmin.labels.select, true); return; } if (generationInFlight) { return; } setGenerationBusy(true, trigger); status(single ? formatLabel(aispAdmin.labels.regenerating, platformLabel(single)) : aispAdmin.labels.generating, false);
			request(aispAdmin.paths.generate + encodeURIComponent(requestPostId), 'POST', { platforms: platforms }).then(function (response) {
				if (!isCurrentRequest(requestPostId, requestVersion)) { return; }
				Object.keys(response.captions || {}).forEach(function (key) { var indicator = modal.querySelector('[data-ai-indicator="' + key + '"]'); setCaption(key, response.captions[key]); savedCaptions[key] = response.captions[key]; updateCounter(key); if (indicator) { indicator.hidden = false; } }); status(single ? formatLabel(aispAdmin.labels.regenerated, platformLabel(single)) : aispAdmin.labels.generated, false);
			}).catch(function (error) { if (isCurrentRequest(requestPostId, requestVersion)) { status(error && error.message ? error.message : aispAdmin.labels.failed, true); } }).finally(function () { if (isCurrentRequest(requestPostId, requestVersion)) { setGenerationBusy(false, trigger); } });
		}
		function publish() {
			var platforms = selected(), captions = {}, missing = false, requestPostId = postId, requestVersion = modalStateVersion; if (!platforms.length) { status(aispAdmin.labels.select, true); return; }
			platforms.forEach(function (key) { var value = getCaption(key).trim(); if (!value) { missing = true; } captions[key] = value; }); if (missing) { status(aispAdmin.labels.caption, true); return; } if (platforms.indexOf('x') !== -1 && xWeightedLength(captions.x) > 280) { status(aispAdmin.labels.xTooLong, true); return; }
			if (forceShare && !window.confirm(aispAdmin.labels.shareAgainConfirm)) { return; }
			setBusy(true); status(aispAdmin.labels.publishing, false);
			request(aispAdmin.paths.share + encodeURIComponent(requestPostId), 'POST', { platforms: platforms, captions: captions, force: forceShare }).then(function (response) {
				if (!isCurrentRequest(requestPostId, requestVersion)) { return; }
				platforms.forEach(function (platform) { savedCaptions[platform] = captions[platform]; });
				updateForceFromResults(response.results || {}); renderResults(response.results || {}, response.message); pollProcessing(response.results || {}, requestPostId, requestVersion);
			}).catch(function (error) { if (isCurrentRequest(requestPostId, requestVersion)) { status(error && error.message ? error.message : aispAdmin.labels.failed, true); } }).finally(function () { if (isCurrentRequest(requestPostId, requestVersion)) { setBusy(false); } });
		}
		function escapeHtml(value) { var element = document.createElement('div'); element.textContent = String(value); return element.innerHTML; }

		bindSettingsButton('aisp-test-webhook', aispAdmin.paths.testWebhook, 'POST');
		bindSettingsButton('aisp-test-buffer', aispAdmin.paths.testBuffer, 'POST');
		function bindSettingsButton(id, path, method) { var button = document.getElementById(id); if (!button) { return; } var output = document.getElementById(id + '-result'); button.addEventListener('click', function () { button.disabled = true; output.textContent = aispAdmin.labels.testing; request(path, method).then(function (response) { var count = response.data && response.data.channels ? response.data.channels.length : ''; output.textContent = response.message || ('Connected. ' + count + ' channels found.'); if (response.data && response.data.channels) { populateChannels(response.data); } }).catch(function (error) { output.textContent = error && error.message ? error.message : aispAdmin.labels.failed; }).finally(function () { button.disabled = false; }); }); }
		function populateChannels(data) { ['facebook', 'linkedin', 'x'].forEach(function (platform) { var select = document.getElementById('aisp_buffer_channel_' + platform); if (!select) { return; } var expected = platform === 'x' ? 'twitter' : platform, current = select.value; select.innerHTML = '<option value="">Select a channel</option>'; data.channels.filter(function (channel) { return channel.service === expected && !channel.isDisconnected && !channel.isLocked; }).forEach(function (channel) { var option = document.createElement('option'); option.value = channel.id; option.textContent = (channel.displayName || channel.name) + ' — ' + channel.descriptor; option.selected = channel.id === current; select.appendChild(option); }); }); var org = document.getElementById('aisp_buffer_organization_id'); if (org && !org.value) { org.value = data.organization_id || ''; } }
	});
}());
