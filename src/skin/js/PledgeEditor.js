function formatCurrency(total) {
    var neg = false;
    if (total < 0) {
        neg = true;
        total = Math.abs(total);
    }
    return parseFloat(total, 10)
        .toFixed(2)
        .replace(/(\d)(?=(\d{3})+\.)/g, "$1,")
        .toString();
}

$("#FundSplit").on("change", function () {
    if (this.value == 0) {
        $("#FundSelection").show();
        $("#SingleComment").hide();
    } else {
        $("#FundSelection").hide();
        $("#SingleComment").show();
    }
});

$("#PaymentByMethod").on("change", function () {
    if (this.value === "CASH") {
        $("#CashEnter").show();
        $("#CheckEnter").hide();
        $("#grandTotal").prop("disabled", true);
    } else if (this.value === "CHECK") {
        $("#CashEnter").hide();
        $("#CheckEnter").show();
        $("#grandTotal").prop("disabled", false);
    } else {
        $("#CashEnter").hide();
        $("#CheckEnter").hide();
    }
});

$(".denominationInputBox").on("change", function () {
    var grandtotal = 0;
    $(".denominationInputBox").each(function (i, el) {
        var currencyvalue = $(el).attr("data-cur-value");
        var currencycount = $(el).val();
        grandtotal += currencyvalue * currencycount;
    });
    $("#grandTotal").val(formatCurrency(grandtotal));
});

$(".fundSplitInputBox").on("change", function () {
    var grandtotal = 0;
    $(".fundSplitInputBox").each(function (i, el) {
        var fundval = $(el).val();
        grandtotal += fundval * 1;
    });
    if (formatCurrency(grandtotal) === formatCurrency($("#grandTotal").val())) {
        console.log("split value is OK");
        $("#FundSelection .box-header h4").removeClass("fa fa-exclamation");
        $("#FundSelection .box-header h4").addClass("fa fa-check");
    } else {
        $("#FundSelection .box-header h4").removeClass("fa fa-check");
        $("#FundSelection .box-header h4").addClass("fa fa-exclamation");
    }
});

