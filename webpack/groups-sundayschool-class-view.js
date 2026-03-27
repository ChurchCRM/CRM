/**
 * Sunday School Class View — webpack entry
 * Handles DataTable initialization, ApexCharts birthday chart, and birthday filter.
 */

import ApexCharts from "apexcharts";
import "./groups-sundayschool-class-view.css";

document.addEventListener("DOMContentLoaded", () => {
  const dataTable = $(".data-table").DataTable(window.CRM.plugin.dataTable);

  // Birthday chart — data stored in data-chart attribute on the div element
  const chartElement = document.getElementById("bar-chart");
  if (!chartElement) return;

  const barData = JSON.parse(chartElement.dataset.chart || "[]");
  const barLabels = barData.map((d) => d[0]);
  const barValues = barData.map((d) => d[1]);
  const maxBarValue = barValues.length > 0 ? Math.max(...barValues) : 0;

  const barChartOptions = {
    chart: {
      type: "bar",
      height: 300,
      toolbar: {
        show: false,
      },
      events: {
        click: function (event, chartContext, opts) {
          if (opts.dataPointIndex !== undefined) {
            applyBirthdayFilter(barLabels[opts.dataPointIndex]);
          }
        },
      },
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        dataLabels: {
          position: "top",
        },
      },
    },
    series: [
      {
        name: chartElement.dataset.chartLabel || "Birthdays by Month",
        data: barValues,
      },
    ],
    xaxis: {
      categories: barLabels,
    },
    yaxis: {
      title: {
        text: i18next.t("Count"),
      },
      forceNiceScale: true,
    },
    colors: ["#9ec5de"],
    dataLabels: {
      enabled: false,
    },
    states: {
      hover: {
        filter: {
          type: "darken",
          value: 0.15,
        },
      },
    },
  };

  const barChart = new ApexCharts(chartElement, barChartOptions);
  barChart.render();
  window.barChart = barChart;

  // Birthday filter — click bar → filter DataTable by that month
  const birthDayFilter = document.querySelector(".birthday-filter");
  const monthLabel = birthDayFilter?.querySelector(".month");

  if (!birthDayFilter || !monthLabel) return;

  function applyBirthdayFilter(month) {
    dataTable.column(0).search(month).draw();
    birthDayFilter.classList.remove("d-none");
    monthLabel.textContent = month;
  }

  function hideBirthdayFilter() {
    dataTable.column(0).search("").draw();
    birthDayFilter.classList.add("d-none");
  }

  birthDayFilter.querySelector("i.fa-times")?.addEventListener("click", hideBirthdayFilter);

  // Remove student from class
  $(document).on("click", ".remove-from-class", function () {
    const $btn = $(this);
    const groupId = $btn.data("group-id");
    const personId = $btn.data("person-id");
    const personName = $btn.data("person-name");

    bootbox.confirm({
      message: i18next.t("Remove") + " <strong>" + personName + "</strong> " + i18next.t("from this class?"),
      buttons: {
        confirm: { label: i18next.t("Remove"), className: "btn-warning" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (!result) return;

        $.ajax({
          method: "DELETE",
          url: `${window.CRM.root}/api/groups/${groupId}/removeperson/${personId}`,
        })
          .done(() => {
            window.CRM.notify(i18next.t("Person removed from class."), { type: "success", delay: 3000 });
            dataTable.row($btn.closest("tr")).remove().draw();
          })
          .fail(() => {
            window.CRM.notify(i18next.t("Failed to remove from class. Please try again."), {
              type: "danger",
              delay: 5000,
            });
          });
      },
    });
  });
});
