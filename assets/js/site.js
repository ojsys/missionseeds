(function(){
  "use strict";

  var isAdmin = !!window.SEEDLINGS_ADMIN;
  var CSRF    = window.SEEDLINGS_CSRF || '';
  var API     = window.SEEDLINGS_API || 'admin/api.php';

  /* ---------------------------------------------------------------------
     Growth vine: draws down the page, fills in as the visitor scrolls
     --------------------------------------------------------------------- */
  var pathBg   = document.getElementById('vine-path-bg');
  var pathFill = document.getElementById('vine-path-fill');
  var trackWrap = document.getElementById('top');

  function layoutVine(){
    if(!trackWrap) return;
    var lastSection = trackWrap.querySelector('section:last-of-type, .section-divider:last-of-type, .harvest:last-of-type');
    var flowH = trackWrap.clientHeight;
    if(lastSection){
      var lr = lastSection.getBoundingClientRect();
      var wr = trackWrap.getBoundingClientRect();
      flowH = Math.max(0, Math.min(trackWrap.scrollHeight, (lr.bottom - wr.top)));
    }
    var h = Math.max(0, flowH);
    var d = 'M2 0 V ' + h;
    pathBg.setAttribute('d', d);
    pathFill.setAttribute('d', d);
    var svgs = trackWrap.querySelectorAll('#vine-wrap svg, .vine-track');
    for(var i=0;i<svgs.length;i++){
      svgs[i].style.overflow = 'hidden';
      if(!svgs[i].getAttribute('viewBox')){
        svgs[i].setAttribute('viewBox', '0 0 4 ' + Math.max(1,h));
        svgs[i].setAttribute('preserveAspectRatio', 'none');
      } else {
        svgs[i].setAttribute('viewBox', '0 0 4 ' + Math.max(1,h));
      }
    }
    pathFill.style.strokeDasharray = h;
    updateVineFill();
  }

  function updateVineFill(){
    if(!trackWrap) return;
    var scrollTop = window.scrollY;
    var winH = window.innerHeight;
    var total = trackWrap.clientHeight - winH;
    var progress = total > 0 ? Math.min(1, Math.max(0, scrollTop / total)) : 0;
    var lastSec = trackWrap.querySelector('section:last-of-type, .harvest:last-of-type');
    var h = trackWrap.clientHeight;
    if(lastSec){
      var lr2 = lastSec.getBoundingClientRect();
      var wr2 = trackWrap.getBoundingClientRect();
      h = Math.max(0, Math.min(trackWrap.scrollHeight, (lr2.bottom - wr2.top)));
    }
    h = Math.max(0, h);
    pathFill.style.strokeDashoffset = h * (1 - progress);
  }

  var ticking = false;
  window.addEventListener('scroll', function(){
    if(!ticking){
      requestAnimationFrame(function(){ updateVineFill(); ticking = false; });
      ticking = true;
    }
  });
  window.addEventListener('resize', function(){ layoutVine(); placeLeafNodes(); });
  window.addEventListener('load', function(){ layoutVine(); placeLeafNodes(); });

  /* ---------------------------------------------------------------------
     Leaf markers + section reveal
     --------------------------------------------------------------------- */
  function placeLeafNodes(){
    // viewport-space bottom of the last section; converted per-node below
    var lastSecBottom = null;
    var lastSec = trackWrap.querySelector('section:last-of-type, .harvest:last-of-type');
    if(lastSec){
      lastSecBottom = lastSec.getBoundingClientRect().bottom;
    }
    document.querySelectorAll('[data-vine-node]').forEach(function(node){
      var section = node.closest('section');
      if(!section) return;
      // un-hide first: offsetParent is null while display:none, which would
      // break the measurement on resize for a leaf hidden by a previous run
      node.style.visibility = '';
      node.style.display = '';
      var rect = section.getBoundingClientRect();
      // top is applied against the node's own containing block (.section-inner is
      // position:relative), so it must be measured against that same box — not
      // against #top, or every leaf lands thousands of px below its section and
      // inflates the document height.
      var origin = node.offsetParent || section;
      var oRect = origin.getBoundingClientRect();
      var top = rect.top - oRect.top + 44;
      var clamp = lastSecBottom !== null ? lastSecBottom - oRect.top - 8 : null;
      if(clamp !== null){
        top = Math.min(top, clamp);
      }
      node.style.top = top + 'px';
      if(clamp !== null && top >= clamp){
        node.style.visibility = 'hidden';
        node.style.display = 'none';
      } else {
        node.style.visibility = '';
        node.style.display = '';
      }
    });
  }

  var leafObserver = new IntersectionObserver(function(entries){
    entries.forEach(function(e){ if(e.isIntersecting) e.target.classList.add('in-view'); });
  }, { threshold: 0.4 });
  document.querySelectorAll('[data-vine-node]').forEach(function(n){ leafObserver.observe(n); });

  var revealObserver = new IntersectionObserver(function(entries){
    entries.forEach(function(e){ if(e.isIntersecting) e.target.classList.add('in-view'); });
  }, { threshold: 0.12 });
  document.querySelectorAll('.reveal').forEach(function(n){ revealObserver.observe(n); });

  if(!isAdmin) return; // everything below is admin-only tooling

  /* ---------------------------------------------------------------------
     Admin: shared request helper
     --------------------------------------------------------------------- */
  function post(params){
    params.csrf = CSRF;
    var body = new URLSearchParams(params);
    return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function(r){ return r.json(); });
  }

  function flash(el){
    el.classList.remove('editable-save-flash');
    void el.offsetWidth;
    el.classList.add('editable-save-flash');
  }

  /* ---------------------------------------------------------------------
     Admin: inline text editing (settings)
     --------------------------------------------------------------------- */
  document.querySelectorAll('.editable[data-setting-key]').forEach(function(el){
    var original = el.innerHTML;
    el.addEventListener('blur', function(){
      if(el.innerHTML === original) return;
      var value = el.innerText.replace(/\r\n/g, '\n');
      post({ action: 'save_setting', key: el.getAttribute('data-setting-key'), value: value })
        .then(function(res){
          if(res && res.ok){ original = el.innerHTML; flash(el); }
          else { alert((res && res.error) || 'Could not save — please try again.'); }
        })
        .catch(function(){ alert('Network error while saving.'); });
    });
  });

  /* ---------------------------------------------------------------------
     Admin: inline text editing (list items — criteria body / receive title)
     --------------------------------------------------------------------- */
  document.querySelectorAll('.editable[data-item-id]').forEach(function(el){
    var original = el.innerText;
    el.addEventListener('blur', function(){
      if(el.innerText === original) return;
      post({
        action: 'save_item_field',
        id: el.getAttribute('data-item-id'),
        field: el.getAttribute('data-item-field'),
        value: el.innerText
      }).then(function(res){
        if(res && res.ok){ original = el.innerText; flash(el); }
        else { alert((res && res.error) || 'Could not save — please try again.'); }
      }).catch(function(){ alert('Network error while saving.'); });
    });
  });

  /* ---------------------------------------------------------------------
     Admin: delete + toggle-visibility on list item cards
     --------------------------------------------------------------------- */
  document.addEventListener('click', function(e){
    var delBtn = e.target.closest('.btn-delete-item');
    if(delBtn){
      var card = delBtn.closest('[data-item-id]');
      if(!card) return;
      if(!confirm('Remove this item from the page?')) return;
      post({ action: 'delete_item', id: card.getAttribute('data-item-id') }).then(function(res){
        if(res && res.ok){ card.remove(); }
        else { alert((res && res.error) || 'Could not delete.'); }
      });
      return;
    }

    var visBtn = e.target.closest('.btn-toggle-visible');
    if(visBtn){
      var card2 = visBtn.closest('[data-item-id]');
      if(!card2) return;
      post({ action: 'toggle_visible', id: card2.getAttribute('data-item-id') }).then(function(res){
        if(res && res.ok){ location.reload(); }
        else { alert((res && res.error) || 'Could not update.'); }
      });
      return;
    }

    var addCard = e.target.closest('.add-item-card');
    if(addCard){ openAddForm(addCard); return; }
  });

  function openAddForm(addCard){
    var group    = addCard.getAttribute('data-group');
    var hasTitle = addCard.getAttribute('data-has-title') === '1';

    var iconOptions = (window.SEEDLINGS_ICONS || [])
      .map(function(i){ return '<option value="' + i.key + '">' + i.label + '</option>'; })
      .join('');

    var form = document.createElement('div');
    form.className = 'mini-form';
    form.innerHTML =
      '<select class="mf-icon">' + iconOptions + '</select>' +
      (hasTitle ? '<input class="mf-title" type="text" placeholder="Title">' : '') +
      '<textarea class="mf-body" placeholder="' + (hasTitle ? 'Optional description' : 'Text') + '" rows="2"></textarea>' +
      '<div class="mini-form-actions">' +
        '<button type="button" class="cancel">Cancel</button>' +
        '<button type="button" class="save">Add</button>' +
      '</div>';

    addCard.replaceWith(form);

    form.querySelector('.cancel').addEventListener('click', function(){ location.reload(); });
    form.querySelector('.save').addEventListener('click', function(){
      var iconKey = form.querySelector('.mf-icon').value;
      var title   = hasTitle ? form.querySelector('.mf-title').value : '';
      var body    = form.querySelector('.mf-body').value;
      if(hasTitle && !title.trim()){ alert('Please add a title.'); return; }
      if(!hasTitle && !body.trim()){ alert('Please add some text.'); return; }
      post({ action: 'add_item', group_key: group, icon_key: iconKey, title: title, body: body })
        .then(function(res){
          if(res && res.ok){ location.reload(); }
          else { alert((res && res.error) || 'Could not add item.'); }
        });
    });
  }

  /* ---------------------------------------------------------------------
     Admin: hero photo swap
     --------------------------------------------------------------------- */
  var changeBtn = document.getElementById('change-photo-btn');
  var fileInput = document.getElementById('hero-photo-input');
  if(changeBtn && fileInput){
    changeBtn.addEventListener('click', function(){ fileInput.click(); });
    fileInput.addEventListener('change', function(){
      var file = fileInput.files[0];
      if(!file) return;
      var fd = new FormData();
      fd.append('action', 'upload_image');
      fd.append('target', 'hero_image');
      fd.append('csrf', CSRF);
      fd.append('image', file);
      changeBtn.textContent = 'Uploading…';
      fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          changeBtn.textContent = 'Change photo';
          if(res && res.ok){
            document.getElementById('hero-img').src = res.url + '?v=' + Date.now();
          } else {
            alert((res && res.error) || 'Upload failed.');
          }
        })
        .catch(function(){ changeBtn.textContent = 'Change photo'; alert('Upload failed.'); });
    });
  }
})();
