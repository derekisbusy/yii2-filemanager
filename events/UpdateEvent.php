<?php


namespace derekisbusy\filemanager\events;


class UpdateEvent extends \yii\base\Event {
    public $media;
    public $mediaId;
}
