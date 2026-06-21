(function(){
  function closest(el, selector){ while(el && el.nodeType === 1){ if(el.matches(selector)) return el; el = el.parentElement; } return null; }
  function formatNumber(n){ return new Intl.NumberFormat().format(Number(n || 0)); }

  function setupRanges(){
    document.querySelectorAll('[data-range-box]').forEach(function(box){
      var minInput = box.querySelector('[data-range-input="min"]');
      var maxInput = box.querySelector('[data-range-input="max"]');
      var fill = box.querySelector('[data-range-fill]');
      var outMin = box.querySelector('[data-range-out="min"]');
      var outMax = box.querySelector('[data-range-out="max"]');
      if(!minInput || !maxInput || !fill || !outMin || !outMax) return;
      var min = Number(minInput.min), max = Number(minInput.max);
      var prefix = box.getAttribute('data-prefix') || '', suffix = box.getAttribute('data-suffix') || '';
      function render(active){
        var a = Number(minInput.value), b = Number(maxInput.value);
        if(a > b){ if(active === 'min'){ b = a; maxInput.value = b; } else { a = b; minInput.value = a; } }
        var left = ((a - min) / (max - min || 1)) * 100;
        var right = ((b - min) / (max - min || 1)) * 100;
        fill.style.left = left + '%'; fill.style.width = Math.max(0, right-left) + '%';
        outMin.textContent = prefix + formatNumber(a) + suffix;
        outMax.textContent = prefix + formatNumber(b) + suffix;
      }
      minInput.addEventListener('input', function(){ render('min'); });
      maxInput.addEventListener('input', function(){ render('max'); });
      render();
    });
  }

  function setupViews(){
    document.querySelectorAll('[data-apibara-results-root]').forEach(function(root){
      var buttons = root.querySelectorAll('[data-results-view]');
      var panels = root.querySelectorAll('[data-results-panel]');
      var defaultView = root.getAttribute('data-default-view') || (window.ApibaraVehicles || {}).defaultView || 'grid';
      function set(view){
        panels.forEach(function(panel){ var hidden = panel.getAttribute('data-results-panel') !== view; panel.hidden = hidden; panel.setAttribute('aria-hidden', hidden ? 'true' : 'false'); panel.classList.toggle('is-hidden', hidden); });
        buttons.forEach(function(btn){ btn.classList.toggle('is-active', btn.getAttribute('data-results-view') === view); });
      }
      buttons.forEach(function(btn){ btn.addEventListener('click', function(){ set(btn.getAttribute('data-results-view')); }); });
      set(defaultView);
    });
  }

  function setupModels(){
    document.querySelectorAll('.apibara-filter-form').forEach(function(form){
      var make = form.querySelector('[name="apibara_make"]');
      var model = form.querySelector('[name="apibara_model"]');
      if(!make || !model) return;
      var modelsByMake = {};
      try { modelsByMake = JSON.parse(form.getAttribute('data-models-by-make') || '{}') || {}; } catch(e) {}
      var selected = new URLSearchParams(window.location.search).get('apibara_model') || model.value || '';
      function fill(){
        var currentMake = make.value || '';
        var models = modelsByMake[currentMake] || [];
        var html = '<option value="">All</option>';
        models.forEach(function(m){
          var esc = String(m).replace(/[&<>'"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]; });
          html += '<option value="'+esc+'"'+(String(m)===selected?' selected':'')+'>'+esc+'</option>';
        });
        model.innerHTML = html;
      }
      make.addEventListener('change', function(){ selected=''; fill(); });
      fill();
    });
  }

	function setupSliders(){
	  document.querySelectorAll('[data-mini-slider]').forEach(function(slider){
		var track = slider.querySelector('[data-mini-track]');
		if(!track) return;

		var slides = Array.prototype.slice.call(track.children);
		var total = slides.length;
		var index = 0;

		if(total < 1) return;

		var prev = slider.querySelector('[data-mini-prev]');
		var next = slider.querySelector('[data-mini-next]');
		var dots = slider.querySelectorAll('[data-mini-dot]');
		var thumbs = slider.querySelectorAll('[data-single-thumb]');

		function render(){
		  var isSingleGallery = slider.hasAttribute('data-single-gallery');

		  if(isSingleGallery){
			track.style.transform = '';

			slides.forEach(function(slide, i){
			  slide.classList.toggle('is-active', i === index);
			});
		  } else {
			track.style.transform = 'translateX(-' + (index * 100) + '%)';
		  }

		  dots.forEach(function(dot, i){
			dot.classList.toggle('is-active', i === index);
		  });

		  thumbs.forEach(function(thumb, i){
			thumb.classList.toggle('is-active', i === index);
			thumb.setAttribute('aria-current', i === index ? 'true' : 'false');
		  });

		  var activeThumb = thumbs[index];
		  if(activeThumb && activeThumb.scrollIntoView){
			activeThumb.scrollIntoView({
			  behavior: 'smooth',
			  block: 'nearest',
			  inline: 'center'
			});
		  }
		}

		if(prev){
		  prev.addEventListener('click', function(e){
			e.preventDefault();
			e.stopPropagation();
			index = index <= 0 ? total - 1 : index - 1;
			render();
		  });
		}

		if(next){
		  next.addEventListener('click', function(e){
			e.preventDefault();
			e.stopPropagation();
			index = index >= total - 1 ? 0 : index + 1;
			render();
		  });
		}

		dots.forEach(function(dot){
		  dot.addEventListener('click', function(e){
			e.preventDefault();
			e.stopPropagation();
			index = parseInt(dot.getAttribute('data-mini-dot') || '0', 10);
			if(index < 0) index = 0;
			if(index >= total) index = total - 1;
			render();
		  });
		});

		thumbs.forEach(function(thumb){
		  thumb.addEventListener('click', function(e){
			e.preventDefault();
			e.stopPropagation();
			index = parseInt(thumb.getAttribute('data-single-thumb') || '0', 10);
			if(index < 0) index = 0;
			if(index >= total) index = total - 1;
			render();
		  });
		});

		render();
	  });
	}

  function countdownText(date){
    var diff = date.getTime() - Date.now(); if(!Number.isFinite(diff) || diff <= 0) return null;
    var s = Math.floor(diff/1000), d = Math.floor(s/86400), h = Math.floor((s%86400)/3600), m = Math.floor((s%3600)/60), sec = s%60;
    if(d>0) return d+'d '+h+'h '+m+'m'; if(h>0) return h+'h '+m+'m '+sec+'s'; if(m>0) return m+'m '+sec+'s'; return sec+'s';
  }

  function updateCountdowns(){
    document.querySelectorAll('[data-countdown-to]').forEach(function(el){
      var raw = el.getAttribute('data-countdown-to'); var status = el.getAttribute('data-countdown-status');
      if(status === 'ended'){ el.textContent = 'Auction ended'; return; }
      if(!raw){ el.textContent = 'No date'; return; }
      var text = countdownText(new Date(raw)); el.textContent = text || 'Auction started';
    });
  }

  function openLightbox(anchor){
    var group = anchor.getAttribute('data-apibara-fancybox') || anchor.getAttribute('data-gallery') || 'default';
    var esc = window.CSS && CSS.escape ? CSS.escape(group) : String(group).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-apibara-lightbox][data-apibara-fancybox="'+esc+'"], [data-apibara-lightbox][data-gallery="'+esc+'"]'));
    if(!items.length) items = [anchor];
    var index = Math.max(0, items.indexOf(anchor));
    var overlay = document.createElement('div');
    overlay.className = 'apibara-lightbox';
    overlay.innerHTML = '<button type="button" class="apibara-lightbox-close" aria-label="Close">×</button><button type="button" class="apibara-lightbox-prev" aria-label="Previous">‹</button><img alt=""><button type="button" class="apibara-lightbox-next" aria-label="Next">›</button><div class="apibara-lightbox-count"></div>';
    var img = overlay.querySelector('img');
    var count = overlay.querySelector('.apibara-lightbox-count');
    function src(item){ return item.getAttribute('href') || item.getAttribute('data-src') || ''; }
    function render(){
      img.setAttribute('src', src(items[index]));
      count.textContent = (index + 1) + ' / ' + items.length;
      overlay.classList.toggle('is-single', items.length < 2);
    }
    overlay.querySelector('.apibara-lightbox-prev').addEventListener('click', function(e){ e.stopPropagation(); index = index <= 0 ? items.length - 1 : index - 1; render(); });
    overlay.querySelector('.apibara-lightbox-next').addEventListener('click', function(e){ e.stopPropagation(); index = index >= items.length - 1 ? 0 : index + 1; render(); });
    overlay.querySelector('.apibara-lightbox-close').addEventListener('click', function(){ overlay.remove(); document.body.classList.remove('apibara-lightbox-open'); });
    overlay.addEventListener('click', function(e){ if(e.target === overlay){ overlay.remove(); document.body.classList.remove('apibara-lightbox-open'); } });
    document.addEventListener('keydown', function onKey(e){
      if(!document.body.contains(overlay)){ document.removeEventListener('keydown', onKey); return; }
      if(e.key === 'Escape'){ overlay.remove(); document.body.classList.remove('apibara-lightbox-open'); }
      if(e.key === 'ArrowLeft'){ index = index <= 0 ? items.length - 1 : index - 1; render(); }
      if(e.key === 'ArrowRight'){ index = index >= items.length - 1 ? 0 : index + 1; render(); }
    });
    document.body.appendChild(overlay);
    document.body.classList.add('apibara-lightbox-open');
    render();
  }

  document.addEventListener('click', function(e){
    var open = closest(e.target, '.apibara-open-modal');
    if(open){ var wrap = closest(open, '.apibara-single-page') || document; var modal = wrap.querySelector('.apibara-modal'); if(modal){ modal.hidden=false; document.body.classList.add('apibara-modal-open'); } e.preventDefault(); return; }
    if(closest(e.target, '[data-apibara-close]')){ var mc=closest(e.target, '.apibara-modal'); if(mc){ mc.hidden=true; document.body.classList.remove('apibara-modal-open'); } e.preventDefault(); return; }
    var lb = closest(e.target, '[data-apibara-lightbox]');
    if(lb){ e.preventDefault(); openLightbox(lb); return; }
    var copy = closest(e.target, '[data-copy]');
    if(copy){ var text=copy.getAttribute('data-copy'); if(!text) return; navigator.clipboard && navigator.clipboard.writeText(text).then(function(){ copy.classList.add('is-copied'); setTimeout(function(){ copy.classList.remove('is-copied'); }, 900); }); }
  });

  document.addEventListener('submit', function(e){
    var form = closest(e.target, '.apibara-contact-form'); if(!form) return; e.preventDefault();
    var result = form.querySelector('.apibara-form-result'); var button = form.querySelector('button[type="submit"]'); var data = new FormData(form);
    if(result) result.textContent = ''; if(button) button.disabled = true;
    fetch((window.ApibaraVehicles || {}).ajaxUrl || '/wp-admin/admin-ajax.php', {method:'POST', credentials:'same-origin', body:data}).then(function(r){ return r.json(); }).then(function(json){ if(result){ result.textContent = json && json.data && json.data.message ? json.data.message : (json.success ? 'Sent.' : 'Error.'); result.style.color = json.success ? '#15803d' : '#b91c1c'; } if(json.success) form.reset(); }).catch(function(){ if(result){ result.textContent = 'Error.'; result.style.color = '#b91c1c'; } }).finally(function(){ if(button) button.disabled = false; });
  });

  document.addEventListener('DOMContentLoaded', function(){ setupRanges(); setupViews(); setupModels(); setupSliders(); updateCountdowns(); setInterval(updateCountdowns, 1000); });
})();
