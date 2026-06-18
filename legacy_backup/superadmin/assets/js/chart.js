// Load Google Charts library for other charts
if (typeof google !== 'undefined' && google.charts) {
    google.charts.load("current", { packages: ["corechart", "bar"] });
} else {
    console.error("Google Charts library not loaded. Please ensure the Google Charts script is included before this file.");
}

// Declare chart variables outside the function scope
var pieChart;
var barChart;
var pieChartData = [];
var barChartData = [];
var profitLossData = [];
var currentChartYear = null;
var resizeTimer;

function debounce(fn, delay) {
    return function () {
        var context = this;
        var args = arguments;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            fn.apply(context, args);
        }, delay);
    };
}

// Function to populate dropdown and draw charts
function populateDropdown() {
    var dropdown = $('#year_select');
    dropdown.empty(); // Clear previous options

    // Extract years from either pieChartData or barChartData
    var years = pieChartData.map(item => item.year);
    years.sort(); // Sort in ascending order
    years.reverse(); // Reverse to have most recent year first

    // Populate hidden native select
    years.forEach(function (year) {
        dropdown.append($('<option>').val(year).text(year));
    });

    // Set the default year to the most recent year
    var defaultYear = years[0];
    dropdown.val(defaultYear);

    // ---------- Custom dropdown logic ----------
    var wrapper  = document.getElementById('yearDropdownWrapper');
    var toggle   = document.getElementById('yearDropdownToggle');
    var menu     = document.getElementById('yearDropdownMenu');
    var selected = document.getElementById('yearDropdownSelected');

    if (wrapper && toggle && menu && selected) {
        // Build option items
        menu.innerHTML = '';
        years.forEach(function (year) {
            var item = document.createElement('div');
            item.className = 'custom-dropdown-item' + (year === defaultYear ? ' active' : '');
            item.setAttribute('data-value', year);
            item.textContent = year;
            menu.appendChild(item);
        });

        // Show default value in toggle
        selected.textContent = defaultYear || 'Select Year';

        // Toggle open / close
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            wrapper.classList.toggle('open');
        });

        // Select an option
        menu.addEventListener('click', function (e) {
            var item = e.target.closest('.custom-dropdown-item');
            if (!item) return;
            var value = item.getAttribute('data-value');

            // Update active class
            menu.querySelectorAll('.custom-dropdown-item').forEach(function (el) {
                el.classList.remove('active');
            });
            item.classList.add('active');

            // Update display + hidden select
            selected.textContent = value;
            dropdown.val(value).trigger('change');

            // Close menu
            wrapper.classList.remove('open');
        });

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('open');
            }
        });
    }
    // ---------- End custom dropdown logic ----------

    // Event listener for hidden select change (keeps compatibility)
    dropdown.on('change', function () {
        var selectedYear = $(this).val();
        console.log('Year changed to:', selectedYear);
        fetchDataAndDrawCharts(selectedYear);
    });

    // Initial chart drawing
    fetchDataAndDrawCharts(defaultYear);
}

// Function to fetch data for selected year and draw charts
function fetchDataAndDrawCharts(selectedYear) {
    console.log('Year changed to:', selectedYear);
    currentChartYear = selectedYear;
    
    // Find data for the selected year from both datasets
    var pieChartSelectedData = pieChartData.find(item => item.year === selectedYear);
    var barChartSelectedData = barChartData.find(item => item.year === selectedYear);
    var profitLossSelectedData = profitLossData.find(item => item.year === selectedYear);

    // Update revenue stats cards
    updateRevenueStats(barChartSelectedData);

    // Draw pie chart
    drawPieChart(pieChartSelectedData);

    // Draw bar chart
    drawBarChart(barChartSelectedData);

    // Draw profit and loss chart using Chart.js
    drawProfitLossChart(profitLossSelectedData);
    
    // Reload analytics charts for the new year
    console.log('Reloading analytics for year:', selectedYear);
    loadAnalyticsCharts(selectedYear);
}

// Function to format currency in compact format
function formatCurrency(value) {
    if (value >= 10000000) {
        return '₹' + (value / 10000000).toFixed(2) + ' Cr';
    } else if (value >= 100000) {
        return '₹' + (value / 100000).toFixed(2) + ' L';
    } else if (value >= 1000) {
        return '₹' + (value / 1000).toFixed(2) + ' K';
    } else {
        return '₹' + value.toFixed(2);
    }
}

