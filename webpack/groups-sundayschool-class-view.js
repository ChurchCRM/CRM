/**
 * Sunday School Class View — webpack entry
 * Handles DataTable initialization, Chart.js birthday chart, and birthday filter.
 */

import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from "chart.js";
import "./groups-sundayschool-class-view.css";

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

document.addEventListener("DOMContentLoaded", () => {
  const dataTable = $(".data-table").DataTable(window.CRM.plugin.dataTable);

  // Birthday chart — data stored in data-chart attribute on the canvas element
  const canvas = /** @type {HTMLCanvasElement|null} */ (document.getElementById("bar-chart"));
  if (!canvas) return;

  const barData = JSON.parse(canvas.dataset.chart || "[]");
  const barLabels = barData.map((d) => d[0]);
  const barValues = barData.map((d) => d[1]);
  const maxBarValue = barValues.length > 0 ? Math.max(...barValues) : 0;

  const barChart = new Chart(canvas, {
    type: "bar",
    data: {
      labels: barLabels,
      datasets: [
        {
          label: canvas.dataset.chartLabel || "Birthdays by Month",
          borderColor: "#3c8dbc",
          backgroundColor: "#9ec5de",
          borderWidth: 2,
          data: barValues,
        },
      ],
    },
    options: {
      scales: {
        y: {
          max: maxBarValue + 1,
          beginAtZero: true,
          ticks: { stepSize: 1 },
        },
      },
    },
  });

  window.barChart = barChart;

  // Birthday filter — click bar → filter DataTable by that month
  const birthDayFilter = document.querySelector(".birthday-filter");
  const monthLabel = birthDayFilter?.querySelector(".month");

  if (!birthDayFilter || !monthLabel) return;

  function hideBirthdayFilter() {
    dataTable.column(0).search("").draw();
    birthDayFilter.classList.add("d-none");
  }

  birthDayFilter.querySelector("i.fa-times")?.addEventListener("click", hideBirthdayFilter);

  canvas.addEventListener("click", (event) => {
    const points = barChart.getElementsAtEventForMode(event, "nearest", { intersect: true }, false);
    if (points.length === 0) {
      hideBirthdayFilter();
      return;
    }
    const month = barLabels[points[0].index];
    monthLabel.textContent = month;
    birthDayFilter.classList.remove("d-none");
  });
});
