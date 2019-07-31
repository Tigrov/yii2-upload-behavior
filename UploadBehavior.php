<?php
namespace tigrov\uploadBehavior;

use yii\db\BaseActiveRecord;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * UploadBehavior save uploaded file into [[$path]]
 *
 * Usage at [[BaseActiveRecord::behaviors()]] add the following code
 *
 * ~~~
 * return [
 *     ...
 *     [
 *         'class' => 'common\behaviors\UploadBehavior',
 *         'path' => '@runtime/upload',
 *         'attributes' => ['file'],
 *     ],
 * ];
 * ~~~
 *
 * @property BaseActiveRecord $owner
 *
 * @author Sergei Tigrov <rrr-r@ya.ru>
 */
class UploadBehavior extends \yii\base\Behavior
{
    /**
     * @var string the directory to store uploaded files. You may use path alias here.
     * If not set, it will use the "upload" subdirectory under the application runtime path.
     */
    public $path = '@runtime/upload';

    /**
     * @var string[]  attributes that will receive uploaded files
     */
    public $attributes = ['file'];

    /**
     * @var integer the level of sub-directories to store uploaded files. Defaults to 1.
     * If the system has huge number of uploaded files (e.g. one million), you may use a bigger value
     * (usually no bigger than 3). Using sub-directories is mainly to ensure the file system
     * is not over burdened with a single directory having too many files.
     */
    public $directoryLevel = 1;

    /**
     * @var boolean when true `saveFiles()` will be called on event 'beforeSave'
     */
    public $autoSave = true;

    /**
     * @var boolean when true `removeOld()` will be called on event 'afterUpdate'
     */
    public $removeOld = true;

    /**
     * @var \Closure|string function (BaseActiveRecord $model, string $attribute, UploadedFile $file, string $filename): bool
     * it will be called for saving uploaded file
     */
    public $saveCallback;

    private $files;

    private $oldFiles;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->attributes = (array) $this->attributes;
        $this->path = \Yii::getAlias($this->path);
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        $event = [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterUpdate',
        ];

        return $event;
    }

    public function getUploadedFile($attribute)
    {
        if (!isset($this->files[$attribute])) {
            $this->files[$attribute] = $this->owner->$attribute instanceof UploadedFile
                ? $this->owner->$attribute
                : UploadedFile::getInstance($this->owner, $attribute);
        }

        return $this->files[$attribute];
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getFilePath($filename)
    {
        $path = $this->path;
        if ($this->directoryLevel > 0) {
            $hash = md5($filename);
            for ($i = 0; $i < $this->directoryLevel; ++$i) {
                if (($subdir = substr($hash, 0, 2)) !== false) {
                    $path .= DIRECTORY_SEPARATOR . $subdir;
                    $hash = substr($hash, 2);
                }
            }
        }

        return $path;
    }

    public function getCallback()
    {
        return $this->saveCallback !== null && is_string($this->saveCallback)
            ? [$this->owner, $this->saveCallback]
            : $this->saveCallback;
    }

    /**
     * Save uploaded files into [[$path]]
     * @return boolean|null if success return true, fault return false.
     * Return null mean no uploaded file.
     */
    public function saveFiles()
    {
        $result = null;
        /* @var $file UploadedFile */
        foreach ($this->attributes as $attribute) {
            $file = $this->getUploadedFile($attribute);

            if ($file !== null) {
                $filename = $file->getBaseName() . '.' . $file->getExtension();
                $path = $this->getFilePath($filename);
                FileHelper::createDirectory($path);
                $fullpath = $path  . DIRECTORY_SEPARATOR . $filename;
                $this->owner->$attribute = $fullpath;
                if ($callback = $this->getCallback()) {
                    $result = call_user_func($callback, $this->owner, $attribute, $file, $fullpath);
                } else {
                    $result = $file->saveAs($fullpath);
                }
            }
        }

        return $result;
    }

    public function removeOldFiles()
    {
        foreach ($this->attributes as $attribute) {
            if (!empty($this->oldFiles[$attribute])) {
                if ($this->owner->getAttribute($attribute) != $this->oldFiles[$attribute]) {
                    @unlink($this->oldFiles[$attribute]);
                }
            }
        }
    }

    public function storeOldFiles()
    {
        foreach ($this->attributes as $attribute) {
            $this->oldFiles[$attribute] = $this->owner->getOldAttribute($attribute);
        }
    }

    /**
     * Event handler for beforeValidate
     * @param yii\base\ModelEvent $event
     */
    public function beforeValidate($event)
    {
        foreach ($this->attributes as $attribute) {
            $this->owner->$attribute = $this->getUploadedFile($attribute);
        }
    }

    /**
     * Event handler for beforeSave
     * @param yii\base\ModelEvent $event
     */
    public function beforeSave($event)
    {
        if ($this->autoSave && $this->saveFiles() === false) {
            $event->isValid = false;
        }
        if ($event->name == BaseActiveRecord::EVENT_BEFORE_UPDATE && $this->removeOld) {
            $this->storeOldFiles();
        }
    }

    /**
     * Event handler for beforeDelete
     * @param yii\base\ModelEvent $event
     */
    public function beforeDelete($event)
    {
        if ($this->removeOld) {
            $this->storeOldFiles();
        }
    }

    public function afterUpdate($event)
    {
        if ($this->removeOld) {
            $this->removeOldFiles();
        }
    }
}