// Function to update revenue stats cards
function updateRevenueStats(data) {
    if (!data) {
        $('#total-revenue-value').text('₹0');
        $('#actual-revenue-value').text('₹0');
        $('#received-amount-value').text('₹0');
        return;
    }
    
    // Update the revenue cards with formatted values
    var totalRevenue = parseInt(data.total_revenue || 0);
    var actualRevenue = parseInt(data.actual_revenue || 0);
    var receivedRevenue = parseInt(data.received_revenue || 0);
    
    $('#total-revenue-value').text(formatCurrency(totalRevenue));
    $('#actual-revenue-value').text(formatCurrency(actualRevenue));
    $('#received-amount-value').text(formatCurrency(receivedRevenue));
}

var redrawCharts = debounce(function () {
    if (!currentChartYear || !pieChartData.length) {
        return;
    }

    var pieChartSelectedData = pieChartData.find(item => item.year === currentChartYear);
    var barChartSelectedData = barChartData.find(item => item.year === currentChartYear);
    var profitLossSelectedData = profitLossData.find(item => item.year === currentChartYear);

    if (barChartSelectedData) {
        updateRevenueStats(barChartSelectedData);
    }
    if (pieChartSelectedData) {
        drawPieChart(pieChartSelectedData);
    }
    if (barChartSelectedData) {
        drawBarChart(barChartSelectedData);
    }
    if (profitLossSelectedData) {
        drawProfitLossChart(profitLossSelectedData);
    }
}, 180);

window.addEventListener('resize', redrawCharts);

// Function to draw pie chart
function drawPieChart(data) {
    if (!data || typeof google === 'undefined' || !google.visualization) {
        return;
    }

    var chartData = [
        ['Task', 'Count'],
        ['Received', parseInt(data.received_count)],
        ['Canceled', parseInt(data.canceled_count)],
        ['Processing', parseInt(data.processing_count)]
    ];

    // Calculate total for center display
    var total = parseInt(data.received_count) + parseInt(data.canceled_count) + parseInt(data.processing_count);

    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var textColor = isDarkMode ? '#ffffff' : '#0f1720';

    var chartOptions = {
        title: 'Status Distribution for Year: ' + data.year,
        pieHole: 0.6,
        slices: {
            0: { color: '#10b981' }, // Emerald green
            1: { color: '#f97316' }, // Orange
            2: { color: '#eab308' }  // Yellow
        },
        legend: {
            position: 'bottom',
            textStyle: { color: textColor, fontSize: 13 },
            alignment: 'center'
        },
        pieSliceText: 'none',
        pieSliceTextStyle: {
            color: 'transparent' // Hide text on slices
        },
        chartArea: { width: '90%', height: '75%' },
        backgroundColor: 'transparent',
        titleTextStyle: { fontSize: 16, bold: true, color: textColor },
        tooltip: {
            text: 'both',
            textStyle: {
                fontSize: 13
            }
        },
        pieSliceBorderColor: 'transparent',
        enableInteractivity: true
    };

    var dataTable = google.visualization.arrayToDataTable(chartData);

    if (!pieChart) {
        pieChart = new google.visualization.PieChart(document.getElementById('piechart_3d'));
    }

    pieChart.draw(dataTable, chartOptions);

    // Add center text showing total
    setTimeout(function () {
        var chartDiv = document.getElementById('piechart_3d');
        var svg = chartDiv.querySelector('svg');
        if (!svg) return;

        // Remove existing center text if any
        var existingText = svg.querySelector('#centerText');
        if (existingText) {
            existingText.remove();
        }

        // Calculate center position
        var svgRect = svg.getBoundingClientRect();
        var centerX = svgRect.width / 2;
        var centerY = (svgRect.height / 2) - 10;

        // Create text group
        var textGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        textGroup.setAttribute('id', 'centerText');

        // Total count (large)
        var totalText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        totalText.setAttribute('x', centerX);
        totalText.setAttribute('y', centerY);
        totalText.setAttribute('text-anchor', 'middle');
        totalText.setAttribute('dominant-baseline', 'middle');
        totalText.setAttribute('fill', textColor);
        totalText.setAttribute('font-size', '32');
        totalText.setAttribute('font-weight', 'bold');
        totalText.textContent = total.toLocaleString();

        // Label "Total" (smaller)
        var labelText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        labelText.setAttribute('x', centerX);
        labelText.setAttribute('y', centerY + 28);
        labelText.setAttribute('text-anchor', 'middle');
        labelText.setAttribute('dominant-baseline', 'middle');
        labelText.setAttribute('fill', isDarkMode ? '#9ca3af' : '#64748b');
        labelText.setAttribute('font-size', '14');
        labelText.textContent = 'Total';

        textGroup.appendChild(totalText);
        textGroup.appendChild(labelText);
        svg.appendChild(textGroup);
    }, 100);
}
// Function to draw Bar CHart
function drawBarChart(data) {
    if (!data || typeof google === 'undefined' || !google.visualization) {
        return;
    }
    
    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var textColor = isDarkMode ? '#ffffff' : '#0f1720';
    
    var chartData = [
        ['Year', 'Total Revenue', 'Actual Revenue', 'Received Amount'],
        [data.year, parseInt(data.total_revenue || 0), parseInt(data.actual_revenue || 0), parseInt(data.received_revenue || 0)]
    ];
    var chartOptions = {
        title: 'Company Performance for Year: ' + data.year,
        bars: 'vertical', // Ensure bars are displayed vertically
        colors: ['#667eea', '#10b981', '#3b82f6'],
        bar: { groupWidth: '55%' },
        backgroundColor: 'transparent',
        legend: {
            position: 'bottom',
            textStyle: { color: textColor, fontSize: 12 }
        },
        chartArea: { width: '80%', height: '70%' },
        titleTextStyle: { fontSize: 16, bold: true, color: textColor },
        vAxis: {
            minValue: 0,
            gridlines: { color: 'rgba(15,23,42,0.08)' },
            baselineColor: 'rgba(15,23,42,0.2)',
            textStyle: { color: textColor },
            format: 'short'
        },
        hAxis: {
            textStyle: { color: textColor }
        }
    };
    var dataTable = google.visualization.arrayToDataTable(chartData);
    if (!barChart) {
        barChart = new google.visualization.BarChart(document.getElementById('barchart_material'));
    }
    barChart.draw(dataTable, chartOptions);
}


