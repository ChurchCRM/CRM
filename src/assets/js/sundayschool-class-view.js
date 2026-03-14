/**
 * Sunday School Class View - Interactive Features
 * Handles student table interactions and quick statistics display
 */

export function initializeSundaySchoolClassView() {
  // Birthday filter functionality for student table
  const birthDayFilter = $(".birthday-filter");
  const dataTable = $(".data-table").DataTable();

  function hideBirthDayFilter() {
    dataTable.column(":contains(Birth Date)").search("").draw();

    birthDayFilter.hide();
  }

  // Clear filter when X is clicked
  birthDayFilter.find("i.fa-times").on("click", hideBirthDayFilter);
}
