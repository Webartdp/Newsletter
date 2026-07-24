<?php

class DnepritNewsletterSubscriberImportProcessor extends modProcessor
{
    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_subscribers_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $token = trim((string)$this->getProperty('token', ''));
        $extension = trim((string)$this->getProperty('extension', ''));
        $delimiter = (string)$this->getProperty('delimiter', 'single');
        $emailColumn = (int)$this->getProperty('email_column', 0);
        $nameColumn = (int)$this->getProperty('name_column', -1);
        $hasHeader = $this->toBoolean($this->getProperty('has_header', false));
        $duplicateMode = (string)$this->getProperty('duplicate_mode', 'skip');
        $reactivate = $this->toBoolean($this->getProperty('reactivate_unsubscribed', false));

        if (!in_array($delimiter, ['single', ',', ';', "\t", '|'], true)) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_import_err_delimiter'));
        }

        if ($emailColumn < 0 || !in_array($duplicateMode, ['skip', 'update'], true)) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_import_err_mapping'));
        }

        $corePath = $this->modx->getOption(
            'dnepritnewsletter.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/dnepritnewsletter/'
        );
        require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletterimporter.class.php';

        $importer = new DnepritNewsletterImporter($this->modx);
        $path = null;

        try {
            $path = $importer->resolveFile($token, $extension);
            $report = [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'duplicates' => 0,
                'invalid' => 0,
                'empty' => 0,
                'errors' => 0,
                'error_rows' => [],
            ];

            $headerSkipped = !$hasHeader;
            $rowNumber = 0;

            foreach ($importer->readRows($path, $delimiter) as $row) {
                $rowNumber++;

                if ($importer->isEmptyRow($row)) {
                    $report['empty']++;
                    continue;
                }

                if (!$headerSkipped) {
                    $headerSkipped = true;
                    continue;
                }

                $report['processed']++;
                $email = isset($row[$emailColumn]) ? strtolower(trim((string)$row[$emailColumn])) : '';
                $name = $nameColumn >= 0 && isset($row[$nameColumn]) ? trim((string)$row[$nameColumn]) : '';

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $report['invalid']++;
                    $this->addErrorRow($report, $rowNumber, $email, 'invalid_email');
                    continue;
                }

                /** @var DnepritNewsletterSubscriber|null $subscriber */
                $subscriber = $this->modx->getObject('DnepritNewsletterSubscriber', ['email' => $email]);
                if ($subscriber) {
                    $report['duplicates']++;

                    if ($duplicateMode === 'skip') {
                        continue;
                    }

                    $changed = false;
                    if ($name !== '' && $name !== (string)$subscriber->get('name')) {
                        $subscriber->set('name', $name);
                        $changed = true;
                    }

                    if ($reactivate && $subscriber->get('status') === 'unsubscribed') {
                        $subscriber->set('status', 'active');
                        $subscriber->set('unsubscribed_at', null);
                        $changed = true;
                    }

                    if ($changed) {
                        $subscriber->set('updated_at', date('Y-m-d H:i:s'));
                        if ($subscriber->save()) {
                            $report['updated']++;
                        } else {
                            $report['errors']++;
                            $this->addErrorRow($report, $rowNumber, $email, 'save_failed');
                        }
                    }
                    continue;
                }

                /** @var DnepritNewsletterSubscriber $subscriber */
                $subscriber = $this->modx->newObject('DnepritNewsletterSubscriber');
                $now = date('Y-m-d H:i:s');
                $subscriber->fromArray([
                    'email' => $email,
                    'name' => $name,
                    'status' => 'active',
                    'source' => 'import',
                    'unsubscribe_token' => bin2hex(random_bytes(32)),
                    'failure_count' => 0,
                    'blocked_reason' => '',
                    'comment' => '',
                    'subscribed_at' => $now,
                    'updated_at' => $now,
                    'unsubscribed_at' => null,
                    'created_by' => (int)$this->modx->user->get('id'),
                ], '', true, true);

                if ($subscriber->save()) {
                    $report['created']++;
                } else {
                    $report['errors']++;
                    $this->addErrorRow($report, $rowNumber, $email, 'save_failed');
                }
            }

            return $this->success($this->modx->lexicon('dnepritnewsletter_import_success'), $report);
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        } finally {
            if ($path) {
                $importer->remove($path);
            }
        }
    }

    protected function toBoolean($value)
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    protected function addErrorRow(array &$report, $rowNumber, $email, $reason)
    {
        if (count($report['error_rows']) >= 50) {
            return;
        }

        $report['error_rows'][] = [
            'row' => (int)$rowNumber,
            'email' => (string)$email,
            'reason' => (string)$reason,
        ];
    }
}

return 'DnepritNewsletterSubscriberImportProcessor';
