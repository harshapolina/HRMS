google.charts.load("current", { packages: ["corechart"] });
google.charts.load("current", { packages: ["bar"] });
google.charts.load("current", { packages: ["line"] });

google.charts.setOnLoadCallback(drawChart);
google.charts.setOnLoadCallback(drawBar);
google.charts.setOnLoadCallback(drawLine);

var piechartdata = [
  ['Year', 'Received', 'Processing', 'Cancel'],
  ['2022', 500, 300, 200],
  ['2023', 900, 200, 400],
  ['2024', 1100, 600, 300]
];

function drawChart() {

  var data = google.visualization.arrayToDataTable([
    ['Task', 'Hours per Day'],
    ['Work',     11],
    ['Bookings',      2],
    ['Revenue',  2],
    ['Incentive', 2],
    ['Slab',    7]
  ]);

  var options = {
    title: 'My Daily Activities'
  };

  var chart = new google.visualization.PieChart(document.getElementById('barchart_material'));

  chart.draw(data, options);
}

function drawBar() {
  var data = google.visualization.arrayToDataTable([
    ["Year", "Processing", "Received", "Cancelled"],
    ["2014", 1000, 400, 300],
    ["2015", 1170, 460, 250],
    ["2016", 660, 1120, 300],
    ["2017", 1030, 540, 350],
    ["2014", 1000, 400, 200],
    ["2015", 1170, 460, 250],
    ["2016", 660, 1120, 300],
    ["2017", 1030, 540, 350],
  ]);

  var options = {
    chart: {
      title: "Company Performance",
      subtitle: "Sales, Expenses, and Profit: 2014-2017",
    },
    bars: "vertical", // Required for Material Bar Charts.
  };

  var chart = new google.charts.Bar(
    document.getElementById("chart_line")
  );

  chart.draw(data, google.charts.Bar.convertOptions(options));
}
function drawLine() {
  var data = new google.visualization.DataTable();
  data.addColumn("number", "Day");
  data.addColumn("number", "Your Working Progress");
  data.addColumn("number", "The Avengers");
  data.addColumn("number", "Your Average Salary");

  data.addRows([
    [1, 37.8, 80.8, 41.8],
    [2, 30.9, 69.5, 32.4],
    [3, 25.4, 57, 25.7],
    [4, 11.7, 18.8, 10.5],
    [5, 11.9, 17.6, 10.4],
    [6, 8.8, 13.6, 7.7],
    [7, 7.6, 12.3, 9.6],
    [8, 12.3, 29.2, 10.6],
    [9, 16.9, 42.9, 14.8],
    [10, 12.8, 30.9, 11.6],
    [11, 5.3, 7.9, 4.7],
    [12, 6.6, 8.4, 5.2],
    [13, 4.8, 6.3, 3.6],
    [14, 4.2, 6.2, 3.4],
  ]);

  var options = {
    chart: {
      title: "Search Homes India Is Keep Tracking Your Working Progress",
      subtitle: "Focus On Work",
    },
    axes: {
      x: {
        0: { side: "top" },
      },
    },
  };

  var chart = new google.charts.Line(document.getElementById("line_top_x"));

  chart.draw(data, google.charts.Line.convertOptions(options));
}