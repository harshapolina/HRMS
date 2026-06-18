</div>
  <!-- incentive main close -->
   <!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script type="text/javascript" src="./assets/js/script.js?v=20250930"></script>
<!-- Addin Actual Revenu as per commission and cashback --> 
<script>
    function addCalculate(){if(isNaN(document.forms.myform.cagreement.value)||""==document.forms.myform.cagreement.value)var e=0;else var e=parseFloat(document.forms.myform.cagreement.value);if(isNaN(document.forms.myform.ccashback.value)||""==document.forms.myform.ccashback.value)var m=0;else var m=parseFloat(document.forms.myform.ccashback.value);if(document.forms.myform.crevenue.value=parseInt(e*(m/100)),isNaN(document.forms.myform.cccashback.value)||""==document.forms.myform.cccashback.value)var r=0;else var r=parseFloat(document.forms.myform.cccashback.value);if(isNaN(document.forms.myform.crevenue.value)||""==document.forms.myform.crevenue.value)var a=0;else var a=parseInt(document.forms.myform.crevenue.value);document.forms.myform.ccrevenue.value=parseInt(a-e*(r/100))calculateCashbackRevenue();}
</script>
<!-- Addin Actual Revenu as per commission and cashback End--> 
<!-- Updating Actual Revenu as per commission and cashback -->
<script>
 function updateCalculate(){if(isNaN(document.getElementById("cagreement").value)||""==document.getElementById("cagreement").value)var e=0;else var e=parseFloat(document.getElementById("cagreement").value);if(isNaN(document.getElementById("ccashback").value)||""==document.getElementById("ccashback").value)var a=0;else var a=parseFloat(document.getElementById("ccashback").value);if(document.getElementById("crevenue").value=e*(a/100),isNaN(document.getElementById("cccashback").value)||""==document.getElementById("cccashback").value)var l=0;else var l=parseFloat(document.getElementById("cccashback").value);if(isNaN(document.getElementById("crevenue").value)||""==document.getElementById("crevenue").value)var t=0;else var t=parseInt(document.getElementById("crevenue").value);document.getElementById("ccrevenue").value=t-e*(l/100)}
</script>
<!-- Updating Actual Revenu as per commission and cashback End-->
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
    }, 2000);
  });
  </script>

</body>
</html>