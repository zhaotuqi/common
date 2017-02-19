<?php
/**
 * Created by PhpStorm.
 * User: jesse
 * Date: 17/2/17
 * Time: 17:35
 */

namespace App\Extension;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogRewrite extends StreamHandler
{
    const FILE_PER_DAY = 'Y-m-d';
    const FILE_PER_MONTH = 'Y-m';
    const FILE_PER_YEAR = 'Y';

    protected $filename;
    protected $maxFiles;
    protected $mustRotate;
    protected $nextRotation;
    protected $filenameFormat;
    protected $dateFormat;

    /**
     * @param string $filename
     * @param int $maxFiles The maximal amount of files to keep (0 means unlimited)
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param Boolean $useLocking Try to lock log file before doing any writes
     */
    public function __construct($filename, $maxFiles = 0, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false)
    {
        $this->filename = $filename;
        $this->maxFiles = (int)$maxFiles;
        $this->nextRotation = new \DateTime(date('Y-m-d H:00:00', time() + 3600));
        $this->filenameFormat = '{filename}_{date}';
        $this->dateFormat = 'YmdH';

        parent::__construct($this->getTimedFilename(), $level, $bubble, $filePermission, $useLocking);

        $this->setFormatter(new \Monolog\Formatter\LineFormatter(
            "[%datetime%] %level_name% " . config('app.app_name') . " %message% %context% %extra%\n",
            'Y-m-d H:i:s.u',
            true,
            true
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        parent::close();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    public function setFilenameFormat($filenameFormat, $dateFormat)
    {
        if (!preg_match('{^Y(([/_.-]?m)([/_.-]?d)?)?$}', $dateFormat)) {
            trigger_error(
                'Invalid date format - format must be one of ' .
                'RotatingFileHandler::FILE_PER_DAY ("Y-m-d"), RotatingFileHandler::FILE_PER_MONTH ("Y-m") ' .
                'or RotatingFileHandler::FILE_PER_YEAR ("Y"), or you can set one of the ' .
                'date formats using slashes, underscores and/or dots instead of dashes.',
                E_USER_DEPRECATED
            );
        }
        if (substr_count($filenameFormat, '{date}') === 0) {
            trigger_error(
                'Invalid filename format - format should contain at least `{date}`, because otherwise rotating is impossible.',
                E_USER_DEPRECATED
            );
        }
        $this->filenameFormat = $filenameFormat;
        $this->dateFormat = $dateFormat;
        $this->url = $this->getTimedFilename();
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if (null === $this->mustRotate) {
            $this->mustRotate = !file_exists($this->url);
        }

        if ($this->nextRotation < $record['datetime']) {
            $this->mustRotate = true;
            $this->close();
        }

        parent::write($record);
    }

    /**
     * Rotates the files.
     */
    protected function rotate()
    {
        // update filename
        $this->url = $this->getTimedFilename();

        $this->nextRotation = new \DateTime(date('Y-m-d H:00:00', time() + 3600));

        // skip GC of old logs if files are unlimited
        if (0 === $this->maxFiles) {
            return;
        }

        $logFiles = glob($this->getGlobPattern());
        if ($this->maxFiles >= count($logFiles)) {
            // no files to remove
            return;
        }

        // Sorting the files by name to remove the older ones
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });

        foreach (array_slice($logFiles, $this->maxFiles) as $file) {
            if (is_writable($file)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                });
                unlink($file);
                restore_error_handler();
            }
        }

        $this->mustRotate = false;
    }

    protected function getTimedFilename()
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = str_replace(
            ['{filename}', '{date}'],
            [$fileInfo['filename'], date($this->dateFormat)],
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );

        if (!empty($fileInfo['extension'])) {
            $timedFilename .= '.' . $fileInfo['extension'];
        }

        return $timedFilename;
    }

    protected function getGlobPattern()
    {
        $fileInfo = pathinfo($this->filename);

        $glob = str_replace(
            ['{filename}', '{date}'],
            [$fileInfo['filename'], '*'],
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );
        if (!empty($fileInfo['extension'])) {
            $glob .= '.' . $fileInfo['extension'];
        }

        return $glob;
    }
}