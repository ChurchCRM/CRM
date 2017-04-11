var lineData = {
    labels: [],
    datasets: [
        {
            data: []
        }
    ]
};


$( document ).ready(function() {
$.each(lineDataRaw.Deposits, function(i, val) {
    lineData.labels.push(moment(val.Date).format("MM-DD-YY"));
    lineData.datasets[0].data.push(val.totalAmount);
});
options = {
  responsive:true,
  maintainAspectRatio:false
};
var lineChartCanvas = $("#deposit-lineGraph").get(0).getContext("2d");
var lineChart = new Chart(lineChartCanvas).Line(lineData,options);

});