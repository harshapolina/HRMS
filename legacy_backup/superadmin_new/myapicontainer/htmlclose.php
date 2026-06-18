  </div>
  <!-- incentive main close -->
  <!--<script type="text/javascript" src="../../../assets/js/button_colvis.js"></script>-->
  <!--<script type="text/javascript" src="../../../assets/js/semantic.js"></script>-->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script type="text/javascript" src="../../../assets/js/bootstrap5.3.2.js"></script>
  <script type="text/javascript" src="../../../assets/js/script.js"></script>
  <script type="text/javascript" src="../assets/js/main_script.js"></script>
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
    $(document).ready(function(){var e;let t=$("#example").DataTable(),a;document.getElementById("searchInput").addEventListener("input",(e=function e(){let a=document.getElementById("searchInput").value.toLowerCase();t.search(a).draw()},function(...t){let n=this;clearTimeout(a),a=setTimeout(()=>e.apply(n,t),300)}))});
  </script>
</body>
</html>