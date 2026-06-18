// Load Google Charts library for other charts
google.charts.load("current", { packages: ["corechart", "bar"] });

// Declare chart variables outside the function scope
var pieChart;
var barChart;
var profitLossChart;

// Function to populate dropdown and draw charts
function populateDropdown() {
    var dropdown = $('#year_select');
    dropdown.empty(); // Clear previous options

    // Extract years from either pieChartData or barChartData
    var years = pieChartData.map(item => item.year);
    years.sort(); // Sort in ascending order
    years.reverse(); // Reverse to have most recent year first

    // Populate dropdown with years
    years.forEach(function(year) {
        dropdown.append($('<option>').val(year).text(year));
    });

    // Set the default year to the most recent year
    var defaultYear = years[0];
    dropdown.val(defaultYear);

    // Event listener for dropdown change
    dropdown.on('change', function() {
        var selectedYear = $(this).val();
        fetchDataAndDrawCharts(selectedYear);
    });

    // Initial chart drawing
    fetchDataAndDrawCharts(defaultYear);
}

// Function to fetch data for selected year and draw charts
function fetchDataAndDrawCharts(selectedYear) {
    // Find data for the selected year from both datasets
    var pieChartSelectedData = pieChartData.find(item => item.year === selectedYear);
    var barChartSelectedData = barChartData.find(item => item.year === selectedYear);
    var profitLossSelectedData = profitLossData.find(item => item.year === selectedYear);

    // Draw pie chart
    drawPieChart(pieChartSelectedData);

    // Draw bar chart
    drawBarChart(barChartSelectedData);

    // Draw profit and loss chart using Chart.js
    drawProfitLossChart(profitLossSelectedData);
}

// Function to draw pie chart
function drawPieChart(data) {
    var chartData = [
        ['Task', 'Count'],
        ['Received', parseInt(data.received_count)],
        ['Canceled', parseInt(data.canceled_count)],
        ['Processing', parseInt(data.processing_count)]
    ];
    var chartOptions = {
        title: 'Status Distribution for Year: ' + data.year,
        is3D: true,
        colors: ['#28a745', '#dc3545', '#FFAE42'] // Customize colors for each slice
    };
    var dataTable = google.visualization.arrayToDataTable(chartData);
    if (!pieChart) {
        pieChart = new google.visualization.PieChart(document.getElementById('piechart_3d'));
    }
    pieChart.draw(dataTable, chartOptions);
}
// Function to draw Bar CHart
function drawBarChart(data) {
    var chartData = [
        ['Year', 'Actual Revenue', 'Invoice Include', 'Received Revenue'],
        [data.year, parseInt(data.actual_revenue), parseInt(data.invoice_include), parseInt(data.received_revenue)]
    ];
    var chartOptions = {
        title: 'Company Performance for Year: ' + data.year,
        bars: 'vertical', // Ensure bars are displayed vertically
        colors: ['#28a745', '#B5FF33', '#007bff'],
        border: ['1px solid black'],
        bar: { groupWidth: '40%' } // Make the bar lines thinner
    };
    var dataTable = google.visualization.arrayToDataTable(chartData);
    if (!barChart) {
        barChart = new google.visualization.BarChart(document.getElementById('barchart_material'));
    }
    barChart.draw(dataTable, chartOptions);
}

// Function to draw profit and loss chart using Chart.js
// Function to draw profit and loss chart using Chart.js (Financial Year: April - March)
function drawProfitLossChart(data) {
    var ctx = document.getElementById('line_top_x'); // Ensure 'line_top_x' is a canvas element

    if (!ctx) {
        console.error("Canvas element 'line_top_x' not found.");
        return;
    }

    // Reorder labels for financial year: April to March
    const monthLabels = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];

    // Rearrange the data arrays to match the above month order
    function rearrangeFinancialYear(dataArray) {
        return [
            ...dataArray.slice(3), // April to December
            ...dataArray.slice(0, 3) // January to March
        ];
    }

    var chartData = {
        labels: monthLabels,
        datasets: [
            {
                label: 'Actual Revenue',
                data: rearrangeFinancialYear(data.actual_revenue),
                borderColor: '#28a745',
                fill: false
            },
            {
                label: 'Expenses',
                data: rearrangeFinancialYear(data.expenses),
                borderColor: '#dc3545',
                fill: false
            },
            {
                label: 'Profit',
                data: rearrangeFinancialYear(data.profit),
                borderColor: '#007bff',
                fill: false
            }
        ]
    };

    if (profitLossChart) {
        profitLossChart.destroy(); // Destroy the existing chart if it exists
    }

    profitLossChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Profit and Loss Summary for Financial Year: ' + data.year,
                    font: {
                        size: 13,
                        family: 'Arial',
                        weight: 'bold'
                    },
                    color: '#333'
                }
            },
            scales: {
                x: {
                    type: 'category',
                    labels: monthLabels,
                    ticks: {
                        color: '#333'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#333'
                    }
                }
            },
            layout: {
                padding: {
                    left: 10,
                    right: 10,
                    top: 10,
                    bottom: 10
                }
            },
            legend: {
                labels: {
                    color: '#333'
                }
            }
        }
    });

    // Set white background for the chart
    ctx.parentNode.style.backgroundColor = '#fff';
}
// When Google Charts library is loaded, execute the code inside the callback function
google.charts.setOnLoadCallback(function() {
    // Fetch data from API and populate dropdown
    $.ajax({
        url: 'action.php?read_chart=1',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log("Parsed Data:", data);
            if (!data || !data.pie_chart_data || !data.bar_chart_data || !data.profit_loss_data) {
                console.error("Invalid data received from API");
                return;
            }

            pieChartData = data.pie_chart_data;
            barChartData = data.bar_chart_data;
            profitLossData = data.profit_loss_data;

            populateDropdown();
        },
        error: function(xhr, status, error) {
            console.error("Error fetching chart data:", error);
        }
    });
});