// Function to draw profit and loss chart using ApexCharts (Financial Year: April - March)
var profitLossChart = null;

function drawProfitLossChart(data) {
    if (!data) {
        return;
    }

    var container = document.getElementById('line_top_x');
    if (!container) {
        console.error("Container element 'line_top_x' not found.");
        return;
    }

    // Check if ApexCharts is loaded
    if (typeof ApexCharts === 'undefined') {
        console.error("ApexCharts not loaded");
        return;
    }

    // Month labels for financial year: April to March
    var monthLabels = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];

    function rearrangeFinancialYear(dataArray) {
        if (!Array.isArray(dataArray)) {
            return [];
        }
        return [
            dataArray.slice(3), // April to December
            dataArray.slice(0, 3) // January to March
        ].flat();
    }

    // Prepare chart data
    var actualRevenue = rearrangeFinancialYear(data.actual_revenue);
    var expenses = rearrangeFinancialYear(data.expenses);
    var profit = rearrangeFinancialYear(data.profit);

    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var textColor = isDarkMode ? '#ffffff' : '#0f1720';
    var legendColor = isDarkMode ? '#ffffff' : '#0f1720';

    var options = {
        series: [
            {
                name: 'Actual Revenue',
                data: actualRevenue,
                color: '#10b981'
            },
            {
                name: 'Expenses',
                data: expenses,
                color: '#f97316'
            },
            {
                name: 'Profit',
                data: profit,
                color: '#2563eb'
            }
        ],
        chart: {
            type: 'line',
            width: '100%',
            height: 380,
            fontFamily: 'Inter, Arial, sans-serif',
            foreColor: textColor,
            toolbar: {
                show: true,
                tools: {
                    download: true,
                    selection: false,
                    zoom: false,
                    zoomin: false,
                    zoomout: false,
                    pan: false,
                    reset: false
                }
            },
            background: '#daeaea',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800,
                animateGradually: {
                    enabled: true,
                    delay: 150
                },
                dynamicAnimation: {
                    enabled: true,
                    speed: 350
                }
            }
        },
        title: {
            text: 'Profit & Loss Summary (FY ' + data.year + ')',
            align: 'center',
            margin: 24,
            offsetY: 0,
            floating: false,
            style: {
                fontSize: '0.8rem',
                fontWeight: 'bold',
                color: textColor
            }
        },
        stroke: {
            width: 5,
            curve: 'smooth'
        },
        xaxis: {
            categories: monthLabels,
            labels: {
                style: {
                    colors: textColor,
                    fontSize: '12px'
                },
                rotate: -45,
                rotateAlways: false
            },
            axisBorder: {
                show: true,
                color: 'rgba(15, 23, 42, 0.2)'
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: textColor,
                    fontSize: '12px'
                },
                formatter: function (value) {
                    if (value >= 1000000) return '₹' + (value / 1000000).toFixed(1) + 'M';
                    if (value >= 1000) return '₹' + (value / 1000).toFixed(1) + 'K';
                    return '₹' + value;
                }
            }
        },
        grid: {
            borderColor: 'rgba(15, 23, 42, 0.08)',
            strokeDashArray: 3,
            xaxis: {
                lines: {
                    show: false
                }
            },
            yaxis: {
                lines: {
                    show: true
                }
            },
            padding: {
                top: 0,
                right: 5,
                bottom: 0,
                left: 5
            }
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '12px',
            fontWeight: 500,
            labels: {
                colors: legendColor
            },
            markers: {
                width: 12,
                height: 12,
                radius: 2
            },
            itemMargin: {
                horizontal: 16,
                vertical: 8
            }
        },
        tooltip: {
            enabled: true,
            shared: true,
            intersect: false,
            theme: isDarkMode ? 'dark' : 'light',
            style: {
                fontSize: '12px'
            },
            y: {
                formatter: function (value) {
                    return '₹' + value.toLocaleString('en-IN');
                }
            },
            marker: {
                show: true
            }
        },
        markers: {
            size: 4,
            strokeWidth: 2,
            strokeColors: '#fff',
            hover: {
                size: 6
            }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 100]
            }
        },
        responsive: [
            {
                breakpoint: 768,
                options: {
                    chart: {
                        height: 350
                    },
                    title: {
                        style: {
                            fontSize: '0.75rem'
                        }
                    },
                    xaxis: {
                        labels: {
                            style: {
                                fontSize: '10px'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                fontSize: '10px'
                            }
                        }
                    },
                    legend: {
                        fontSize: '11px'
                    }
                }
            },
            {
                breakpoint: 480,
                options: {
                    chart: {
                        height: 300
                    },
                    title: {
                        style: {
                            fontSize: '0.65rem'
                        },
                        margin: 15
                    },
                    xaxis: {
                        labels: {
                            style: {
                                fontSize: '9px'
                            },
                            rotate: -45,
                            rotateAlways: true
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                fontSize: '9px'
                            }
                        }
                    },
                    legend: {
                        fontSize: '10px',
                        itemMargin: {
                            horizontal: 8,
                            vertical: 6
                        }
                    },
                    grid: {
                        padding: {
                            left: 0,
                            right: 0
                        }
                    }
                }
            },
            {
                breakpoint: 375,
                options: {
                    chart: {
                        height: 280
                    },
                    title: {
                        style: {
                            fontSize: '0.6rem'
                        },
                        margin: 12
                    },
                    xaxis: {
                        labels: {
                            style: {
                                fontSize: '8px'
                            },
                            rotate: -45,
                            rotateAlways: true
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                fontSize: '8px'
                            }
                        }
                    },
                    legend: {
                        fontSize: '9px',
                        itemMargin: {
                            horizontal: 6,
                            vertical: 5
                        }
                    },
                    grid: {
                        padding: {
                            left: 0,
                            right: 0,
                            top: 0,
                            bottom: 0
                        }
                    },
                    stroke: {
                        width: 4
                    }
                }
            },
            {
                breakpoint: 320,
                options: {
                    chart: {
                        height: 260
                    },
                    title: {
                        style: {
                            fontSize: '0.55rem'
                        },
                        margin: 10
                    },
                    xaxis: {
                        labels: {
                            style: {
                                fontSize: '7px'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                fontSize: '7px'
                            }
                        }
                    },
                    legend: {
                        fontSize: '8px'
                    },
                    stroke: {
                        width: 3
                    }
                }
            }
        ]
    };

    // Destroy previous chart if exists
    if (profitLossChart) {
        profitLossChart.destroy();
    }

    // Clear container and create new chart
    container.innerHTML = '';
    profitLossChart = new ApexCharts(container, options);
    profitLossChart.render();
}
// When Google Charts library is loaded, execute the code inside the callback function
if (typeof google !== 'undefined' && google.charts) {
    google.charts.setOnLoadCallback(function () {
        // Fetch data from API and populate dropdown
        $.ajax({
            url: 'action.php?read_chart=1',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                console.log("Parsed Data:", data);
                if (!data || !data.pie_chart_data || !data.bar_chart_data || !data.profit_loss_data) {
                    console.error("Invalid data received from API");
                    return;
                }

                pieChartData = data.pie_chart_data;
                barChartData = data.bar_chart_data;
                profitLossData = data.profit_loss_data;

                populateDropdown();
                
                // Initialize analytics charts after main charts are loaded
                initializeAnalyticsCharts();
            },
            error: function (xhr, status, error) {
                console.error("Error fetching chart data:", error);
                console.error("Status:", status);
                console.error("Response:", xhr.responseText);
                // Check if response is HTML (PHP error) instead of JSON
                if (xhr.responseText && xhr.responseText.trim().startsWith('<')) {
                    console.error("Server returned HTML instead of JSON. This usually indicates a PHP error. Check action.php for errors.");
                }
            }
        });
    });
} else {
    console.error("Google Charts library not available. Charts will not be initialized.");
}

