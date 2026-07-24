<?php

class DnepritNewsletterSubscriberImportPreviewProcessor extends modProcessor
{
    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_subscribers_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        if (!$file) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_import_err_file_required'));
        }

        $corePath = $this->modx->getOption(
            'dnepritnewsletter.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/dnepritnewsletter/'
        );
        require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletterimporter.class.php';

        try {
            $importer = new DnepritNewsletterImporter($this->modx);
            $stored = $importer->storeUploadedFile($file);
            $inspection = $importer->inspect($stored['path'], $stored['extension']);

            $suggestions = $this->detectColumns($inspection['rows'][0]);

            return $this->success('', [
                'token' => $stored['token'],
                'extension' => $stored['extension'],
                'filename' => $stored['original_name'],
                'delimiter' => $inspection['delimiter'],
                'columns' => $inspection['columns'],
                'rows' => $inspection['rows'],
                'email_column' => $suggestions['email_column'],
                'name_column' => $suggestions['name_column'],
                'has_header' => $suggestions['has_header'],
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }
    }

    protected function detectColumns(array $firstRow)
    {
        $emailColumn = 0;
        $nameColumn = count($firstRow) > 1 ? 1 : -1;
        $hasHeader = false;

        foreach ($firstRow as $index => $value) {
            $value = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string)$value), 'UTF-8')
                : strtolower(trim((string)$value));

            if (in_array($value, ['email', 'e-mail', 'mail', 'пошта', 'электронная почта'], true)) {
                $emailColumn = (int)$index;
                $hasHeader = true;
            }

            if (in_array($value, ['name', 'fullname', 'full name', 'ім’я', 'имя', 'піб', 'фио'], true)) {
                $nameColumn = (int)$index;
                $hasHeader = true;
            }
        }

        return [
            'email_column' => $emailColumn,
            'name_column' => $nameColumn,
            'has_header' => $hasHeader,
        ];
    }
}

return 'DnepritNewsletterSubscriberImportPreviewProcessor';
