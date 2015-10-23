<?php


namespace derekisbusy\filemanager\events;


class UploadManagerEvent extends \yii\base\Event {
    public $relation = '';
    public $itemId;
    public $tempId;
}