// ============================================
// ANALYTICS CHARTS SECTION - CLEAN REWRITE
// ============================================

var bookingStatusChart = null;
var bookingTrendChart = null;
var leadSourceChart = null;
var leadFlowApexChart = null;
var analyticsChartsInitialized = false;

// Store analytics data for redrawing
var analyticsData = {
    booking_status: null,
    booking_trend: null,
    lead_source: null,
    lead_flow: null
};

// Load analytics data and draw charts
function loadAnalyticsCharts(selectedYear) {
    console.log('Loading analytics charts for year:', selectedYear);
    
    $.ajax({
        url: 'action.php?read_analytics=1&year=' + encodeURIComponent(selectedYear),
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            console.log('Analytics data received:', response);
            
            // Store the data for later use
            analyticsData.booking_status = response.booking_status_data;
            analyticsData.booking_trend = response.booking_trend_data;
            analyticsData.lead_source = response.lead_source_data;
            analyticsData.lead_flow = response.lead_flow_data;
            
            // Get current active tab
            var activeTab = window.currentAnalyticsTab || 'bookings';
            
            // Only draw charts for the active tab
            if (activeTab === 'bookings') {
                // Draw booking status chart
                if (response.booking_status_data && Object.keys(response.booking_status_data).length > 0) {
                    drawBookingStatus(response.booking_status_data);
                } else {
                    showNoData('booking_status_chart', 'No booking status data');
                }
                
                // Draw booking trend chart
                if (response.booking_trend_data && response.booking_trend_data.length > 0) {
                    drawBookingTrend(response.booking_trend_data);
                } else {
                    showNoData('booking_trend_chart', 'No booking trend data');
                }
            } else if (activeTab === 'leads') {
                // Draw lead source chart
                if (response.lead_source_data && Object.keys(response.lead_source_data).length > 0) {
                    drawLeadSource(response.lead_source_data);
                } else {
                    showNoData('lead_source_chart', 'No lead source data');
                }
                
                // Draw lead flow chart
                if (response.lead_flow_data && response.lead_flow_data.length > 0) {
                    drawLeadFlow(response.lead_flow_data);
                } else {
                    showNoData('lead_flow_chart', 'No lead flow data');
                }
            }
            
            analyticsChartsInitialized = true;
            console.log('Analytics charts drawn for active tab:', activeTab);
        },
        error: function (xhr, status, error) {
            console.error('Error loading analytics:', error);
            showNoData('booking_status_chart', 'Error loading data');
            showNoData('booking_trend_chart', 'Error loading data');
            showNoData('lead_source_chart', 'Error loading data');
            showNoData('lead_flow_chart', 'Error loading data');
        }
    });
}

