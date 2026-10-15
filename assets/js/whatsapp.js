
(function () {
	'use strict';

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback, { once: true });
		} else {
			callback();
		}
	}

	onReady(function () {
		if (
			typeof window.UWCP_DATA !== 'object' ||
			document.getElementById('uwcp-root') ||
			!document.body
		) {
			return;
		}

		var data = window.UWCP_DATA;
		var contacts = Array.isArray(data.contacts) ? data.contacts : [];
		var texts = data.texts || {};
		var supported = Object.keys(texts);

		if (!contacts.length || !supported.length) {
			return;
		}

		function languageFromUrl() {
			var params = new URLSearchParams(window.location.search);
			var queryLanguage = (params.get('lang') || '').toLowerCase();

			if (supported.indexOf(queryLanguage) !== -1) {
				return queryLanguage;
			}

			var pathMatch = window.location.pathname.match(/\/(ar|en|ru|fr)\/?$/i);
			return pathMatch ? pathMatch[1].toLowerCase() : '';
		}

		function languageFromHtml() {
			return (document.documentElement.lang || '').toLowerCase().split('-')[0];
		}

		function languageFromStorage() {
			try {
				return (localStorage.getItem(data.storageKey || 'site_language') || '').toLowerCase();
			} catch (error) {
				return '';
			}
		}

		function detectLanguage() {
			var source = data.languageSource || 'auto';
			var candidates = [];

			if (source === 'url') candidates = [languageFromUrl()];
			else if (source === 'html') candidates = [languageFromHtml()];
			else if (source === 'storage') candidates = [languageFromStorage()];
			else candidates = [languageFromUrl(), languageFromHtml(), languageFromStorage()];

			for (var i = 0; i < candidates.length; i += 1) {
				if (supported.indexOf(candidates[i]) !== -1) {
					return candidates[i];
				}
			}

			return supported.indexOf(data.defaultLanguage) !== -1
				? data.defaultLanguage
				: supported[0];
		}

		function initials(name) {
			var parts = String(name || 'W').trim().split(/\s+/).filter(Boolean);
			if (!parts.length) return 'W';
			if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
			return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
		}

		function whatsappSvg() {
			return '<svg viewBox="0 0 32 32" aria-hidden="true">' +
				'<path d="M16.04 3.2c-7.06 0-12.8 5.72-12.8 12.77 0 2.25.59 4.45 1.71 6.39L3.13 29l6.81-1.78a12.76 12.76 0 0 0 6.09 1.55h.01c7.05 0 12.79-5.73 12.79-12.78 0-3.41-1.33-6.62-3.75-9.03A12.7 12.7 0 0 0 16.04 3.2zm0 23.41h-.01a10.59 10.59 0 0 1-5.39-1.47l-.39-.23-4.05 1.06 1.08-3.94-.25-.4a10.58 10.58 0 0 1-1.62-5.66c0-5.85 4.77-10.61 10.63-10.61 2.84 0 5.51 1.1 7.51 3.11a10.54 10.54 0 0 1 3.12 7.51c0 5.86-4.78 10.63-10.63 10.63zm5.82-7.95c-.32-.16-1.89-.93-2.18-1.04-.29-.11-.5-.16-.72.16-.21.32-.82 1.04-1.01 1.25-.19.21-.37.24-.69.08-.32-.16-1.35-.5-2.57-1.59-.95-.85-1.59-1.89-1.78-2.21-.19-.32-.02-.49.14-.65.14-.14.32-.37.48-.56.16-.19.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.72-1.73-.98-2.37-.26-.62-.53-.54-.72-.55h-.61c-.21 0-.56.08-.85.4-.29.32-1.12 1.09-1.12 2.66s1.15 3.09 1.31 3.3c.16.21 2.26 3.45 5.48 4.84.77.33 1.36.53 1.83.68.77.24 1.47.21 2.03.13.62-.09 1.89-.77 2.15-1.51.27-.74.27-1.38.19-1.51-.08-.13-.29-.21-.61-.37z"/>' +
				'</svg>';
		}

		var language = detectLanguage();
		var copy = texts[language] || texts[supported[0]];
		var selectedContact = null;
		var isMobile = window.matchMedia('(max-width: 767px)').matches;

		var root = document.createElement('div');
		root.id = 'uwcp-root';
		root.style.setProperty('--uwcp-primary', data.primaryColor);
		root.style.setProperty('--uwcp-secondary', data.secondaryColor);
		root.style.setProperty('--uwcp-icon', data.iconColor);
		root.style.setProperty('--uwcp-ripple', data.rippleColor);
		root.style.setProperty('--uwcp-modal-bg', data.modalBgColor);
		root.style.setProperty('--uwcp-text', data.modalTextColor);
		root.style.setProperty('--uwcp-muted', data.mutedTextColor);
		root.style.setProperty('--uwcp-logo-bg', data.logoBackground);
		root.style.setProperty('--uwcp-modal-width', Math.max(320, Number(data.modalWidth) || 500) + 'px');
		root.style.setProperty('--uwcp-modal-radius', Math.max(0, Number(data.modalRadius) || 0) + 'px');
		root.style.setProperty('--uwcp-logo-width', Math.max(80, Number(data.logoWidth) || 220) + 'px');

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'uwcp-floating-button';
		if (data.floatAnimation) button.classList.add('uwcp-float');
		if (data.rippleAnimation) button.classList.add('uwcp-ripple');
		button.setAttribute('aria-label', 'WhatsApp');
		button.setAttribute('data-no-language', '1');
		button.innerHTML =
			'<span class="uwcp-ring uwcp-ring-one"></span>' +
			'<span class="uwcp-ring uwcp-ring-two"></span>' +
			whatsappSvg();

		var overlay = document.createElement('div');
		overlay.className = 'uwcp-overlay';
		overlay.setAttribute('aria-hidden', 'true');

		var modal = document.createElement('div');
		modal.className = 'uwcp-modal';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');

		var header = document.createElement('div');
		header.className = 'uwcp-header';

		var closeButton = document.createElement('button');
		closeButton.type = 'button';
		closeButton.className = 'uwcp-close';
		closeButton.textContent = '×';

		var logo = null;
		if (data.showLogo && data.logoUrl) {
			logo = document.createElement('img');
			logo.className = 'uwcp-brand-logo';
			logo.src = data.logoUrl;
			logo.alt = data.brandName || '';
			header.appendChild(logo);
		}

		var brand = document.createElement('div');
		brand.className = 'uwcp-brand-name';
		brand.textContent = data.brandName || '';

		var title = document.createElement('h2');
		title.className = 'uwcp-title';

		var subtitle = document.createElement('p');
		subtitle.className = 'uwcp-subtitle';

		header.insertBefore(closeButton, header.firstChild);
		header.appendChild(brand);
		header.appendChild(title);
		header.appendChild(subtitle);

		var body = document.createElement('div');
		body.className = 'uwcp-body';

		var chooseTitle = document.createElement('h3');
		chooseTitle.className = 'uwcp-section-title';

		var contactList = document.createElement('div');
		contactList.className = 'uwcp-contact-list';

		var messageWrap = document.createElement('div');
		messageWrap.className = 'uwcp-message-wrap';

		var label = document.createElement('label');
		label.className = 'uwcp-label';
		label.setAttribute('for', 'uwcp-message');

		var message = document.createElement('textarea');
		message.id = 'uwcp-message';
		message.className = 'uwcp-message';

		var errorBox = document.createElement('div');
		errorBox.className = 'uwcp-error';

		messageWrap.appendChild(label);
		messageWrap.appendChild(message);
		messageWrap.appendChild(errorBox);

		var actions = document.createElement('div');
		actions.className = 'uwcp-actions';

		var cancelButton = document.createElement('button');
		cancelButton.type = 'button';
		cancelButton.className = 'uwcp-cancel';

		var sendButton = document.createElement('button');
		sendButton.type = 'button';
		sendButton.className = 'uwcp-send';
		sendButton.disabled = true;

		actions.appendChild(cancelButton);
		actions.appendChild(sendButton);

		body.appendChild(chooseTitle);
		body.appendChild(contactList);
		body.appendChild(messageWrap);
		body.appendChild(actions);

		modal.appendChild(header);
		modal.appendChild(body);
		overlay.appendChild(modal);
		root.appendChild(button);
		root.appendChild(overlay);
		document.body.appendChild(root);

		function applyButtonLayout() {
			isMobile = window.matchMedia('(max-width: 767px)').matches;

			var size = isMobile ? data.buttonMobile : data.buttonDesktop;
			var bottom = isMobile ? data.bottomMobile : data.bottomDesktop;
			var side = isMobile ? data.sideMobile : data.sideDesktop;
			var visible = isMobile ? data.showMobile : data.showDesktop;

			button.style.setProperty('--uwcp-button-size', Math.max(44, Number(size) || 58) + 'px');
			button.style.bottom = Math.max(0, Number(bottom) || 0) + 'px';
			button.style.left = '';
			button.style.right = '';
			button.style[data.position === 'left' ? 'left' : 'right'] = Math.max(0, Number(side) || 0) + 'px';
			button.style.display = visible ? 'flex' : 'none';
		}

		function applyLanguage(nextLanguage) {
			language = supported.indexOf(nextLanguage) !== -1 ? nextLanguage : data.defaultLanguage;
			copy = texts[language] || texts[supported[0]];

			overlay.setAttribute('dir', language === 'ar' ? 'rtl' : 'ltr');
			title.textContent = copy.title;
			subtitle.textContent = copy.subtitle;
			chooseTitle.textContent = copy.choose;
			label.textContent = copy.message_label;
			message.placeholder = copy.placeholder;
			sendButton.textContent = copy.send;
			cancelButton.textContent = copy.cancel;
			closeButton.setAttribute('aria-label', copy.close);

			renderContacts();
		}

		function renderContacts() {
			contactList.innerHTML = '';

			contacts.forEach(function (contact) {
				var card = document.createElement('button');
				card.type = 'button';
				card.className = 'uwcp-contact-card';

				if (selectedContact && selectedContact.id === contact.id) {
					card.classList.add('uwcp-selected');
				}

				var avatar = document.createElement('span');
				avatar.className = 'uwcp-avatar';
				avatar.textContent = initials(contact.name);

				var content = document.createElement('span');
				content.className = 'uwcp-contact-content';

				var name = document.createElement('span');
				name.className = 'uwcp-contact-name';
				name.textContent = contact.name;

				var role = document.createElement('span');
				role.className = 'uwcp-contact-role';
				role.textContent = contact.roles && contact.roles[language]
					? contact.roles[language]
					: '';

				var check = document.createElement('span');
				check.className = 'uwcp-check';
				check.textContent = '✓';

				content.appendChild(name);
				content.appendChild(role);
				card.appendChild(avatar);
				card.appendChild(content);
				card.appendChild(check);

				card.addEventListener('click', function () {
					selectedContact = contact;
					sendButton.disabled = false;
					messageWrap.classList.add('uwcp-visible');
					errorBox.classList.remove('uwcp-visible');
					renderContacts();

					if (!message.value.trim()) {
						message.value = copy.placeholder;
					}

					window.setTimeout(function () {
						message.focus();
						message.setSelectionRange(message.value.length, message.value.length);
					}, 80);
				});

				contactList.appendChild(card);
			});
		}

		function openModal() {
			overlay.classList.add('uwcp-open');
			overlay.setAttribute('aria-hidden', 'false');
			document.body.classList.add('uwcp-no-scroll');

			if (contacts.length === 1 && !selectedContact) {
				selectedContact = contacts[0];
				sendButton.disabled = false;
				messageWrap.classList.add('uwcp-visible');

				if (!message.value.trim()) {
					message.value = copy.placeholder;
				}

				renderContacts();
			}
		}

		function closeModal() {
			overlay.classList.remove('uwcp-open');
			overlay.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('uwcp-no-scroll');
			errorBox.classList.remove('uwcp-visible');
			button.focus();
		}

		function getPageContext() {
			var title = '';
			var ogTitle = document.querySelector('meta[property="og:title"]');
			var mainHeading = document.querySelector('main h1, article h1, h1');

			if (ogTitle && ogTitle.content) {
				title = ogTitle.content.trim();
			} else if (mainHeading && mainHeading.textContent) {
				title = mainHeading.textContent.trim();
			} else {
				title = String(document.title || '').trim();
			}

			var pageUrl = window.location.href.split('#')[0];

			return {
				title: title || pageUrl,
				url: pageUrl
			};
		}

		function appendPageContext(value) {
			if (!data.includePageContext) {
				return value;
			}

			var page = getPageContext();
			var lines = [
				value,
				'',
				'────────────',
				copy.source_heading || 'Page source:',
				(copy.source_title || 'Title:') + ' ' + page.title,
				(copy.source_url || 'URL:') + ' ' + page.url
			];

			return lines.join('\n');
		}

		function sendMessage() {
			if (!selectedContact) {
				errorBox.textContent = copy.error_contact;
				errorBox.classList.add('uwcp-visible');
				return;
			}

			var value = message.value.trim();
			if (!value) {
				errorBox.textContent = copy.error_message;
				errorBox.classList.add('uwcp-visible');
				message.focus();
				return;
			}

			var fullMessage = appendPageContext(value);
			var url = 'https://wa.me/' + selectedContact.phone + '?text=' + encodeURIComponent(fullMessage);

			if (data.openNewTab) {
				window.open(url, '_blank', 'noopener');
			} else {
				window.location.href = url;
			}

			closeModal();
		}

		applyButtonLayout();
		applyLanguage(language);

		button.addEventListener('click', openModal);
		closeButton.addEventListener('click', closeModal);
		cancelButton.addEventListener('click', closeModal);
		sendButton.addEventListener('click', sendMessage);

		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				closeModal();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && overlay.classList.contains('uwcp-open')) {
				closeModal();
			}

			if (
				event.key === 'Enter' &&
				(event.ctrlKey || event.metaKey) &&
				overlay.classList.contains('uwcp-open')
			) {
				sendMessage();
			}
		});

		window.addEventListener('resize', applyButtonLayout);

		document.addEventListener('fornixx:languagechange', function (event) {
			if (event.detail && supported.indexOf(event.detail.language) !== -1) {
				applyLanguage(event.detail.language);
			}
		});

		window.addEventListener('storage', function (event) {
			if (
				event.key === data.storageKey &&
				supported.indexOf(event.newValue) !== -1
			) {
				applyLanguage(event.newValue);
			}
		});
	});
}());
