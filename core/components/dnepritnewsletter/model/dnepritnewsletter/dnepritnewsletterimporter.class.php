<?php

class DnepritNewsletterImporter
{
    /** @var modX */
    protected $modx;

    /** @var string */
    protected $directory;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->directory = MODX_CORE_PATH . 'cache/dnepritnewsletter/imports/';
    }

    public function storeUploadedFile(array $file)
    {
        $this->cleanup();

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_upload'));
        }

        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_upload'));
        }

        $maxSize = (int)$this->modx->getOption('dnepritnewsletter.import_max_size', null, 10485760);
        if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxSize) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_size'));
        }

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_extension'));
        }

        $this->ensureDirectory();
        $token = bin2hex(random_bytes(24));
        $path = $this->getPath($token, $extension);

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_store'));
        }

        @chmod($path, 0600);

        return [
            'token' => $token,
            'extension' => $extension,
            'path' => $path,
            'original_name' => basename((string)$file['name']),
        ];
    }

    public function inspect($path, $extension, $limit = 10)
    {
        $delimiter = $extension === 'txt' ? 'single' : $this->detectDelimiter($path);
        $rows = [];
        $maxColumns = 0;

        foreach ($this->readRows($path, $delimiter) as $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $row = array_values($row);
            $rows[] = $row;
            $maxColumns = max($maxColumns, count($row));

            if (count($rows) >= $limit) {
                break;
            }
        }

        if (!$rows) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_empty'));
        }

        return [
            'delimiter' => $delimiter,
            'rows' => $rows,
            'columns' => $maxColumns,
        ];
    }

    public function resolveFile($token, $extension)
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_token'));
        }

        if (!in_array($extension, ['csv', 'txt'], true)) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_token'));
        }

        $path = $this->getPath($token, $extension);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_expired'));
        }

        return $path;
    }

    public function readRows($path, $delimiter)
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_read'));
        }

        try {
            if ($delimiter === 'single') {
                while (($line = fgets($handle)) !== false) {
                    yield [$this->toUtf8(trim($line))];
                }
                return;
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                yield array_map([$this, 'toUtf8'], $row);
            }
        } finally {
            fclose($handle);
        }
    }

    public function remove($path)
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function isEmptyRow(array $row)
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    public function toUtf8($value)
    {
        $value = (string)$value;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        if (!function_exists('mb_detect_encoding') || mb_check_encoding($value, 'UTF-8')) {
            return trim($value);
        }

        $encoding = mb_detect_encoding($value, ['Windows-1251', 'ISO-8859-1'], true);
        if ($encoding) {
            $converted = mb_convert_encoding($value, 'UTF-8', $encoding);
            return trim($converted);
        }

        return trim($value);
    }

    protected function detectDelimiter($path)
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_read'));
        }

        $line = '';
        while (($candidate = fgets($handle)) !== false) {
            if (trim($candidate) !== '') {
                $line = $this->toUtf8($candidate);
                break;
            }
        }
        fclose($handle);

        if ($line === '') {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_empty'));
        }

        $delimiters = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = 1;

        foreach ($delimiters as $delimiter) {
            $count = count(str_getcsv($line, $delimiter));
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $bestCount > 1 ? $best : 'single';
    }

    protected function ensureDirectory()
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_import_err_store'));
        }
    }

    protected function cleanup()
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $expires = time() - 86400;
        foreach (glob($this->directory . '*.{csv,txt}', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $expires) {
                @unlink($path);
            }
        }
    }

    protected function getPath($token, $extension)
    {
        return $this->directory . $token . '.' . $extension;
    }
}