// Show "no data" message in container
function showNoData(containerId, message) {
    var container = document.getElementById(containerId);
    if (container) {
        var isDarkMode = document.body.classList.contains('dark-mode');
        var textColor = isDarkMode ? '#ffffff' : '#64748b';
        container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 350px; color: ' + textColor + '; font-size: 14px;">' + message + ' for selected year</div>';
    }
}

// Draw Booking Status Chart
function drawBookingStatus(data) {
    var container = document.getElementById('booking_status_chart');
    if (!container) return;
    
    var chartData = [['Status', 'Count']];
    var hasData = false;
    
    for (var status in data) {
        var count = parseInt(data[status]) || 0;
        if (count > 0) hasData = true;
        chartData.push([status, count]);
    }
    
    if (!hasData) {
        showNoData('booking_status_chart', 'No booking status data');
        return;
    }
    
    container.innerHTML = '';
    
    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var textColor = isDarkMode ? '#ffffff' : '#0f1720';
    
    var options = {
        title: 'Booking Status Distribution',
        pieHole: 0.4,
        colors: ['#10b981', '#eab308', '#f97316', '#3b82f6', '#8b5cf6'],
        legend: { 
            position: 'bottom',
            textStyle: { color: textColor, fontSize: 13 }
        },
        chartArea: { width: '90%', height: '75%' },
        backgroundColor: 'transparent',
        titleTextStyle: { fontSize: 16, bold: true, color: textColor }
    };
    
    var dataTable = google.visualization.arrayToDataTable(chartData);
    bookingStatusChart = new google.visualization.PieChart(container);
    bookingStatusChart.draw(dataTable, options);
}

