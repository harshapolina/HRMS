var menuBar = document.querySelector(".sidebarbutt");
var sideBar = document.querySelector(".sidebar");

function toggleleftsidebar() {
  sideBar.classList.toggle("close");
}

var rightsidebar = document.querySelector("#rightsidebar");
var togglerightsidebar = document.querySelector("#togglerightsidebar");
var closebtnbar = document.querySelector("#close-btn");

togglerightsidebar.addEventListener("click", function () {
   rightsidebar.style.display="block";
});

closebtnbar.addEventListener("click", function () {
    rightsidebar.style.display="none";
 });
 
 function openSearchPopup() {
  var searchPopup = document.getElementById('search-popup');
  searchPopup.style.display = 'block';
}

function closeSearchPopup() {
  var searchPopup = document.getElementById('search-popup');
  searchPopup.style.display = 'none';
}

// var toggler = document.getElementById("theme-toggle");

// toggler.addEventListener("change", function () {
//   if (this.checked) {
//     document.body.classList.add("dark");
//     document.body.style.color = "white";
//     document.querySelector(".content").add("dark");
    
//   } else {
//     document.body.classList.remove("dark");
//     document.body.style.color = "black";
//   }
// });

var morenotif = document.querySelector("#more_notif_btn");
var notifcontent = document.querySelector("#notif-content_box");
var morenotificon = document.querySelector("#more_notif_icon");
var moreprofileicon = document.querySelector("#more_profile_icon");
var profilecontent = document.querySelector("#profile-content_box");
var closebtn = document.querySelector(".closebtn");
var closebtn1 = document.querySelector(".closebtn1");

morenotificon.addEventListener("click", () => {
    notifcontent.style.display === "block" ? "none" : "block";
  profilecontent.style.display = "none";
});

closebtn.addEventListener("click", () => {
  profilecontent.style.display = "none";
  notifcontent.style.display = "none";
});

closebtn1.addEventListener("click", function () {
  profilecontent.style.display = "none";
});

moreprofileicon.addEventListener("click", () => {
  profilecontent.style.display =
    profilecontent.style.display === "block" ? "none" : "block";
  notifcontent.style.display = "none";
});

var originalDisplay = {};
function filterOptions() {
  var input, filter, ul, li, a, i, txtValue;
  input = document.getElementById('searchInput');
  filter = input.value.toUpperCase();
  ul = document.getElementById('optionul');
  li = ul.getElementsByClassName('optionli');

  for (i = 0; i < li.length; i++) {
    a = li[i].getElementsByTagName('a')[0];
    txtValue = a.textContent || a.innerText;
    if (txtValue.toUpperCase().indexOf(filter) > -1) {
      li[i].style.display = 'block';
    } else {
      li[i].style.display = 'none';
    }
  }
  if (filter === '') {
    for (i = 0; i < li.length; i++) {
      li[i].style.display = 'none'; 
    }
  }
}
// function downloadCsv(data, filename) {
//   var csvContent = "data:text/csv;charset=utf-8,";
//   data.forEach(function (row) {
//     csvContent += row.join(",") + "\n";
//   });

//   var encodedUri = encodeURI(csvContent);
//   var link = document.createElement("a");
//   link.setAttribute("href", encodedUri);
//   link.setAttribute("download", filename);
//   document.body.appendChild(link);
//   link.click();
// }

// $("#downloadCsvBtn").click(function () {
//   var csvData = [];

//   // Loop through table rows to collect data
//   $("#pagedata tr.custom-filtered-row").each(function () {
//     var rowData = [];
//     var isExcludedHeaderRow = false;

//     // Loop through table cells
//     $(this)
//       .find("td")
//       .each(function (index) {
//         var cellText = $(this).text().trim();

//         if (
//           cellText === "Financial Year/Bookings:" ||
//           cellText === "Total Revenue:" ||
//           cellText === "Actual Revenue:" ||
//           cellText === "Recived Amount:" ||
//           cellText === "Amount To be Pay:" ||
//           cellText === "Total Paid Amt:"
//         ) {
//           isExcludedHeaderRow = true;
//           return false; // Stop checking cells once an excluded header is found
//         }

//         if (
//           // index === 11 ||  // Column 11 (filterAgreement)
//           // index === 12 ||  // Column 12 (filterCommission)
//           // index === 13 ||  // Column 13 (filterTrevenue)
//           // index === 14 ||  // Column 14 (filterCashBack)
//           // index === 15 ||  // Column 15 (filterActualRevenue)
//           // index === 16 ||  // Column 16 (filterStatus)
//           // index === 17 ||  // Column 17 (filterReceived)
//           index === 18     // Column 18 (filterSales)
//         ) {
//           // Replace specific columns with asterisks
//           rowData.push('Search Homes India');
//         } else {
//           rowData.push(cellText);
//         }
//       });

//     if (!isExcludedHeaderRow) {
//       // Exclude the row if it matches the specific content
//       var rowText = rowData.join(',');
//       if (
//         rowText !== "*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*"
//       ) {
//         csvData.push(rowData);
//       }
//     }
//   });

//   downloadCsv(csvData, "filtered_data.csv");
// });
$(document).ready(function () {
  $('#scroll-left').on('click', function () {
    $('#example_wrapper .dt-scroll-body').scrollLeft($('#example_wrapper .dt-scroll-body').scrollLeft() - 200);
  });
  $('#scroll-right').on('click', function () {
    $('#example_wrapper .dt-scroll-body').scrollLeft($('#example_wrapper .dt-scroll-body').scrollLeft() + 200);
  });
});