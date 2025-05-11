<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../index.php');
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>

  <!-- External CSS -->
  <link href="CSS/dashboard.css" rel="stylesheet" />
  <link rel="stylesheet" href="CSS/sidebar.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <!-- Replace FA6 CDN with FA4 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>


  <!-- Main Content -->

  <body>

    <?php include 'includes/sidebar.php'; ?>

    <di class="main-content">
      

      <div class="content-container">
        <!-- your charts and dashboard content here -->

        </div>
        <h2 class="dashboard">Financial Data</h1>

          <!-- Chart Section -->
          <div class="charts-row d-flex justify-content-center gap-2 flex-wrap">
            <div class="chart-container">
              <canvas id="expenseChart"></canvas>
            </div>
            <div class="chart-container">
              <canvas id="profitChart"></canvas>
            </div>
          </div>

          <h3 class="flooroccupancy">Occupancy Data
        </h2>

        <script>
          const ctxExpense = document.getElementById('expenseChart').getContext('2d');
          const expenseChart = new Chart(ctxExpense, {
            type: 'line',
            data: {
              labels: ['January', 'February', 'March', 'April'],
              datasets: [{
                label: 'Monthly Expenses',
                data: [500, 750, 620, 880],
                borderColor: '#2f8656',
                fill: false,
                tension: 0.3,
                pointBackgroundColor: 'rgba(75, 192, 192, 1)'
              }]
            },
            options: {
              responsive: true,
              plugins: {
                legend: { display: true, labels: { font: { size: 10 } } },
                tooltip: {
                  mode: 'index',
                  intersect: false,
                  titleFont: { size: 10 },
                  bodyFont: { size: 10 }
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  title: { display: true, text: 'Amount', font: { size: 10 } },
                  ticks: { font: { size: 10 } }
                },
                x: {
                  title: { display: true, text: 'Month', font: { size: 10 } },
                  ticks: { font: { size: 10 } }
                }
              }
            }
          });

          const ctxProfit = document.getElementById('profitChart').getContext('2d');
          const profitChart = new Chart(ctxProfit, {
            type: 'line',
            data: {
              labels: ['January', 'February', 'March', 'April'],
              datasets: [{
                label: 'Monthly Profit',
                data: [300, 600, 450, 720],
                borderColor: '#4287f5',
                fill: false,
                tension: 0.3,
                pointBackgroundColor: 'rgba(66, 135, 245, 1)'
              }]
            },
            options: {
              responsive: true,
              plugins: { legend: { display: true }, tooltip: { mode: 'index', intersect: false } },
              scales: { y: { beginAtZero: true, title: { display: true, text: 'Amount' } }, x: { title: { display: true, text: 'Month' } } }
            }
          });
        </script>
      </div>

      <!-- Floor Capacity Section -->
      <!-- Occupancy Pie Chart -->

      <!-- Floor Cards with Pie Charts -->
      <div class="charts-row d-flex justify-content-center gap-4 flex-wrap mt-4">
        <!-- First Floor -->
        <div class="floor-block card p-3 shadow-sm">
          <div class="floor-info mb-3">
            <h4 class="firstfloor">First Floor Capacity</h4>
            <p>Room 1: <strong>6 tenants</strong></p>
            <p>Room 2: <strong>6 tenants</strong></p>
            <p>Room 3: <strong>3 tenants</strong></p>
          </div>
          <div class="floor-chart-container">
            <canvas id="firstFloorChart"></canvas>
          </div>
        </div>

        <!-- Second Floor -->
        <div class="floor-block card p-3 shadow-sm">
          <div class="floor-info mb-3">
            <h4 class="secondfloor">Second Floor Capacity</h4>
            <p>Room 1: <strong>6 tenants</strong></p>
            <p>Room 2: <strong>10 tenants</strong></p>
          </div>
          <div class="floor-chart-container">
            <canvas id="secondFloorChart"></canvas>
          </div>
        </div>

        <!-- Third Floor -->
        <div class="floor-block card p-3 shadow-sm">
          <div class="floor-info mb-3">
            <h4 class="thirdfloor">Third Floor Capacity</h4>
            <p>Room 1: <strong>10 tenants</strong></p>
          </div>
          <div class="floor-chart-container">
            <canvas id="thirdFloorChart"></canvas>
          </div>
        </div>
      </div>



      <script>


        // Example data per floor
        const floorData = {
          first: { occupied: 15, total: 18 },
          second: { occupied: 16, total: 20 },
          third: { occupied: 10, total: 12 }
        };

        function createPieChart(canvasId, floor, title) {
          const occupied = floorData[floor].occupied;
          const total = floorData[floor].total;
          const vacant = total - occupied;

          new Chart(document.getElementById(canvasId).getContext('2d'), {
            type: 'pie',
            data: {
              labels: ['Occupied', 'Vacant'],
              datasets: [{
                data: [occupied, vacant],
                backgroundColor: ['#2f8656', '#e0e0e0'],
                borderColor: ['#2f8656', '#e0e0e0'],
                borderWidth: 1
              }]
            },
            options: {
              plugins: {
                title: {
                  display: true,
                  text: title,
                  font: { size: 16, weight: 'bold' },
                  padding: { top: 10, bottom: 10 }
                },
                legend: { position: 'bottom', labels: { font: { size: 12 } } },
                tooltip: {
                  callbacks: {
                    label: function (context) {
                      let label = context.label || '';
                      let value = context.raw || 0;
                      let percentage = ((value / total) * 100).toFixed(1);
                      return `${label}: ${value} (${percentage}%)`;
                    }
                  }
                }
              }
            }
          });
        }

        // Create Pie Charts for each floor
        createPieChart('firstFloorChart', 'first', 'First floor bed occupancy');
        createPieChart('secondFloorChart', 'second', 'Second floor bed occupancy');
        createPieChart('thirdFloorChart', 'third', 'Third floor bed occupancy');
      </script>

      </div>

      <!-- Bootstrap JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>

</html>