// Draw Booking Trend Chart
function drawBookingTrend(data) {
    var container = document.getElementById('booking_trend_chart');
    if (!container) return;
    
    var chartData = [['Month', 'Bookings']];
    var hasData = false;
    
    data.forEach(function(item) {
        var count = parseInt(item.count) || 0;
        if (count > 0) hasData = true;
        chartData.push([item.month, count]);
    });
    
    if (!hasData) {
        showNoData('booking_trend_chart', 'No booking trend data');
        return;
    }
    
    container.innerHTML = '';
    
    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var textColor = isDarkMode ? '#ffffff' : '#0f1720';
    
    var options = {
        title: 'Monthly Bookings Trend',
        curveType: 'function',
        legend: { 
            position: 'bottom',
            textStyle: { color: textColor, fontSize: 13 }
        },
        chartArea: { width: '85%', height: '70%' },
        backgroundColor: 'transparent',
        titleTextStyle: { fontSize: 16, bold: true, color: textColor },
        colors: ['#667eea'],
        hAxis: { 
            slantedText: true, 
            slantedTextAngle: 45,
            textStyle: { color: textColor }
        },
        vAxis: {
            textStyle: { color: textColor }
        }
    };
    
    var dataTable = google.visualization.arrayToDataTable(chartData);
    bookingTrendChart = new google.visualization.LineChart(container);
    bookingTrendChart.draw(dataTable, options);
}

// Draw Lead Source Chart
function drawLeadSource(data) {
    var container = document.getElementById('lead_source_chart');
    if (!container) return;
    
    var chartData = [['Source', 'Count']];
    var hasData = false;
    
    for (var source in data) {
        var count = parseInt(data[source]) || 0;
        if (count > 0) hasData = true;
        chartData.push([source, count]);
    }
    
    if (!hasData) {
        showNoData('lead_source_chart', 'No lead source data');
        return;
    }
    
    container.innerHTML = '';
    
    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var textColor = isDarkMode ? '#ffffff' : '#0f1720';
    
    var options = {
        title: 'Lead Source Distribution',
        pieHole: 0.4,
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
        legend: { 
            position: 'bottom',
            textStyle: { color: textColor, fontSize: 13 }
        },
        chartArea: { width: '90%', height: '75%' },
        backgroundColor: 'transparent',
        titleTextStyle: { fontSize: 16, bold: true, color: textColor }
    };
    
    var dataTable = google.visualization.arrayToDataTable(chartData);
    leadSourceChart = new google.visualization.PieChart(container);
    leadSourceChart.draw(dataTable, options);
}

