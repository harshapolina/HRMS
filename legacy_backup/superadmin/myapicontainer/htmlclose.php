  </div>
  <!-- incentive main close -->
  <!--<script type="text/javascript" src="../../../assets/js/button_colvis.js"></script>-->
  <!--<script type="text/javascript" src="../../../assets/js/semantic.js"></script>-->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Use CDN Bootstrap bundle so `bootstrap` is defined and avoid missing local file 404 -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script type="text/javascript" src="../../../assets/js/script.js"></script>
  <script type="text/javascript" src="../assets/js/main_script.js"></script>
  <!-- DataTables (depends on jQuery) - include so $(...).DataTable is defined where used -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      var loader = document.getElementById('loader');
      // Show loader initially
      loader.style.opacity = '1';
      loader.style.top = '0';
      loader.style.zIndex = '1002'; // Set initial z-index to 999
      // Hide loader after 5 seconds with smooth transition
      setTimeout(function() {
        loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
        loader.style.opacity = '0';
        loader.style.top = '-100px'; // Move loader smoothly upward
        loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
      }, 5000);
    });
  </script>
  <script>
    $(document).ready(function(){
      // Only initialize DataTable and search wiring if the table and input exist
      var tableEl = document.getElementById('example');
      var searchInput = document.getElementById('searchInput');
      if (tableEl && typeof $.fn.DataTable === 'function') {
        var t = $('#example').DataTable();
        if (searchInput) {
          // simple debounce
          function debounce(fn, wait) {
            var timeout;
            return function() {
              var ctx = this, args = arguments;
              clearTimeout(timeout);
              timeout = setTimeout(function(){ fn.apply(ctx, args); }, wait);
            };
          }
          var doSearch = function() {
            var q = (searchInput.value || '').toLowerCase();
            t.search(q).draw();
          };
          searchInput.addEventListener('input', debounce(doSearch, 300));
        }
      }
    });
  </script>
</body>
</html>