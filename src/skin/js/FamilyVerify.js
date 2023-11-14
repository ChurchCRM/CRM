$(document).ready(function () {
    $("#onlineVerifySiteBtn").hide();
    $("#confirm-modal-done").hide();
    $("#confirm-modal-error").hide();

    const doShowMap = LatLng !== null;

    // When the window has finished loading google map
    if (doShowMap) {
        google.maps.event.addDomListener(window, "load", init);
    }

    function init() {
        // Options for Google map
        // More info see: https://developers.google.com/maps/documentation/javascript/reference#MapOptions
        if (doShowMap) {
            var mapOptions1 = {
                zoom: 14,
                center: LatLng,
                scrollwheel: false,
                disableDefaultUI: true,
                draggable: false,
                // Style for Google Maps
                styles: [
                    {
                        featureType: "landscape",
                        stylers: [
                            { saturation: -100 },
                            { lightness: 65 },
                            { visibility: "on" },
                        ],
                    },
                    {
                        featureType: "poi",
                        stylers: [
                            { saturation: -100 },
                            { lightness: 51 },
                            { visibility: "simplified" },
                        ],
                    },
                    {
                        featureType: "road.highway",
                        stylers: [
                            { saturation: -100 },
                            { visibility: "simplified" },
                        ],
                    },
                    {
                        featureType: "road.arterial",
                        stylers: [
                            { saturation: -100 },
                            { lightness: 30 },
                            { visibility: "on" },
                        ],
                    },
                    {
                        featureType: "road.local",
                        stylers: [
                            { saturation: -100 },
                            { lightness: 40 },
                            { visibility: "on" },
                        ],
                    },
                    {
                        featureType: "transit",
                        stylers: [
                            { saturation: -100 },
                            { visibility: "simplified" },
                        ],
                    },
                    {
                        featureType: "administrative.province",
                        stylers: [{ visibility: "off" }],
                    },
                    {
                        featureType: "water",
                        elementType: "labels",
                        stylers: [
                            { visibility: "on" },
                            { lightness: -25 },
                            { saturation: -100 },
                        ],
                    },
                    {
                        featureType: "water",
                        elementType: "geometry",
                        stylers: [
                            { hue: "#ffff00" },
                            { lightness: -25 },
                            { saturation: -97 },
                        ],
                    },
                ],
            };

            // Get all html elements for map
            var mapElement1 = document.getElementById("map1");

            // Create the Google Map using elements
            var map1 = new google.maps.Map(mapElement1, mapOptions1);

            marker = new google.maps.Marker({ position: LatLng, map: map1 });
        }
    }

    $("#onlineVerifyBtn").click(function () {
        var formData = "";
        if ($("input[type='radio']:checked").val() === "change-needed") {
            formData = $("#confirm-info-data").val();
        }

        alert(formData);

        $.post(
            window.CRM.root + "/external/verify/" + token,
            {
                message: formData,
            },
            function (data, status) {
                $("#confirm-modal-collect").hide();
                $("#onlineVerifyCancelBtn").hide();
                $("#onlineVerifyBtn").hide();
                $("#onlineVerifySiteBtn").show();
                if (status === "success") {
                    $("#confirm-modal-done").show();
                } else {
                    $("#confirm-modal-error").show();
                }
            },
        );
    });
});
