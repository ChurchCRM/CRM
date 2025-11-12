<?php

namespace ChurchCRM\Utils;

use League\Csv\Exception;
use League\Csv\Writer;
use ChurchCRM\dto\SystemConfig;

/**
 * CsvExporter - Secure CSV export utility using League\CSV
 *
 * Self-contained CSV generation utility that handles:
 * - Formula injection prevention (escaping formula trigger characters)
 * - Character set translation for export compatibility
 * - Proper CSV RFC 4180 compliance via League\CSV
 *
 * Works with simple data structures (headers array + 2D rows array).
 * All data transformation happens internally when rows are added.
 * Database queries and initial data preparation should be done by caller.
 *
 * @see https://csv.thephpleague.com/
 * @see https://github.com/ChurchCRM/CRM/issues/5465
 */
class CsvExporter
{
    private Writer $writer;
    private string $charset;
    /**
     * Constructor
     *
     * @param string $charset Character set for encoding (default: UTF-8)
     * @throws Exception
     */
    public function __construct(string $charset = 'UTF-8')
    {
        $this->charset = $charset;
        // Create a writer using a temporary file with seek support
        $tempFile = tmpfile();
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file for CSV export');
        }
        $this->writer = Writer::createFromStream($tempFile);
        // Set delimiter to comma (RFC 4180 standard)
        $this->writer->setDelimiter(',');
    }

    /**
     * Translate special characters for charset compatibility
     *
     * Converts from UTF-8 to the target CSV export charset if needed.
     * Handles gettext translation as well.
     *
     * @param string $value Value to translate
     * @return string Translated value
     */
    private function translateSpecialCharset(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($this->charset === 'UTF-8') {
            return gettext($value);
        }

        $resultString = iconv(
            'UTF-8',
            $this->charset,
            gettext($value)
        );

        if ($resultString === false) {
            throw new \RuntimeException("Failed to convert charset from UTF-8 to {$this->charset}");
        }

        return $resultString;
    }

    /**
     * Escape formula injection attempts by prepending tab to formula triggers
     *
     * Prevents Excel from interpreting values as formulas when they start with
     * =, -, +, or @ characters.
     *
     * @param string $value Value to escape
     * @return string Escaped value
     */
    private function escapeFormulaInjection(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '-', '+', '@'], true)) {
            return "\t" . $value;
        }

        return $value;
    }

    /**
     * Process a value for CSV export: translate charset and escape formula injection
     *
     * Handles all transformations needed for safe CSV output:
     * 1. Apply charset translation for export compatibility
     * 2. Escape formula injection attempts
     *
     * @param mixed $value Value to process
     * @return string Processed value
     */
    private function processValue($value): string
    {
        $stringValue = (string) $value;

        if (empty($stringValue)) {
            return $stringValue;
        }

        // Step 1: Translate charset for export compatibility
        $stringValue = $this->translateSpecialCharset($stringValue);

        // Step 2: Escape formula injection
        return $this->escapeFormulaInjection($stringValue);
    }

    /**
     * Insert header row
     *
     * @param array $headers Header values
     * @throws Exception
     */
    public function insertHeaders(array $headers): self
    {
        $processed = array_map([$this, 'processValue'], $headers);
        $this->writer->insertOne($processed);
        return $this;
    }

    /**
     * Insert a single data row
     *
     * @param array $row Row data
     * @throws Exception
     */
    public function insertRow(array $row): self
    {
        $processed = array_map([$this, 'processValue'], $row);
        $this->writer->insertOne($processed);
        return $this;
    }

    /**
     * Insert multiple data rows
     *
     * @param iterable $rows 2D array or iterable of rows
     * @throws Exception
     */
    public function insertRows(iterable $rows): self
    {
        foreach ($rows as $row) {
            $this->insertRow($row);
        }
        return $this;
    }

    /**
     * Send CSV export with appropriate HTTP headers and output
     *
     * @param string $filename Output filename
     * @param string $contentType Content-Type header (default: text/csv)
     * @throws Exception
     */
    public function output(string $filename = 'export.csv', string $contentType = 'text/csv'): void
    {
        header("Content-Type: {$contentType}; charset={$this->charset}");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($this->writer->toString()));

        // Output CSV content without seeking
        echo $this->writer->toString();
        exit;
    }

    /**
     * Output CSV data only (headers must be sent by caller)
     *
     * Use this method when you need to set custom headers before calling this method.
     * This outputs the CSV content and exits.
     *
     * @throws Exception
     */
    public function outputOnly(): void
    {
        // Output CSV content without seeking
        echo $this->writer->toString();
        exit;
    }

    /**
     * Create and output CSV from headers and data rows
     *
     * Convenience static method for simple use cases.
     * Automatically appends .csv extension and optionally includes today's date.
     *
     * @param array $headers Column headers
     * @param array $rows 2D array of data rows
     * @param string $basename Base filename (without extension)
     * @param string $charset Character encoding
     * @param bool $includeDateInFilename Whether to append today's date to filename
     * @throws Exception
     */
    public static function create(
        array $headers,
        array $rows,
        string $basename = 'export',
        string $charset = 'UTF-8',
        bool $includeDateInFilename = false
    ): void {
        // Generate filename: basename + optional date + .csv extension
        $filename = $basename;
        if ($includeDateInFilename) {
            $dateFormat = SystemConfig::getValue('sDateFilenameFormat');
            $filename .= '-' . date($dateFormat);
        }
        $filename .= '.csv';

        $exporter = new self($charset);
        $exporter->insertHeaders($headers);
        $exporter->insertRows($rows);
        $exporter->output($filename);
    }
}
