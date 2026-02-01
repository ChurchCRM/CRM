<?php

namespace ChurchCRM;

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Service\AttendanceService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;

AuthenticationManager::redirectHomeIfNotAdmin();

$logger = LoggerUtils::getAppLogger();
$sPageTitle = gettext('Attendance CSV Import');

require_once __DIR__ . '/Include/Header.php';

$attendanceService = new AttendanceService();
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Import Attendance Data') ?></h3>
    </div>
    <div class="card-body">
        
        <?php
        $iStage = 1;
        $csvError = '';
        $importResults = null;
        
        // Is the CSV file being uploaded?
        if (isset($_POST['UploadCSV'])) {
            // Check if a valid CSV file was actually uploaded
            if (empty($_FILES['CSVfile']['name'])) {
                $csvError = gettext('No file selected for upload.');
            } else {
                // Valid file, so save it and display the import mapping form.
                $csvTempFile = tempnam(sys_get_temp_dir(), 'attendance_csv_');
                $_SESSION['attendanceCsvTempFile'] = $csvTempFile;
                move_uploaded_file($_FILES['CSVfile']['tmp_name'], $csvTempFile);
                
                // create the file pointer
                $pFile = fopen($csvTempFile, 'r');
                
                // count # lines in the file
                $iNumRows = 0;
                while ($tmp = fgets($pFile, 2048)) {
                    $iNumRows++;
                }
                rewind($pFile);
                
                echo '<h4>' . gettext('CSV File Preview') . '</h4>';
                echo '<p>' . gettext('Total number of rows in the CSV file') . ': ' . $iNumRows . '</p>';
                
                // create the form
                ?>
                <form method="post" action="AttendanceCSVImport.php">
                    
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr class="table-primary">
                                <th><?= gettext('Column Data Preview') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        // grab and display up to the first 8 lines of data in the CSV in a table
                        $iRow = 0;
                        while (($aData = fgetcsv($pFile, 2048, ',')) && $iRow++ < 9) {
                            $numCol = count($aData);
                            
                            echo '<tr>';
                            for ($col = 0; $col < $numCol; $col++) {
                                echo '<td>' . InputUtils::escapeHTML($aData[$col]) . '&nbsp;</td>';
                            }
                            echo '</tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                    
                    <?php
                    fclose($pFile);
                    
                    // Re-open file to read header for column mapping
                    $pFile = fopen($csvTempFile, 'r');
                    $aHeader = fgetcsv($pFile, 2048, ',');
                    fclose($pFile);
                    $numCol = count($aHeader);
                    ?>
                    
                    <h5 class="mt-4"><?= gettext('Column Mapping') ?></h5>
                    <p><?= gettext('Map each CSV column to the corresponding field') ?></p>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= gettext('CSV Column Header') ?></th>
                                <th><?= gettext('Maps To') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        for ($col = 0; $col < $numCol; $col++) {
                            // Auto-detect column mapping based on header name
                            $headerLower = strtolower($aHeader[$col] ?? '');
                            $defaultMapping = 0; // Ignore
                            
                            if (preg_match('/member.*id|person.*id|id/i', $headerLower)) {
                                $defaultMapping = 1; // Person ID
                            } elseif (preg_match('/date/i', $headerLower) && !preg_match('/time/i', $headerLower)) {
                                $defaultMapping = 2; // Date
                            } elseif (preg_match('/time/i', $headerLower) && !preg_match('/date/i', $headerLower)) {
                                $defaultMapping = 3; // Time
                            } elseif (preg_match('/datetime/i', $headerLower)) {
                                $defaultMapping = 4; // DateTime
                            }
                            ?>
                            <tr>
                                <td><strong><?= InputUtils::escapeHTML($aHeader[$col] ?? 'Column ' . ($col + 1)) ?></strong></td>
                                <td>
                                    <select name="col<?= $col ?>" class="form-control">
                                        <option value="0" <?= $defaultMapping === 0 ? 'selected' : '' ?>><?= gettext('Ignore this Field') ?></option>
                                        <option value="1" <?= $defaultMapping === 1 ? 'selected' : '' ?>><?= gettext('Person ID (Member ID)') ?></option>
                                        <option value="2" <?= $defaultMapping === 2 ? 'selected' : '' ?>><?= gettext('Date') ?></option>
                                        <option value="3" <?= $defaultMapping === 3 ? 'selected' : '' ?>><?= gettext('Time') ?></option>
                                        <option value="4" <?= $defaultMapping === 4 ? 'selected' : '' ?>><?= gettext('Date & Time Combined') ?></option>
                                    </select>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" value="1" name="IgnoreFirstRow" checked>
                            <?= gettext('Ignore first CSV row (header row)') ?>
                        </label>
                    </div>
                    
                    <div class="form-group mt-4">
                        <label for="EventSelection"><strong><?= gettext('Associate Attendance With Event') ?></strong></label>
                        <select name="EventId" id="EventSelection" class="form-control" required>
                            <option value=""><?= gettext('-- Select Event --') ?></option>
                            <optgroup label="<?= gettext('Create New Event') ?>">
                                <option value="new:Sunday Service"><?= gettext('Sunday Service (New)') ?></option>
                                <option value="new:Fellowship Group"><?= gettext('Fellowship Group (New)') ?></option>
                                <option value="new:Other"><?= gettext('Other (New)') ?></option>
                            </optgroup>
                            <optgroup label="<?= gettext('Existing Events') ?>">
                                <?php
                                $events = EventQuery::create()
                                    ->filterByInactive(0)
                                    ->orderByTitle()
                                    ->find();
                                foreach ($events as $event) {
                                    echo '<option value="' . $event->getId() . '">' . 
                                         InputUtils::escapeHTML($event->getTitle()) . 
                                         ' (' . $event->getStart('Y-m-d') . ')' .
                                         '</option>';
                                }
                                ?>
                            </optgroup>
                        </select>
                        <small class="form-text text-muted">
                            <?= gettext('Select an existing event or create a new recurring event for attendance tracking.') ?>
                        </small>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="NewEventTitle" id="NewEventTitleLabel" style="display:none;">
                            <strong><?= gettext('New Event Title') ?></strong>
                        </label>
                        <input type="text" name="NewEventTitle" id="NewEventTitle" class="form-control" 
                               placeholder="<?= gettext('Enter event title') ?>" style="display:none;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" name="DoImport">
                        <i class="fa fa-upload"></i> <?= gettext('Import Attendance Data') ?>
                    </button>
                    <a href="AttendanceCSVImport.php" class="btn btn-secondary">
                        <i class="fa fa-times"></i> <?= gettext('Cancel') ?>
                    </a>
                </form>
                
                <script>
                    document.getElementById('EventSelection').addEventListener('change', function() {
                        var newEventTitle = document.getElementById('NewEventTitle');
                        var newEventTitleLabel = document.getElementById('NewEventTitleLabel');
                        
                        if (this.value.startsWith('new:')) {
                            newEventTitle.style.display = 'block';
                            newEventTitleLabel.style.display = 'block';
                            newEventTitle.required = true;
                            
                            // Pre-fill with suggestion
                            var suggestion = this.value.split(':')[1];
                            if (suggestion === 'Other') {
                                newEventTitle.value = '';
                            } else {
                                newEventTitle.value = suggestion;
                            }
                        } else {
                            newEventTitle.style.display = 'none';
                            newEventTitleLabel.style.display = 'none';
                            newEventTitle.required = false;
                        }
                    });
                </script>
                
                <?php
                $iStage = 2;
            }
        }
        
        // Has the import form been submitted?
        if (isset($_POST['DoImport'])) {
            // Get the temp filename stored in the session
            $csvTempFile = $_SESSION['attendanceCsvTempFile'] ?? null;
            
            if (!$csvTempFile || !is_file($csvTempFile)) {
                $csvError = gettext('CSV file not found. Please upload again.');
            } else {
                // Process the import
                $pFile = fopen($csvTempFile, 'r');
                
                // Get the number of columns
                $aData = fgetcsv($pFile, 2048, ',');
                $numCol = count($aData);
                
                // Skip first row if requested
                if (!isset($_POST['IgnoreFirstRow'])) {
                    rewind($pFile);
                }
                
                // Build column mapping array
                $columnMapping = [];
                for ($col = 0; $col < $numCol; $col++) {
                    $columnMapping[$col] = (int)$_POST['col' . $col];
                }
                
                // Get or create event
                $eventIdInput = InputUtils::sanitizeText($_POST['EventId'] ?? '');
                $eventId = null;
                
                if (str_starts_with($eventIdInput, 'new:')) {
                    // Create new event
                    $newEventTitle = InputUtils::sanitizeText($_POST['NewEventTitle'] ?? '');
                    if (empty($newEventTitle)) {
                        $csvError = gettext('Please provide a title for the new event.');
                    } else {
                        $event = $attendanceService->getOrCreateRecurringEvent($newEventTitle);
                        $eventId = $event->getId();
                    }
                } else {
                    $eventId = (int)$eventIdInput;
                }
                
                if ($eventId && empty($csvError)) {
                    // Parse CSV data
                    $csvData = [];
                    while ($aData = fgetcsv($pFile, 2048, ',')) {
                        $record = [];
                        
                        for ($col = 0; $col < $numCol; $col++) {
                            switch ($columnMapping[$col]) {
                                case 1: // Person ID
                                    $record['personId'] = (int)$aData[$col];
                                    break;
                                case 2: // Date
                                    $record['date'] = $aData[$col];
                                    break;
                                case 3: // Time
                                    $record['time'] = $aData[$col];
                                    break;
                                case 4: // DateTime
                                    $record['datetime'] = $aData[$col];
                                    break;
                            }
                        }
                        
                        if (!empty($record['personId'])) {
                            $csvData[] = $record;
                        }
                    }
                    
                    fclose($pFile);
                    
                    // Import attendance records
                    $importResults = $attendanceService->importAttendanceRecords($csvData, $eventId);
                    
                    // Clean up temp file
                    unlink($csvTempFile);
                    unset($_SESSION['attendanceCsvTempFile']);
                }
            }
        }
        
        // Display import results
        if ($importResults !== null) {
            if ($importResults['success']) {
                ?>
                <div class="alert alert-success">
                    <h4><i class="fa fa-check"></i> <?= gettext('Import Completed') ?></h4>
                    <p>
                        <?= gettext('Imported') ?>: <strong><?= $importResults['imported'] ?></strong><br>
                        <?= gettext('Skipped') ?>: <strong><?= $importResults['skipped'] ?></strong>
                    </p>
                    <?php if (!empty($importResults['errors'])): ?>
                        <details>
                            <summary><?= gettext('View Errors/Warnings') ?> (<?= count($importResults['errors']) ?>)</summary>
                            <ul class="mt-2">
                                <?php foreach ($importResults['errors'] as $error): ?>
                                    <li><?= InputUtils::escapeHTML($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
                <a href="AttendanceCSVImport.php" class="btn btn-primary">
                    <i class="fa fa-upload"></i> <?= gettext('Import Another File') ?>
                </a>
                <?php
            }
        } elseif ($iStage === 1) {
            // Initial upload form
            if (!empty($csvError)) {
                echo '<div class="alert alert-danger">' . InputUtils::escapeHTML($csvError) . '</div>';
            }
            ?>
            
            <h4><?= gettext('Upload CSV File') ?></h4>
            <p><?= gettext('Upload a CSV file containing attendance data with the following format:') ?></p>
            
            <div class="alert alert-info">
                <strong><?= gettext('CSV Format') ?>:</strong><br>
                <code>PersonID, Date, Time</code> <?= gettext('or') ?> <code>PersonID, DateTime</code><br><br>
                <strong><?= gettext('Example') ?>:</strong><br>
                <pre>PersonID,Date,Time
123,2024-01-15,09:30:00
124,2024-01-15,09:35:00
125,2024-01-15,09:32:00</pre>
            </div>
            
            <form method="post" action="AttendanceCSVImport.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="CSVfile"><?= gettext('Select CSV File') ?></label>
                    <input type="file" class="form-control-file" name="CSVfile" id="CSVfile" accept=".csv" required>
                </div>
                
                <button type="submit" class="btn btn-primary" name="UploadCSV">
                    <i class="fa fa-upload"></i> <?= gettext('Upload and Map Columns') ?>
                </button>
            </form>
            
            <?php
        }
        ?>
        
    </div>
</div>

<?php require_once __DIR__ . '/Include/Footer.php'; ?>