$(document).ready(function () {
    $("#MatchEnvelope").click(function () {
        console.log("matchenvelopecliked");
        $.ajax({
            type: "GET", // define the type of HTTP verb we want to use (POST for our form)
            url:
                "/api/families/byEnvelopeNumber/" +
                $("input[name=Envelope]").val(), // the url where we want to POST
            dataType: "json", // what type of data do we expect back from the server
            encode: true,
        }).done(function (data) {
            console.log(data);
            $("[name=FamilyName]").val(data.Name);
            $("[name=FamilyID]:eq(1)").val(data.fam_ID);
        });
    });

    $("#MatchFamily").click(function () {
        $.ajax({
            type: "GET", // define the type of HTTP verb we want to use (POST for our form)
            url:
                "/api/families/byCheckNumber/" +
                $("textarea[name=ScanInput]").val(), // the url where we want to POST
            dataType: "json", // what type of data do we expect back from the server
            encode: true,
        }).done(function (data) {
            console.log(data);
            $("[name=FamilyName]").val(data.fam_Name);
            $("[name=CheckNo]").val(data.CheckNumber);
        });
    });

    $("#SetDefaultCheck").click(function () {
        alert("Handler for find SetDefaultCheck clicked");
    });

    function getFundSubmitData() {
        var funds = new Array();
        if ($("select[name=FundSplit]").val() == "0") {
            $(".fundrow").each(function (i, el) {
                console.log($(this).attr("id"));
                var fundID = $(this).attr("id").split("_")[1];
                console.log(fundID);
                var amount = $("input[name=" + fundID + "_Amount]").val();
                var nondedamount = $(
                    "input[name=" + fundID + "_NonDeductible]",
                ).val();
                var comment = $("input[name=" + fundID + "_Comment]").val();
                var fundobjet = {
                    FundID: fundID,
                    Amount: amount,
                    NonDeductible: nondedamount,
                    Comment: comment,
                };
                funds.push(fundobjet);
            });
        } else {
            var fundobjet = {
                FundID: $("select[name=FundSplit]").val(),
                Comment: $("input[name=OneComment]").val(),
                Amount: $("input[name=TotalAmount]").val(),
            };
            funds.push(fundobjet);
        }
        return JSON.stringify(funds);
    }

    function setFundData(funds) {
        console.log("Fund Split Length: " + funds.length);
        if (funds.length > 1) {
            $("#FundSelection").show();
            $("#SingleComment").hide();
            $("select[name=FundSplit]").val(0);
            $.each(funds, function (index, fund) {
                $("input[name=" + fund.FundID + "_Amount]").val(fund.Amount);
                $("input[name=" + fund.FundID + "_NonDeductible]").val(
                    fund.NonDeductible,
                );
                $("input[name=" + fund.FundID + "_Comment]").val(fund.Comment);
            });
        } else {
            var fund = funds[0];
            $("#FundSelection").hide();
            $("select[name=FundSplit]").val(fund.FundID);
            $("#SingleComment").show();
            $("input[name=OneComment]").val(fund.Comment);
        }
    }

    function getDenominationSubmitData() {
        var denominations = new Array();
        $(".denominationInputBox").each(function (i, el) {
            var currencyObject = {
                currencyID: $(el).attr("Name").split("-")[1],
                Count: $(el).val(),
            };
            denominations.push(currencyObject);
        });
        return JSON.stringify(denominations);
    }

    function getSubmitFormData() {
        var fd = {
            FamilyID: $("[name=FamilyID]:eq(1)").val(),
            Date: $("input[name=Date]").val(),
            FYID: $("select[name=FYID]").val(),
            Envelope: $("input[name=Envelope]").val(),
            iMethod: $("select[name=Method]").val(),
            comment: $("input[name=OneComment]").val(),
            total: $("input[name=TotalAmount]").val(),
            DepositID: $("input[name=DepositID]").val(),
            type: $("input[name=PledgeOrPayment").val(),
        };
        if ($("select[name=Method]").val() === "CASH") {
            fd["cashDenominations"] = getDenominationSubmitData();
        }
        if ($("select[name=Method]").val() === "CHECK") {
            fd["iCheckNo"] = $("input[name=CheckNo]").val();
        }
        fd["FundSplit"] = getFundSubmitData();
        return fd;
    }

    $("#ResetForm").click(function () {
        resetForm();
    });

    function resetForm() {
        $("#CashEnter").hide();
        $("#CheckEnter").hide();
        $("#FundSelection").hide();
        $("select[name=FundSplit]").val("None");
        $("#SingleComment").show();
        $("[name=FamilyID]:eq(1)").val("");
        $("input[name=FamilyName]").val("");
        $("input[name=Date]").val("");
        $("select[name=FYID]").val("");
        $("input[name=Envelope]").val("");
        $("select[name=Method]").val("");
        $("input[name=OneComment]").val("");
        $("input[name=TotalAmount]").val("");
        $("input[name=DepositID]").val("");
    }

    function renderFormData(payment) {
        console.log("Rendering Payment Data: " + JSON.stringify(payment));
        if (payment.iMethod === "CASH") {
            $("#CashEnter").show();
            $("#CheckEnter").hide();
        } else {
            $("#CheckEnter").show();
            $("#CashEnter").hide();
        }
        setFundData(payment.funds);
        var family = JSON.parse(payment.Family);
        console.log(family.fam_ID);
        console.log(family.Name);
        $("[name=FamilyID]:eq(1)").val(family.fam_ID);
        $("input[name=FamilyName]").val(family.Name);
        $("input[name=Date]").val(payment.Date);
        $("select[name=FYID]").val(payment.FYID);
        $("input[name=Envelope]").val("");
        $("select[name=Method]").val(payment.iMethod);
        $("input[name=TotalAmount]").val(payment.total);
        $("input[name=DepositID]").val("");
    }

    $("#PledgeForm").submit(function (event) {
        event.preventDefault();
        var submitType = $("button[type=submit][clicked=true]").val();
        // get the form data
        // there are many ways to get this data using jQuery (you can use the class or id also)
        var formData = getSubmitFormData();

        console.log(formData);

        //process the form
        $.ajax({
            type: "POST", // define the type of HTTP verb we want to use (POST for our form)
            url: "api/payments", // the url where we want to POST
            data: JSON.stringify(formData), // our data object
            //dataType    : 'json', // what type of data do we expect back from the server
            encode: true,
        }).done(function (data) {
            var submitType = $("button[type=submit][clicked=true]").val();
            if (submitType === "Save") {
                window.location.href =
                    "DepositSlipEditor.php?DepositSlipID=" +
                    $("input[name=DepositID]").val();
            } else if ((submitType = "Save and Add")) {
                window.location.href = "PledgeEditor.php";
            }
        });
    });

    $("form button[type=submit]").click(function () {
        $("button[type=submit]", $(this).parents("form")).removeAttr("clicked");
        $(this).attr("clicked", "true");
    });

    if (typeof thisPayment != "undefined") {
        renderFormData(thisPayment);
    }

    $("#FamilyName").select2({
        minimumInputLength: 2,
        ajax: {
            url: function (params) {
                return "api/families/search/" + params.term;
            },
            dataType: "json",
            delay: 250,
            data: "",
            processResults: function (data, params) {
                var idKey = 1;
                var results = new Array();
                var groupName = Object.keys(data)[0];
                var ckeys = data[groupName];
                var resultGroup = {
                    id: idKey,
                    text: groupName,
                    children: [],
                };
                idKey++;
                var children = new Array();
                $.each(ckeys, function (ckey, cvalue) {
                    var childObject = {
                        id: idKey,
                        text: cvalue.displayName,
                        uri: cvalue.uri,
                        familyID: cvalue.id,
                    };
                    idKey++;
                    resultGroup.children.push(childObject);
                });
                results.push(resultGroup);
                return { results: results };
            },
            cache: false,
        },
    });
    $("#FamilyName").on("select2:select", function (e) {
        console.log(e);
        $("input[name=FamilyID]").val(e.params.data.familyID);
    });
});
