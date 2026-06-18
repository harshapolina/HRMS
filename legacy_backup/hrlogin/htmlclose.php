</div>
  <!-- incentive main close -->
  <!-- Core Dependencies (CDN) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- DataTables Framework (CDN) -->
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css"/>
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css"/>
  
  <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
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
    }, 3000);
  });
  </script>
  <script>
    $(document).ready(function () {
      var tableEl = $("#example");
      if (!tableEl.length) return;
      var t = tableEl.DataTable({ dom: "lrtip" });
      var searchInput = document.getElementById("searchInput");
      if (!searchInput) return;
      var debounceTimer;
      function applySearch() {
        var q = (searchInput.value || "").toLowerCase();
        t.search(q).draw();
      }
      searchInput.addEventListener("input", function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
          applySearch();
        }, 300);
      });
    });
  </script>
</body>
</html> 