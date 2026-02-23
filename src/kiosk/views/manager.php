<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
  <div class="col-lg-4 col-md-6 col-sm-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= gettext('Kiosk Manager') ?></h3>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label><?= gettext('Enable New Kiosk Registration') ?>:</label>
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="isNewKioskRegistrationActive">
            <label class="custom-control-label" for="isNewKioskRegistrationActive">
              <span id="kioskRegistrationStatus"><?= gettext('Inactive') ?></span>
            </label>
          </div>
          <small class="form-text text-muted"><?= gettext('When enabled, new kiosk devices can register for 30 seconds.') ?></small>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= gettext('Active Kiosks') ?></h3>
      </div>
      <div class="card-body">
        <table id="KioskTable" class="table table-striped table-bordered" style="width:100%">
        </table>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">

  // Initialize events storage - will be populated from API
  window.CRM.events = window.CRM.events || {};
  window.CRM.events.futureEvents = [];

  // Escape HTML to prevent XSS
  window.CRM.escapeHtml = window.CRM.escapeHtml || function(text) {
    if (text === null || text === undefined) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  };

  // Kiosk API helper for the new /kiosk/api endpoints
  window.CRM.kioskAPI = {
    request: function(path, method, data) {
      var options = {
        method: method || 'GET',
        headers: {
          'Content-Type': 'application/json'
        }
      };
      if (data) {
        options.body = JSON.stringify(data);
      }
      return fetch(window.CRM.root + '/kiosk/api' + path, options)
        .then(function(response) {
          return response.json();
        });
    },
    getDevices: function() {
      return this.request('/devices', 'GET');
    },
    enableRegistration: function() {
      return this.request('/allowRegistration', 'POST');
    },
    reload: function(id) {
      return this.request('/devices/' + id + '/reload', 'POST');
    },
    identify: function(id) {
      return this.request('/devices/' + id + '/identify', 'POST');
    },
    accept: function(id) {
      return this.request('/devices/' + id + '/accept', 'POST');
    },
    setAssignment: function(id, assignmentType, eventId) {
      return this.request('/devices/' + id + '/assignment', 'POST', {
        assignmentType: assignmentType,
        eventId: eventId
      });
    },
    delete: function(id) {
      return this.request('/devices/' + id, 'DELETE');
    }
  };

  function renderKioskAssignment(data) {
    if (data.Accepted) {
      var options = '<option value="None">' + i18next.t('None') + '</option>';
      var currentAssignment = (data.KioskAssignments && data.KioskAssignments.length > 0) ? data.KioskAssignments[0] : null;
      var currentEventId = currentAssignment ? currentAssignment.EventId : null;
      
      if (window.CRM.events && window.CRM.events.futureEvents && window.CRM.events.futureEvents.length > 0) {
          for (var i = 0; i < window.CRM.events.futureEvents.length; i++) {
              var event = window.CRM.events.futureEvents[i];
              var selected = (currentAssignment && currentAssignment.EventId === event.Id) ? 'selected' : '';
              
              // Build event label with group name if available
              var eventLabel = window.CRM.escapeHtml(event.Title);
              if (event.Groups && event.Groups.length > 0) {
                  var groupNames = event.Groups.map(function(g) { return g.Name; }).join(', ');
                  eventLabel += ' [' + window.CRM.escapeHtml(groupNames) + ']';
              }
              
              options += '<option ' + selected + ' value="1-' + event.Id + '">' + eventLabel + '</option>';
          }
      }
      
      var html = '<div class="d-flex align-items-center">';
      html += '<select class="assignmentMenu form-control form-control-sm mr-2" data-kioskid="' + data.Id + '">' + options + '</select>';
      
      // Add edit link if an event is assigned
      if (currentEventId) {
        html += '<a href="' + window.CRM.root + '/EventEditor.php?EID=' + currentEventId + '" class="btn btn-sm btn-outline-primary" title="' + i18next.t('Edit Event') + '">';
        html += '<i class="fas fa-edit"></i>';
        html += '</a>';
      }
      html += '</div>';
      
      return html;
    } else {
        return '<span class="text-muted">' + i18next.t('Kiosk must be accepted') + '</span>';
    }
  }

  $('#isNewKioskRegistrationActive').change(function() {
    if ($("#isNewKioskRegistrationActive").prop('checked')) {
      $("#kioskRegistrationStatus").text(i18next.t('Active'));
      window.CRM.kioskAPI.enableRegistration().then(function(data) {
        window.CRM.secondsLeft = moment(data.visibleUntil.date).unix() - moment().unix();
        window.CRM.discoverInterval = setInterval(function() {
          window.CRM.secondsLeft -= 1;
          if (window.CRM.secondsLeft > 0) {
            $("#kioskRegistrationStatus").text(i18next.t('Active for') + ' ' + window.CRM.secondsLeft + ' ' + i18next.t('seconds'));
          } else {
            clearInterval(window.CRM.discoverInterval);
            $('#isNewKioskRegistrationActive').prop('checked', false);
            $("#kioskRegistrationStatus").text(i18next.t('Inactive'));
          }
        }, 1000);
      });
    } else {
      $("#kioskRegistrationStatus").text(i18next.t('Inactive'));
    }
  });

  $(document).on("change", ".assignmentMenu", function(event) {
    var kioskId = $(event.currentTarget).data("kioskid");
    var selected = $(event.currentTarget).val();
    var assignmentSplit = selected.split("-");
    var assignmentType = assignmentSplit[0];
    var eventId = assignmentSplit.length > 1 ? assignmentSplit[1] : null;
    window.CRM.kioskAPI.setAssignment(kioskId, assignmentType, eventId);
  });

  function confirmDeleteKiosk(id, name) {
    bootbox.confirm({
      title: i18next.t("Delete Kiosk"),
      message: i18next.t("Are you sure you want to delete kiosk") + ': <strong>' + window.CRM.escapeHtml(name) + '</strong>?',
      buttons: {
        cancel: {
          label: '<i class="fas fa-times"></i> ' + i18next.t("Cancel")
        },
        confirm: {
          label: '<i class="fas fa-trash"></i> ' + i18next.t("Delete"),
          className: 'btn-danger'
        }
      },
      callback: function(result) {
        if (result) {
          window.CRM.kioskAPI.delete(id).then(function(data) {
            if (data.success) {
              window.CRM.notify(i18next.t("Kiosk deleted successfully"), { type: 'success' });
              window.CRM.kioskDataTable.ajax.reload();
            } else {
              window.CRM.notify(data.message || i18next.t("Failed to delete kiosk"), { type: 'error' });
            }
          }).catch(function(error) {
            window.CRM.notify(i18next.t("Failed to delete kiosk"), { type: 'error' });
          });
        }
      }
    });
  }

  // Load future events from API
  function loadFutureEvents() {
    return window.CRM.APIRequest({
      path: "events/",
      method: "GET"
    }).done(function(data) {
      // Filter to events that:
      // 1. Haven't ended yet (end date >= now)
      // 2. Have at least one linked group (required for kiosk check-in)
      var now = new Date();
      var events = data.Events || data;
      if (Array.isArray(events)) {
        window.CRM.events.futureEvents = events.filter(function(event) {
          var eventEnd = new Date(event.End);
          var hasGroup = event.Groups && event.Groups.length > 0;
          return eventEnd >= now && hasGroup;
        });
      }
    }).fail(function() {
      // If events fail to load, just use empty array
      window.CRM.events.futureEvents = [];
    });
  }

  $(document).ready(function() {
    // Load future events first, then initialize the DataTable
    loadFutureEvents().always(function() {
      initKioskTable();
    });
  });

  function initKioskTable() {
    var dataTableConfig = {
      ajax: {
        url: window.CRM.root + "/kiosk/api/devices",
        dataSrc: "KioskDevices",
        statusCode: {
          401: function(xhr, error, thrown) {
            window.location = window.location.origin + '/session/begin?location=' + window.location.pathname;
            return false;
          }
        }
      },
      columns: [
        {
          width: 'auto',
          title: i18next.t('Id'),
          data: 'Id',
          searchable: false
        },
        {
          width: 'auto',
          title: i18next.t('Kiosk Name'),
          data: 'Name'
        },
        {
          width: 'auto',
          title: i18next.t('Assignment'),
          data: function(row, type, set, meta) {
            if (row && row.KioskAssignments && row.KioskAssignments.length > 0) {
              return row.KioskAssignments[0];
            } else {
              return "None";
            }
          },
          render: function(data, type, full, meta) {
            return renderKioskAssignment(full);
          }
        },
        {
          width: 'auto',
          title: i18next.t('Last Heartbeat'),
          data: 'LastHeartbeat',
          render: function(data, type, full, meta) {
            if (full.LastHeartbeat) {
              return moment(full.LastHeartbeat).fromNow();
            }
            return i18next.t('Never');
          }
        },
        {
          width: 'auto',
          title: i18next.t('Accepted'),
          data: 'Accepted',
          render: function(data, type, full, meta) {
            if (full.Accepted) {
              return '<span class="badge badge-success">' + i18next.t('Yes') + '</span>';
            } else {
              return '<span class="badge badge-warning">' + i18next.t('No') + '</span>';
            }
          }
        },
        {
          width: 'auto',
          title: i18next.t('Actions'),
          data: null,
          defaultContent: '',
          orderable: false,
          render: function(data, type, full, meta) {
            var buttons = '<div class="btn-group btn-group-sm" role="group">';
            buttons += '<button class="btn btn-outline-primary" onclick="window.CRM.kioskAPI.reload(' + full.Id + ').then(function() { window.CRM.notify(i18next.t(\'Reload command sent\'), {type: \'success\'}); })" title="' + i18next.t('Reload') + '"><i class="fas fa-sync"></i></button>';
            buttons += '<button class="btn btn-outline-info" onclick="window.CRM.kioskAPI.identify(' + full.Id + ').then(function() { window.CRM.notify(i18next.t(\'Identify command sent\'), {type: \'success\'}); })" title="' + i18next.t('Identify') + '"><i class="fas fa-eye"></i></button>';
            if (!full.Accepted) {
              buttons += '<button class="btn btn-outline-success" onclick="window.CRM.kioskAPI.accept(' + full.Id + ').then(function() { window.CRM.kioskDataTable.ajax.reload(); window.CRM.notify(i18next.t(\'Kiosk accepted\'), {type: \'success\'}); })" title="' + i18next.t('Accept') + '"><i class="fas fa-check"></i></button>';
            }
            buttons += '<button class="btn btn-outline-danger" onclick="confirmDeleteKiosk(' + full.Id + ', \'' + window.CRM.escapeHtml(full.Name).replace(/'/g, "\\'") + '\')" title="' + i18next.t('Delete') + '"><i class="fas fa-trash"></i></button>';
            buttons += '</div>';
            return buttons;
          }
        }
      ]
    };

    $.extend(dataTableConfig, window.CRM.plugin.dataTable);

    window.CRM.kioskDataTable = $("#KioskTable").DataTable(dataTableConfig);

    setInterval(function() { window.CRM.kioskDataTable.ajax.reload(null, false); }, 30000);
  }

</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
