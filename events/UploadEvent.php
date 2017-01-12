<?php


namespace derekisbusy\filemanager\events;


class UploadEvent extends \yii\base\Event {
    public $className;
    public $itemId;
    public $tempId;
}
