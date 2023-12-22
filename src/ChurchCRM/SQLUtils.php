<?php

namespace ChurchCRM;

// Sourced from http://stackoverflow.com/questions/147821/loading-sql-files-from-within-php
class SQLUtils
{
    /**
     * Import SQL from file.
     *
     * @param string path to sql file
     */
    public static function sqlImport(string $fileName, $mysqli): void
    {
        $delimiter = ';';
        $fileHandle = fopen($fileName, 'r');
        $isFirstRow = true;
        $isMultiLineComment = false;
        $sql = '';

        if (!$fileHandle) {
            throw new \Exception(gettext('Unable to open file') . ': ' . $fileName);
        }

        while (!feof($fileHandle)) {
            $row = fgets($fileHandle);

            // remove BOM for utf-8 encoded file
            if ($isFirstRow) {
                $row = preg_replace('/^\x{EF}\x{BB}\x{BF}/', '', $row);
                $isFirstRow = false;
            }

            // 1. ignore empty string and comment row
            if (trim($row) == '' || preg_match('/^\s*(#|--\s)/sUi', $row)) {
                continue;
            }

            // 2. clear comments
            $row = trim(self::clearSQL($row, $isMultiLineComment));

            // 3. parse delimiter row
            if (preg_match('/^DELIMITER\s+[^ ]+/sUi', $row)) {
                $delimiter = preg_replace('/^DELIMITER\s+([^ ]+)$/sUi', '$1', $row);
                continue;
            }

            // 4. separate sql queries by delimiter
            $offset = 0;
            while (strpos($row, (string) $delimiter, $offset) !== false) {
                $delimiterOffset = strpos($row, (string) $delimiter, $offset);
                if (self::isQuoted($delimiterOffset, $row)) {
                    $offset = $delimiterOffset + strlen($delimiter);
                } else {
                    $sql = trim($sql . ' ' . trim(mb_substr($row, 0, $delimiterOffset)));
                    self::query($sql, $mysqli);
                    $row = mb_substr($row, $delimiterOffset + strlen($delimiter));
                    $offset = 0;
                    $sql = '';
                }
            }
            $sql = trim($sql . ' ' . $row);
        }
        if (strlen($sql) > 0) {
            self::query($row, $mysqli);
        }

        fclose($fileHandle);
    }

    /**
     * Remove comments from sql.
     *
     * @param string sql
     * @param bool is multicomment line
     *
     * @return string
     */
    private static function clearSQL($sql, &$isMultiComment)
    {
        if ($isMultiComment) {
            if (preg_match('#\*/#sUi', $sql)) {
                $sql = preg_replace('#^.*\*/\s*#sUi', '', $sql);
                $isMultiComment = false;
            } else {
                $sql = '';
            }
            if (trim($sql) == '') {
                return $sql;
            }
        }

        $offset = 0;
        while (preg_match('{--\s|#|/\*[^!]}sUi', $sql, $matched, PREG_OFFSET_CAPTURE, $offset)) {
            [$comment, $foundOn] = $matched[0];
            if (self::isQuoted($foundOn, $sql)) {
                $offset = $foundOn + strlen($comment);
            } else {
                if (mb_substr($comment, 0, 2) == '/*') {
                    $closedOn = strpos($sql, '*/', $foundOn);
                    if ($closedOn !== false) {
                        $sql = mb_substr($sql, 0, $foundOn) . mb_substr($sql, $closedOn + 2);
                    } else {
                        $sql = mb_substr($sql, 0, $foundOn);
                        $isMultiComment = true;
                    }
                } else {
                    $sql = mb_substr($sql, 0, $foundOn);
                    break;
                }
            }
        }

        return $sql;
    }

    /**
     * Check if "offset" position is quoted.
     *
     * @param int    $offset
     * @param string $text
     *
     * @return bool
     */
    private static function isQuoted($offset, $text): bool
    {
        if ($offset > strlen($text)) {
            $offset = strlen($text);
        }

        $isQuoted = false;
        for ($i = 0; $i < $offset; $i++) {
            if ($text[$i] === "'") {
                $isQuoted = !$isQuoted;
            }
            if ($text[$i] == '\\' && $isQuoted) {
                $i++;
            }
        }

        return $isQuoted;
    }

    private static function query($sql, $mysqli): void
    {
        if (preg_match("/DEFINER\s*=.*@.*/", $sql)) {
            return;
        }
        if (!$query = $mysqli->query($sql)) {
            throw new \Exception("Cannot execute request to the database {$sql}: " . $mysqli->error);
        }
    }
}
