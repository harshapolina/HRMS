// this script for calculate the incentive (calculactor Script)
$(document).ready(function () {
    $("#calculator-form").submit(function (e) {
        e.preventDefault();

        // Get input values
        var d1 = parseFloat($("#d1").val());
        var d2 = parseFloat($("#d2").val());

        // Perform the calculations
        var b3 = d1 * 5;
        var b4 = d1 * 10;
        var b5 = d1 * 15;
        var b6 = d1 * 20;
        var b7 = d1 * 25;

        var c3 = (d2 > b3) ? b3 : d2;
        var c4 = (d2 > b4) ? b4 - b3 : d2 - b3;
        var c5 = (d2 > b5) ? b5 - b4 : (d2 > b4) ? d2 - b4 : 0;
        var c6 = (d2 > b6) ? b6 - b5 : (d2 > b5) ? d2 - b5 : 0;
        var c7 = (d2 > b7) ? b7 - b6 : (d2 > b6) ? d2 - b6 : 0;
        var c8 = (d2 > b7) ? d2 - b3 : 0;

        var d3 = Math.floor(0.04 * c3);
        var d4 = Math.floor(0.08 * c4);
        var d5 = Math.floor(0.12 * c5);
        var d6 = Math.floor(0.16 * c6);
        var d7 = Math.floor(0.20 * c7);
        var sumD3D7 = d3 + d4 + d5 + d6 + d7;
        var d8 = Math.floor(0.22 * c8);

        var d10 = (c8 === 0) ? sumD3D7 : 0;
        var d11 = (c8 > 0) ? (d8 + d3) : 0;

        var result = (d1 <= 40000 && d2 <= d1 * 25) || (d1 > 40000 && d2 <= d1 * 25) ? d10 : d11;

        // Display the result
        $("#result").text(result.toFixed(2));
    });
});
//This Script is for auto calculation of revenue and cashback
  //Calculation of cashbak and revenue
  function addCalculate() {   
 
    if(isNaN(document.forms["myform"]["cagreement"].value) || document.forms["myform"]["cagreement"].value=="") {   
   var text1 = 0;   
   } else {   
   var text1 = parseFloat(document.forms["myform"]["cagreement"].value);   
   }   
   
    if(isNaN(document.forms["myform"]["ccashback"].value) || document.forms["myform"]["ccashback"].value=="") {   
   var text2 = 0;   
   } else {   
     var text2 = parseFloat(document.forms["myform"]["ccashback"].value); 
   } 
   //print Value Input
   document.forms["myform"]["crevenue"].value = parseInt((text1)*(text2/100));
   
    if(isNaN(document.forms["myform"]["cccashback"].value) || document.forms["myform"]["cccashback"].value=="") {   
   var text3 = 0;   
   } else {   
     var text3 = parseFloat(document.forms["myform"]["cccashback"].value); 
   }
    
    if(isNaN(document.forms["myform"]["crevenue"].value) || document.forms["myform"]["crevenue"].value=="") {   
   var text4 = 0; 
   
   } else {   
     var text4 = parseInt(document.forms["myform"]["crevenue"].value); 
   }
    document.forms["myform"]["ccrevenue"].value = parseInt((text4)-((text1)*(text3/100)));
   } 
  //This Script is for calculation for other calculation
  //Calculation for revenue calculate
  function updateCalculate() {   
 
    if(isNaN(document.getElementById("cagreement").value) || document.getElementById("cagreement").value=="") {   
   var text1 = 0;   
   } else {   
   var text1 = parseFloat(document.getElementById("cagreement").value);   
   }   
   
    if(isNaN(document.getElementById("ccashback").value) || document.getElementById("ccashback").value=="") {   
   var text2 = 0;   
   } else {   
     var text2 = parseFloat(document.getElementById("ccashback").value); 
   } 
   //print Value Input
   document.getElementById("crevenue").value = ((text1)*(text2/100));
   
    if(isNaN(document.getElementById("cccashback").value) || document.getElementById("cccashback").value=="") {   
   var text3 = 0;   
   } else {   
     var text3 = parseFloat(document.getElementById("cccashback").value); 
   }
    
    if(isNaN(document.getElementById("crevenue").value) || document.getElementById("crevenue").value=="") {   
   var text4 = 0;   
   } else {   
     var text4 = parseInt(document.getElementById("crevenue").value); 
   }
    document.getElementById("ccrevenue").value = ((text4)-((text1)*(text3/100)));
   } 
  // this script is for cancel booking button 
  // Add an event listener to the radio button with value "Canceled"
  document.querySelectorAll('.Canceled').forEach(function (element) {
  element.addEventListener('change', function (event) {
      if (event.target.value === 'Canceled') {
          // Set Commission (%) and CashBack (%) fields to '0'
          document.getElementById('ccashback').value = '0';
          document.getElementById('cccashback').value = '0';
          // Recalculate the dependent fields
          updateCalculate();
      }
  });
});
// this script is for cancle booking button End
   
   