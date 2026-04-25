<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Dashboard Stat Cards -->
<div class="row row-cards mb-4 g-2" id="kioskStats">
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-primary text-white avatar rounded-circle"><i class="fa-solid fa-desktop icon"></i></span></div>
                    <div class="col"><div class="fw-medium" id="statTotal">0</div><div class="text-muted"><?= gettext('Total Kiosks') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-circle-check icon"></i></span></div>
                    <div class="col"><div class="fw-medium" id="statOnline">0</div><div class="text-muted"><?= gettext('Online') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-warning text-dark avatar rounded-circle"><i class="fa-solid fa-clock icon"></i></span></div>
                    <div class="col"><div class="fw-medium" id="statPending">0</div><div class="text-muted"><?= gettext('Pending') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-azure text-white avatar rounded-circle"><i class="fa-solid fa-calendar-check icon"></i></span></div>
                    <div class="col"><div class="fw-medium" id="statAssigned">0</div><div class="text-muted"><?= gettext('Assigned') ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Registration + Setup -->
<div class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-3 align-items-center">
      <div class="col-auto">
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="isNewKioskRegistrationActive">
          <label class="form-check-label visually-hidden" for="isNewKioskRegistrationActive"><?= gettext('Enable new kiosk registration') ?></label>
        </div>
      </div>
      <div class="col">
        <strong><?= gettext('Register New Device') ?></strong>
        <span class="text-muted ms-1"><?= gettext('Opens a 2-minute window for devices to register at:') ?></span>
        <code class="ms-1"><?= InputUtils::escapeHTML(SystemURLs::getURL()) ?>/kiosk</code>
      </div>
      <div class="col-auto">
        <span id="kioskRegistrationStatus" class="badge bg-secondary-lt text-secondary"><?= gettext('Inactive') ?></span>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3" id="eventsOverviewCard" style="display:none;">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">
      <i class="fa-solid fa-calendar-check me-2"></i><?= gettext('Upcoming Events') ?>
    </h3>
    <div class="card-options">
      <span class="badge bg-secondary-lt text-secondary" id="eventsOverviewCount"></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th><?= gettext('Event') ?></th>
          <th><?= gettext('Class / Group') ?></th>
          <th><?= gettext('Date & Time') ?></th>
          <th><?= gettext('Assigned Kiosk') ?></th>
          <th><?= gettext('Status') ?></th>
        </tr>
      </thead>
      <tbody id="eventsOverviewBody"></tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">
      <i class="fa-solid fa-desktop me-2"></i><?= gettext('Kiosk Devices') ?>
    </h3>
  </div>
  <div class="table-responsive">
    <table id="KioskTable" class="table table-vcenter table-hover card-table">
    </table>
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
    },
    rename: function(id, name) {
      return this.request('/devices/' + id + '/name', 'POST', { name: name });
    }
  };

  function renameKiosk(id, currentName) {
    bootbox.prompt({
      title: i18next.t('Rename Kiosk'),
      value: currentName,
      callback: function(result) {
        if (result === null) return;
        var name = result.trim();
        if (!name) {
          window.CRM.notify(i18next.t('Name cannot be empty'), { type: 'error' });
          return;
        }
        window.CRM.kioskAPI.rename(id, name).then(function(data) {
          if (data.success) {
            window.CRM.notify(i18next.t('Kiosk renamed'), { type: 'success' });
            window.CRM.kioskDataTable.ajax.reload(null, false);
          } else {
            window.CRM.notify(data.message || i18next.t('Failed to rename kiosk'), { type: 'error' });
          }
        }).catch(function() {
          window.CRM.notify(i18next.t('Failed to rename kiosk'), { type: 'error' });
        });
      }
    });
  }

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
      html += '<select class="assignmentMenu form-select form-select-sm me-2" data-kioskid="' + data.Id + '">' + options + '</select>';
      
      // Add edit link if an event is assigned
      if (currentEventId) {
        html += '<a href="' + window.CRM.root + '/event/editor/' + currentEventId + '" class="btn btn-sm btn-outline-primary" title="' + i18next.t('Edit Event') + '">';
        html += '<i class="fa-solid fa-pen-to-square"></i>';
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
      $("#kioskRegistrationStatus").text(i18next.t('Active')).removeClass('bg-secondary-lt text-secondary').addClass('bg-success-lt text-success');
      if (window.CRM.discoverInterval) {
        clearInterval(window.CRM.discoverInterval);
      }
      window.CRM.kioskAPI.enableRegistration().then(function(data) {
        window.CRM.secondsLeft = moment(data.visibleUntil).unix() - moment().unix();
        window.CRM.discoverInterval = setInterval(function() {
          window.CRM.secondsLeft -= 1;
          if (window.CRM.secondsLeft > 0) {
            var minsLeft = Math.max(1, Math.round(window.CRM.secondsLeft / 60));
            $("#kioskRegistrationStatus").text(i18next.t('Active for {{count}} min', { count: minsLeft })).removeClass('bg-secondary-lt text-secondary').addClass('bg-success-lt text-success');
          } else {
            clearInterval(window.CRM.discoverInterval);
            $('#isNewKioskRegistrationActive').prop('checked', false);
            $("#kioskRegistrationStatus").text(i18next.t('Inactive')).removeClass('bg-success-lt text-success').addClass('bg-secondary-lt text-secondary');
          }
        }, 1000);
      }).catch(function() {
        if (window.CRM.discoverInterval) {
          clearInterval(window.CRM.discoverInterval);
        }
        $('#isNewKioskRegistrationActive').prop('checked', false);
        $("#kioskRegistrationStatus").text(i18next.t('Inactive')).removeClass('bg-success-lt text-success').addClass('bg-secondary-lt text-secondary');
        window.CRM.notify(i18next.t('Failed to enable kiosk registration'), { type: 'error' });
      });
    } else {
      if (window.CRM.discoverInterval) {
        clearInterval(window.CRM.discoverInterval);
      }
      $("#kioskRegistrationStatus").text(i18next.t('Inactive')).removeClass('bg-success-lt text-success').addClass('bg-secondary-lt text-secondary');
    }
  });

  $(document).on("change",".assignmentMenu", function(event) {
    var kioskId = $(event.currentTarget).data("kioskid");
    var selected = $(event.currentTarget).val();
    var assignmentSplit = selected.split("-");
    var assignmentType = assignmentSplit[0];
    var eventId = assignmentSplit.length > 1 ? assignmentSplit[1] : null;
    window.CRM.kioskAPI.setAssignment(kioskId, assignmentType, eventId).then(function() {
      window.CRM.kioskDataTable.ajax.reload(null, false);
    });
  });

  function confirmDeleteKiosk(id, name) {
    bootbox.confirm({
      title: i18next.t("Delete Kiosk"),
      message: i18next.t("Are you sure you want to delete kiosk") + ': <strong>' + window.CRM.escapeHtml(name) + '</strong>?',
      buttons: {
        cancel: {
          label: '<i class="fa-solid fa-times"></i> ' + i18next.t("Cancel")
        },
        confirm: {
          label: '<i class="fa-solid fa-trash"></i> ' + i18next.t("Delete"),
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
      path:"events/",
      method:"GET"
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

  function renderEventsOverview(events, kiosks) {
    if (!events || events.length === 0) {
      $('#eventsOverviewCard').hide();
      return;
    }

    var now = new Date();
    var tbody = $('#eventsOverviewBody');
    tbody.empty();

    events.forEach(function(event) {
      // Find accepted kiosk assigned to this event
      var assignedKiosk = null;
      kiosks.forEach(function(kiosk) {
        if (kiosk.Accepted && kiosk.KioskAssignments && kiosk.KioskAssignments.length > 0) {
          if (parseInt(kiosk.KioskAssignments[0].EventId, 10) === event.Id) {
            assignedKiosk = kiosk;
          }
        }
      });

      var start = new Date(event.Start);
      var end = new Date(event.End);
      var isActive = now >= start && now < end;

      var statusBadge = isActive
        ? '<span class="badge bg-success-lt text-success"><i class="fa-solid fa-circle-dot me-1"></i>' + i18next.t('Active Now') + '</span>'
        : '<span class="badge bg-blue-lt text-blue">' + i18next.t('Upcoming') + '</span>';

      var kioskCell = assignedKiosk
        ? '<span class="d-flex align-items-center"><i class="fa-solid fa-desktop text-secondary me-2"></i>' + window.CRM.escapeHtml(assignedKiosk.Name) + '</span>'
        : '<span class="badge bg-warning text-dark">' + i18next.t('Not assigned') + '</span>';

      var groupNames = event.Groups.map(function(g) { return window.CRM.escapeHtml(g.Name); }).join(', ');
      var dateStr = moment(event.Start).format('ddd MMM D, h:mm A') + '–' + moment(event.End).format('h:mm A');

      tbody.append(
        '<tr>' +
        '<td class="fw-medium">' + window.CRM.escapeHtml(event.Title) + '</td>' +
        '<td class="text-secondary">' + groupNames + '</td>' +
        '<td class="text-secondary text-nowrap">' + dateStr + '</td>' +
        '<td>' + kioskCell + '</td>' +
        '<td>' + statusBadge + '</td>' +
        '</tr>'
      );
    });

    var label = events.length === 1 ? i18next.t('event') : i18next.t('events');
    $('#eventsOverviewCount').text(events.length + ' ' + label);
    $('#eventsOverviewCard').show();
  }

  $(document).ready(function() {
    var eventsLoaded = new Promise(function(resolve) {
      loadFutureEvents().always(resolve);
    });

    var kiosksLoaded = window.CRM.kioskAPI.getDevices()
      .then(function(data) { return (data && data.KioskDevices) ? data.KioskDevices : []; })
      .catch(function() { return []; });

    Promise.all([eventsLoaded, kiosksLoaded]).then(function(results) {
      renderEventsOverview(window.CRM.events.futureEvents, results[1]);
      initKioskTable();
    });
  });

  // Update dashboard stat cards from kiosk data
  function updateDashboardStats(kiosks) {
    var total = kiosks ? kiosks.length : 0;
    var pending = 0;
    var online = 0;
    var assigned = 0;
    var now = moment();
    (kiosks || []).forEach(function(k) {
      if (!k.Accepted) pending++;
      if (k.LastHeartbeat && now.diff(moment(k.LastHeartbeat), 'minutes') < 5) online++;
      if (k.Accepted && k.KioskAssignments && k.KioskAssignments.length > 0 && k.KioskAssignments[0].EventId) assigned++;
    });
    $('#statTotal').text(total);
    $('#statOnline').text(online);
    $('#statPending').text(pending);
    $('#statAssigned').text(assigned);
  }

  function initKioskTable() {
    var dataTableConfig = {
      ajax: {
        url: window.CRM.root +"/kiosk/api/devices",
        dataSrc:"KioskDevices",
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
          title: i18next.t('Status'),
          data: 'LastHeartbeat',
          searchable: false,
          render: function(data, type, full, meta) {
            var isOnline = full.LastHeartbeat && moment().diff(moment(full.LastHeartbeat), 'minutes') < 5;
            if (!full.Accepted) {
              return '<span class="badge bg-warning text-dark">' + i18next.t('Pending') + '</span>';
            }
            if (isOnline) {
              return '<span class="badge bg-success">' + i18next.t('Online') + '</span>';
            }
            return '<span class="badge bg-secondary-lt">' + i18next.t('Offline') + '</span>';
          }
        },
        {
          width: 'auto',
          title: i18next.t('Kiosk Name'),
          data: 'Name',
          render: function(data, type, full, meta) {
            var heartbeat = full.LastHeartbeat ? moment(full.LastHeartbeat).fromNow() : i18next.t('Never');
            return '<div><span class="fw-medium">' + window.CRM.escapeHtml(data) + '</span></div>' +
                   '<div class="text-muted small">' + i18next.t('Last seen') + ': ' + heartbeat + '</div>';
          }
        },
        {
          width: 'auto',
          title: i18next.t('Assignment'),
          data: function(row, type, set, meta) {
            if (row && row.KioskAssignments && row.KioskAssignments.length > 0) {
              return row.KioskAssignments[0];
            } else {
              return"None";
            }
          },
          render: function(data, type, full, meta) {
            return renderKioskAssignment(full);
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
            if (!full.Accepted) {
              buttons += '<button class="btn btn-success" onclick="window.CRM.kioskAPI.accept(' + full.Id + ').then(function() { window.CRM.kioskDataTable.ajax.reload(); window.CRM.notify(i18next.t(\'Kiosk accepted\'), {type: \'success\'}); })" title="' + i18next.t('Accept') + '"><i class="fa-solid fa-check me-1"></i>' + i18next.t('Accept') + '</button>';
            }
            buttons += '<button class="btn btn-outline-secondary" onclick="renameKiosk(' + full.Id + ', \'' + window.CRM.escapeHtml(full.Name).replace(/'/g, "\\'") + '\')" title="' + i18next.t('Rename') + '"><i class="fa-solid fa-pen"></i></button>';
            buttons += '<button class="btn btn-outline-primary" onclick="window.CRM.kioskAPI.reload(' + full.Id + ').then(function() { window.CRM.notify(i18next.t(\'Reload command sent\'), {type: \'success\'}); })" title="' + i18next.t('Reload') + '"><i class="fa-solid fa-sync"></i></button>';
            buttons += '<button class="btn btn-outline-info" onclick="window.CRM.kioskAPI.identify(' + full.Id + ').then(function() { window.CRM.notify(i18next.t(\'Identify command sent\'), {type: \'success\'}); })" title="' + i18next.t('Identify') + '"><i class="fa-solid fa-eye"></i></button>';
            buttons += '<button class="btn btn-outline-danger" onclick="confirmDeleteKiosk(' + full.Id + ', \'' + window.CRM.escapeHtml(full.Name).replace(/'/g,"\\'") + '\')" title="' + i18next.t('Delete') + '"><i class="fa-solid fa-trash"></i></button>';
            buttons += '</div>';
            return buttons;
          }
        }
      ]
    };

    dataTableConfig.drawCallback = function() {
      var kiosks = window.CRM.kioskDataTable
        ? window.CRM.kioskDataTable.rows().data().toArray()
        : [];
      renderEventsOverview(window.CRM.events.futureEvents, kiosks);
      updateDashboardStats(kiosks);
    };

    $.extend(dataTableConfig, window.CRM.plugin.dataTable);

    window.CRM.kioskDataTable = $("#KioskTable").DataTable(dataTableConfig);

    setInterval(function() { window.CRM.kioskDataTable.ajax.reload(null, false); }, 10000);
  }

</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
