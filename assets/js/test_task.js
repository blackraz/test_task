(function () {
	var form = document.getElementById('tt-data-form');
	if (!form) return;
	var startEl = document.getElementById('tt-start-time');
	var alertBox;
	
	function setStart () { if (startEl) startEl.value = (Date.now() / 1000).toString(); }
	
	function ensureAlert () {
		if (!alertBox) {
			alertBox = document.createElement('div');
			alertBox.className = 'tt-alert';
			form.insertBefore(alertBox, form.firstChild);
		}
		return alertBox;
	}
	
	function showMsg (type, msg) {
		var box = ensureAlert();
		box.className = 'tt-alert ' + (type === 'ok' ? 'tt-success' : 'tt-error');
		box.textContent = msg;
	}
	
	function validate () {
		var emailEl = document.getElementById('tt-email');
		var phoneEl = document.getElementById('tt-phone');
		var email = emailEl ? emailEl.value.trim() : '';
		var phone = phoneEl ? phoneEl.value.trim() : '';
		var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		var phoneOk = /^[+]?[- 0-9()]{7,20}$/.test(phone);
		if (!emailOk) {
			showMsg('error', 'Please enter a valid email.');
			return false;
		}
		if (!phoneOk) {
			showMsg('error', 'Please enter a valid phone number.');
			return false;
		}
		return true;
	}
	
	setStart();
	var ttFirstInteracted = false;
	
	function ttOnFirstInteract () {
		if (ttFirstInteracted) return;
		ttFirstInteracted = true;
		setStart();
		form.removeEventListener('focusin', ttOnFirstInteract);
	}
	
	form.addEventListener('focusin', ttOnFirstInteract);
	
	form.addEventListener('submit', function (e) {
		if (!form.checkValidity()) {
			form.reportValidity();
			e.preventDefault();
			return false;
		}
		e.preventDefault();
		if (!validate()) return false;
		
		var fd = new FormData(form);
		var target = form.getAttribute('action') || (window.ajaxurl || '');
		
		fetch(target, {
			method: 'POST',
			body: fd,
		}).then(function (r) { return r.json(); }).then(function (data) {
			if (data && data.success) {
				var msg = (data.data && data.data.message) ? data.data.message : 'Thank you! Your information has been submitted successfully.';
				if (data.data && typeof data.data.time !== 'undefined') {
					msg += ' Time taken: ' + Number(data.data.time).toFixed(2) + ' seconds.';
				}
				showMsg('ok', msg);
				form.reset();
				setStart();
				ttFirstInteracted = false;
				form.addEventListener('focusin', ttOnFirstInteract);
			} else {
				var em = (data && data.data && data.data.message) ? data.data.message : 'Submission failed.';
				if (data && data.data && typeof data.data.time !== 'undefined') {
					em += ' Time taken: ' + Number(data.data.time).toFixed(2) + ' seconds.';
				}
				showMsg('error', em);
			}
		}).catch(function () {
			showMsg('error', 'Network error. Please try again.');
		});
	});
})();
