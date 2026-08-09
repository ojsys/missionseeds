      </div><!-- /.content-inner -->
    </main>
  </div><!-- /.shell-main -->
</div><!-- /.shell -->

<script>
(function(){
  "use strict";
  var sidebar  = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebar-backdrop');
  var openBtn  = document.getElementById('sidebar-open');
  var closeBtn = document.getElementById('sidebar-close');
  if(!sidebar || !openBtn) return;

  function open(){
    document.body.classList.add('sidebar-open');
    backdrop.hidden = false;
    openBtn.setAttribute('aria-expanded', 'true');
    var first = sidebar.querySelector('.nav-item');
    if(first) first.focus();
  }
  function close(){
    document.body.classList.remove('sidebar-open');
    backdrop.hidden = true;
    openBtn.setAttribute('aria-expanded', 'false');
    openBtn.focus();
  }

  openBtn.addEventListener('click', open);
  if(closeBtn) closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && document.body.classList.contains('sidebar-open')) close();
  });

  // The drawer is a mobile affordance; if the viewport grows past the
  // breakpoint the sidebar is permanent again and the open state is stale.
  window.addEventListener('resize', function(){
    if(window.innerWidth > 960 && document.body.classList.contains('sidebar-open')){
      document.body.classList.remove('sidebar-open');
      backdrop.hidden = true;
      openBtn.setAttribute('aria-expanded', 'false');
    }
  });

  // Warn before leaving a form with unsaved edits — admin forms are long and
  // a mis-click on the sidebar should not silently discard someone's work.
  var dirty = false;
  document.querySelectorAll('form').forEach(function(f){
    if(f.hasAttribute('data-no-guard')) return;
    f.addEventListener('input', function(){ dirty = true; });
    f.addEventListener('submit', function(){ dirty = false; });
  });
  window.addEventListener('beforeunload', function(e){
    if(!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });
})();
</script>
</body>
</html>