// Draw Lead Flow Chart
function drawLeadFlow(data) {
    var container = document.getElementById('lead_flow_chart');
    if (!container) return;
    
    var categories = [];
    var pendingData = [];
    var followupData = [];
    var sitevisitData = [];
    var convertedData = [];
    var lostData = [];
    var hasData = false;
    
    data.forEach(function(item) {
        categories.push(item.month);
        var pending = parseInt(item.pending) || 0;
        var followup = parseInt(item.followup) || 0;
        var sitevisit = parseInt(item.sitevisit) || 0;
        var converted = parseInt(item.converted) || 0;
        var lost = parseInt(item.lost) || 0;
        
        if (pending > 0 || followup > 0 || sitevisit > 0 || converted > 0 || lost > 0) {
            hasData = true;
        }
        
        pendingData.push(pending);
        followupData.push(followup);
        sitevisitData.push(sitevisit);
        convertedData.push(converted);
        lostData.push(lost);
    });
    
    if (!hasData) {
        showNoData('lead_flow_chart', 'No lead flow data');
        return;
    }
    
    if (leadFlowApexChart) {
        leadFlowApexChart.destroy();
        leadFlowApexChart = null;
    }
    
    container.innerHTML = '';
    
    // Detect dark mode for text colors
    var isDarkMode = document.body.classList.contains('dark-mode');
    var titleColor = isDarkMode ? '#ffffff' : '#0f1720';
    var legendColor = isDarkMode ? '#ffffff' : '#333333';
    var axisLabelColor = isDarkMode ? '#e5e7eb' : '#666666';
    var gridColor = isDarkMode ? '#374151' : '#e7e7e7';
    
    var options = {
        series: [
            { name: 'Pending', data: pendingData },
            { name: 'Follow-up', data: followupData },
            { name: 'Site Visit', data: sitevisitData },
            { name: 'Converted', data: convertedData },
            { name: 'Lost', data: lostData }
        ],
        chart: {
            type: 'line',
            height: 350,
            toolbar: { show: true },
            background: 'transparent',
            foreColor: axisLabelColor
        },
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
        stroke: { width: 3, curve: 'smooth' },
        dataLabels: { enabled: false },
        title: {
            text: 'Monthly Lead Flow',
            align: 'center',
            style: { fontSize: '16px', fontWeight: 'bold', color: titleColor }
        },
        xaxis: {
            categories: categories,
            labels: { 
                rotate: -45, 
                rotateAlways: true, 
                style: { 
                    fontSize: '11px',
                    colors: categories.map(function() { return axisLabelColor; })
                } 
            }
        },
        yaxis: { 
            title: { 
                text: 'Lead Count', 
                style: { color: axisLabelColor } 
            }, 
            min: 0, 
            labels: { 
                style: { 
                    colors: [axisLabelColor]
                } 
            } 
        },
        legend: { 
            position: 'bottom', 
            horizontalAlign: 'center', 
            fontSize: '13px', 
            labels: { 
                colors: [legendColor, legendColor, legendColor, legendColor, legendColor]
            } 
        },
        tooltip: {
            shared: true,
            intersect: false,
            theme: isDarkMode ? 'dark' : 'light',
            y: { formatter: function(value) { return value + ' leads'; } }
        },
        grid: { borderColor: gridColor, strokeDashArray: 4 },
        theme: {
            mode: isDarkMode ? 'dark' : 'light'
        },
        responsive: [
            {
                breakpoint: 768,
                options: {
                    chart: { height: 300 },
                    legend: { fontSize: '11px' },
                    xaxis: { labels: { style: { fontSize: '9px' } } }
                }
            },
            {
                breakpoint: 480,
                options: {
                    chart: { height: 280 },
                    legend: { fontSize: '10px' },
                    xaxis: { labels: { style: { fontSize: '8px' } } }
                }
            }
        ]
    };
    
    leadFlowApexChart = new ApexCharts(container, options);
    leadFlowApexChart.render();
}

