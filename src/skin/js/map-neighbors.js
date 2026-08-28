/**
 * map-neighbors.js — Find nearest-neighbor families to a selected family.
 *
 * Reads window.CRM.mapNeighborsConfig (set by people/views/map-neighbors-view.php)
 * and fetches results from GET /api/map/neighbors/{familyId}.
 */
$(document).ready(() => {
  window.CRM.onLocalesReady(() => {
    var cfg = window.CRM.mapNeighborsConfig;
    var listPeople = [];

    $(".choiceSelectBox").each(function () {
      if (!this.tomselect) new TomSelect(this);
    });

    function setResultsVisible(hasResults) {
      $("#neighborsEmpty").toggleClass("d-none", hasResults);
      $("#neighborsTable").toggleClass("d-none", !hasResults);
      $("#addAllToCart, #removeAllFromCart").prop("disabled", !hasResults);
    }

    function renderResults(families) {
      var $tbody = $("#neighborsTableBody").empty();
      listPeople = [];

      families.forEach((family) => {
        family.people.forEach((person) => {
          listPeople.push(person.id);
        });

        var peopleHtml = family.people
          .map(
            (person) =>
              '<span class="badge bg-secondary-lt text-secondary me-1 mb-1">' +
              window.CRM.escapeHtml(person.name) +
              "</span>",
          )
          .join("");

        var $row = $("<tr>");
        $row.append($("<td>").text(family.distanceText));
        $row.append(
          $("<td>").html(
            window.CRM.escapeHtml(family.bearing) +
              ' <a target="_blank" rel="noopener noreferrer" href="' +
              window.CRM.escapeHtml(family.directionsUrl) +
              '">' +
              i18next.t("Directions") +
              "</a>",
          ),
        );
        $row.append(
          $("<td>").html(
            '<a href="' +
              window.CRM.escapeHtml(family.profileUrl) +
              '"><strong>' +
              window.CRM.escapeHtml(family.name) +
              '</strong></a><br><span class="text-secondary">' +
              window.CRM.escapeHtml(family.address) +
              "</span>",
          ),
        );
        $row.append($("<td>").html(peopleHtml));
        $tbody.append($row);
      });

      setResultsVisible(families.length > 0);
    }

    $("#neighborsForm").on("submit", (e) => {
      e.preventDefault();

      var familyId = $("#familySelect").val();
      if (!familyId) {
        window.CRM.notify(i18next.t("Please select a family."), { type: "warning" });
        return;
      }

      var classificationIds = $('input[name="classificationId"]:checked')
        .map(function () {
          return $(this).val();
        })
        .get()
        .join(",");

      var params = new URLSearchParams({
        maxNeighbors: $("#maxNeighbors").val() || 15,
        maxDistance: $("#maxDistance").val() || 10,
        classificationIds: classificationIds,
      });

      var $btn = $("#findNeighborsBtn").prop("disabled", true);

      fetch(cfg.apiUrl + "/" + encodeURIComponent(familyId) + "?" + params.toString(), {
        credentials: "same-origin",
      })
        .then((res) => {
          if (!res.ok) {
            throw new Error("API error " + res.status);
          }
          return res.json();
        })
        .then(renderResults)
        .catch((err) => {
          console.error("Find Neighbors: failed to load results", err);
          window.CRM.notify(i18next.t("Failed to load neighbors."), { type: "danger" });
          listPeople = [];
          setResultsVisible(false);
        })
        .finally(() => {
          $btn.prop("disabled", false);
        });
    });

    $("#addAllToCart").on("click", () => {
      window.CRM.cartManager.addPerson(listPeople, { showNotification: true });
    });

    $("#removeAllFromCart").on("click", () => {
      window.CRM.cartManager.removePerson(listPeople, { confirm: true, showNotification: true });
    });
  });
});
