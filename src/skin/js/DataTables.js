$(function () {
    // Automatically convert all data tables that have not already been converted.
    $("table.data-table:not(.dataTable)").dataTable();
});