// Initialize analytics on first load
function initializeAnalyticsCharts() {
    if (analyticsChartsInitialized) {
        console.log('Analytics already initialized');
        return;
    }
    
    // Wait for containers to exist
    var checkContainers = setInterval(function() {
        var container1 = document.getElementById('booking_status_chart');
        var container2 = document.getElementById('booking_trend_chart');
        var container3 = document.getElementById('lead_source_chart');
        var container4 = document.getElementById('lead_flow_chart');
        
        if (container1 && container2 && container3 && container4) {
            clearInterval(checkContainers);
            var selectedYear = $('#year_select').val() || '';
            console.log('Initializing analytics charts for year:', selectedYear);
            loadAnalyticsCharts(selectedYear);
        }
    }, 100);
    
    // Timeout after 5 seconds
    setTimeout(function() {
        clearInterval(checkContainers);
    }, 5000);
}

// Function to redraw only the charts for the currently active analytics tab
function redrawActiveAnalyticsCharts() {
    // Get the current active tab (this is set from dashboard.php)
    var activeTab = window.currentAnalyticsTab || 'bookings';
    
    console.log('Redrawing charts for active tab:', activeTab);
    
    // Use setTimeout to ensure the tab content is visible before redrawing
    setTimeout(function() {
        if (activeTab === 'bookings') {
            // Draw booking charts using stored data
            if (analyticsData.booking_status && Object.keys(analyticsData.booking_status).length > 0) {
                drawBookingStatus(analyticsData.booking_status);
            } else {
                showNoData('booking_status_chart', 'No booking status data');
            }
            
            if (analyticsData.booking_trend && analyticsData.booking_trend.length > 0) {
                drawBookingTrend(analyticsData.booking_trend);
            } else {
                showNoData('booking_trend_chart', 'No booking trend data');
            }
        } else if (activeTab === 'leads') {
            // Draw lead charts using stored data
            if (analyticsData.lead_source && Object.keys(analyticsData.lead_source).length > 0) {
                drawLeadSource(analyticsData.lead_source);
            } else {
                showNoData('lead_source_chart', 'No lead source data');
            }
            
            if (analyticsData.lead_flow && analyticsData.lead_flow.length > 0) {
                drawLeadFlow(analyticsData.lead_flow);
            } else {
                showNoData('lead_flow_chart', 'No lead flow data');
            }
        }
    }, 150);
}

// Redraw analytics charts on window resize
window.addEventListener('resize', debounce(function() {
    if (analyticsChartsInitialized) {
        var selectedYear = $('#year_select').val() || '';
        loadAnalyticsCharts(selectedYear);
    }
}, 300));

// Watch for dark mode changes and redraw charts
var themeObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
            // Dark mode changed, redraw all charts
            console.log('Theme changed, redrawing charts');
            
            // Redraw main charts if data is available
            if (currentChartYear && pieChartData.length > 0) {
                var pieChartSelectedData = pieChartData.find(item => item.year === currentChartYear);
                var barChartSelectedData = barChartData.find(item => item.year === currentChartYear);
                var profitLossSelectedData = profitLossData.find(item => item.year === currentChartYear);
                
                if (pieChartSelectedData) drawPieChart(pieChartSelectedData);
                if (barChartSelectedData) {
                    updateRevenueStats(barChartSelectedData);
                    drawBarChart(barChartSelectedData);
                }
                if (profitLossSelectedData) drawProfitLossChart(profitLossSelectedData);
            }
            
            // Redraw analytics charts
            if (analyticsChartsInitialized) {
                setTimeout(function() {
                    redrawActiveAnalyticsCharts();
                }, 100);
            }
        }
    });
});

// Start observing body for class changes (wait for DOM if needed)
function setupThemeObserver() {
    if (document.body) {
        themeObserver.observe(document.body, {
            attributes: true,
            attributeFilter: ['class']
        });
        console.log('Theme observer initialized');
    } else {
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            if (document.body) {
                themeObserver.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
                console.log('Theme observer initialized after DOMContentLoaded');
            }
        });
    }
}

setupThemeObserver();
